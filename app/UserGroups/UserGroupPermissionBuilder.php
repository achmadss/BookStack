<?php

namespace BookStack\UserGroups;

use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Chapter;
use BookStack\Entities\Models\Entity;
use BookStack\Entities\Models\Page;
use BookStack\UserGroups\Models\UserGroup;
use BookStack\Users\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * UserGroupPermissionBuilder manages the cache table that stores
 * which users have access to which entities via their user group memberships.
 */
class UserGroupPermissionBuilder
{
    /**
     * Rebuild the permission cache for all users and groups.
     */
    public function rebuildAll(): void
    {
        DB::table('user_group_joint_permissions')->truncate();

        $userGroups = UserGroup::query()->with(['users', 'contentItems'])->get();

        foreach ($userGroups as $group) {
            $this->rebuildForGroup($group);
        }
    }

    /**
     * Rebuild permission cache for a specific user.
     */
    public function rebuildForUser(User $user): void
    {
        // Delete existing cache for this user
        DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->delete();

        // Get all groups this user belongs to
        $userGroups = $user->userGroups()->with('contentItems')->get();

        foreach ($userGroups as $group) {
            $accessibleEntityIds = $this->computeAccessibleEntitiesForGroup($group);
            $this->insertCacheRecords($user->id, $accessibleEntityIds);
        }
    }

    /**
     * Rebuild permission cache for a specific user group.
     */
    public function rebuildForGroup(UserGroup $group): void
    {
        $group->load(['users', 'contentItems']);

        $accessibleEntityIds = $this->computeAccessibleEntitiesForGroup($group);

        foreach ($group->users as $user) {
            // Delete ALL existing cache for this user (not just the new accessible ones)
            // This ensures old permissions are removed when content is removed from the group
            DB::table('user_group_joint_permissions')
                ->where('user_id', $user->id)
                ->delete();

            // Rebuild from scratch for all their groups
            $this->rebuildForUser($user);
        }
    }

    /**
     * Rebuild permission cache for a specific entity.
     * This is called when an entity is added/removed from groups.
     */
    public function rebuildForEntity(Entity $entity): void
    {
        $entityType = $entity->getMorphClass();
        $entityId = $entity->id;

        // Get all groups that have access to this entity
        $groups = UserGroup::query()
            ->whereHas('contentItems', function ($query) use ($entityId, $entityType) {
                $query->where('entity_id', $entityId)
                    ->where('entity_type', $entityType);
            })
            ->with('users')
            ->get();

        // Delete existing cache for this entity
        DB::table('user_group_joint_permissions')
            ->where('entity_id', $entityId)
            ->where('entity_type', $entityType)
            ->delete();

        // Rebuild cache for all affected users
        $userIds = $groups->pluck('users')->flatten()->pluck('id')->unique();
        foreach ($userIds as $userId) {
            DB::table('user_group_joint_permissions')->insert([
                'user_id' => $userId,
                'entity_id' => $entityId,
                'entity_type' => $entityType,
                'has_access' => true,
            ]);
        }

        // If this is a shelf or book, also rebuild for child entities
        if ($entity instanceof Bookshelf) {
            $this->rebuildForShelfChildren($entity);
        } elseif ($entity instanceof Book) {
            $this->rebuildForBookChildren($entity);
        }
    }

    /**
     * Compute all accessible entities for a given group.
     * Returns array with entity types as keys and arrays of IDs as values.
     *
     * @return array<string, array<int>>
     */
    protected function computeAccessibleEntitiesForGroup(UserGroup $group): array
    {
        $accessible = [
            'bookshelf' => [],
            'book' => [],
            'chapter' => [],
            'page' => [],
        ];

        foreach ($group->contentItems as $contentItem) {
            if ($contentItem->entity_type === 'bookshelf') {
                // Add the shelf itself
                $accessible['bookshelf'][] = $contentItem->entity_id;

                // Add all books in this shelf
                $bookIds = DB::table('bookshelves_books')
                    ->where('bookshelf_id', $contentItem->entity_id)
                    ->pluck('book_id')
                    ->toArray();

                $accessible['book'] = array_merge($accessible['book'], $bookIds);

                // Add all chapters and pages in those books
                foreach ($bookIds as $bookId) {
                    $childrenIds = $this->getBookChildrenIds($bookId);
                    $accessible['chapter'] = array_merge($accessible['chapter'], $childrenIds['chapters']);
                    $accessible['page'] = array_merge($accessible['page'], $childrenIds['pages']);
                }
            } elseif ($contentItem->entity_type === 'book') {
                // Add the book itself
                $accessible['book'][] = $contentItem->entity_id;

                // Add all chapters and pages in this book
                $childrenIds = $this->getBookChildrenIds($contentItem->entity_id);
                $accessible['chapter'] = array_merge($accessible['chapter'], $childrenIds['chapters']);
                $accessible['page'] = array_merge($accessible['page'], $childrenIds['pages']);
            }
        }

        // Remove duplicates
        foreach ($accessible as $type => $ids) {
            $accessible[$type] = array_unique($ids);
        }

        return $accessible;
    }

    /**
     * Get all chapter and page IDs for a given book.
     *
     * @return array{chapters: array<int>, pages: array<int>}
     */
    protected function getBookChildrenIds(int $bookId): array
    {
        $chapterIds = DB::table('entities')
            ->where('type', 'chapter')
            ->where('book_id', $bookId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        $pageIds = DB::table('entities')
            ->where('type', 'page')
            ->where('book_id', $bookId)
            ->whereNull('deleted_at')
            ->pluck('id')
            ->toArray();

        return [
            'chapters' => $chapterIds,
            'pages' => $pageIds,
        ];
    }

    /**
     * Insert cache records for a user and their accessible entities.
     *
     * @param int                         $userId
     * @param array<string, array<int>>   $accessibleEntityIds
     */
    protected function insertCacheRecords(int $userId, array $accessibleEntityIds): void
    {
        $records = [];

        foreach ($accessibleEntityIds as $entityType => $ids) {
            foreach ($ids as $entityId) {
                $records[] = [
                    'user_id' => $userId,
                    'entity_id' => $entityId,
                    'entity_type' => $entityType,
                    'has_access' => true,
                ];
            }
        }

        if (!empty($records)) {
            // Insert in chunks to avoid query size limits
            foreach (array_chunk($records, 500) as $chunk) {
                DB::table('user_group_joint_permissions')->insert($chunk);
            }
        }
    }

    /**
     * Rebuild cache for all children of a shelf (books, chapters, pages).
     */
    protected function rebuildForShelfChildren(Bookshelf $shelf): void
    {
        $books = DB::table('bookshelves_books')
            ->where('bookshelf_id', $shelf->id)
            ->pluck('book_id');

        foreach ($books as $bookId) {
            $book = Book::find($bookId);
            if ($book) {
                $this->rebuildForEntity($book);
            }
        }
    }

    /**
     * Rebuild cache for all children of a book (chapters and pages).
     */
    protected function rebuildForBookChildren(Book $book): void
    {
        $childrenIds = $this->getBookChildrenIds($book->id);

        // Get all groups that have access to this book
        $groups = UserGroup::query()
            ->whereHas('contentItems', function ($query) use ($book) {
                $query->where('entity_id', $book->id)
                    ->where('entity_type', 'book');
            })
            ->with('users')
            ->get();

        $userIds = $groups->pluck('users')->flatten()->pluck('id')->unique();

        // Rebuild cache for chapters
        foreach ($childrenIds['chapters'] as $chapterId) {
            DB::table('user_group_joint_permissions')
                ->where('entity_id', $chapterId)
                ->where('entity_type', 'chapter')
                ->delete();

            foreach ($userIds as $userId) {
                DB::table('user_group_joint_permissions')->insert([
                    'user_id' => $userId,
                    'entity_id' => $chapterId,
                    'entity_type' => 'chapter',
                    'has_access' => true,
                ]);
            }
        }

        // Rebuild cache for pages
        foreach ($childrenIds['pages'] as $pageId) {
            DB::table('user_group_joint_permissions')
                ->where('entity_id', $pageId)
                ->where('entity_type', 'page')
                ->delete();

            foreach ($userIds as $userId) {
                DB::table('user_group_joint_permissions')->insert([
                    'user_id' => $userId,
                    'entity_id' => $pageId,
                    'entity_type' => 'page',
                    'has_access' => true,
                ]);
            }
        }
    }
}

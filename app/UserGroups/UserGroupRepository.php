<?php

namespace BookStack\UserGroups;

use BookStack\Entities\Models\Bookshelf;
use BookStack\Entities\Models\Book;
use BookStack\Entities\Models\Entity;
use BookStack\UserGroups\Models\UserGroup;
use BookStack\Users\Models\User;
use BookStack\Util\SimpleListOptions;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

class UserGroupRepository
{
    /**
     * Get all user groups paginated.
     */
    public function getAllPaginated(int $perPage = 20, ?SimpleListOptions $listOptions = null): LengthAwarePaginator
    {
        $query = UserGroup::query()
            ->withCount(['users', 'contentItems']);

        // Add content_count as alias for contentItems_count for sorting
        $query->selectRaw('user_groups.*, (SELECT COUNT(*) FROM user_group_content WHERE user_group_content.user_group_id = user_groups.id) as content_count');

        // Apply search if provided
        if ($listOptions && $listOptions->getSearch()) {
            $search = $listOptions->getSearch();
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }

        // Apply sorting
        if ($listOptions) {
            $order = $listOptions->getOrder();
            $sort = $listOptions->getSort();
            
            if ($sort === 'users_count') {
                $query->orderBy('users_count', $order);
            } elseif ($sort === 'content_count') {
                $query->orderBy('content_count', $order);
            } elseif (in_array($sort, ['name', 'created_at', 'updated_at'])) {
                $query->orderBy($sort, $order);
            } else {
                $query->orderBy('sort_order')->orderBy('name');
            }
        } else {
            $query->orderBy('sort_order')->orderBy('name');
        }

        return $query->paginate($perPage);
    }

    /**
     * Get all user groups (not paginated).
     */
    public function getAll(): Collection
    {
        return UserGroup::query()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }

    /**
     * Get a user group by ID.
     */
    public function getById(int $id): UserGroup
    {
        return UserGroup::query()
            ->with(['users', 'contentItems'])
            ->findOrFail($id);
    }

    /**
     * Create a new user group with the given data.
     *
     * @param array{name: string, description: ?string, color: ?string} $data
     */
    public function create(array $data): UserGroup
    {
        $userGroup = new UserGroup();
        $userGroup->name = $data['name'];
        $userGroup->description = $data['description'] ?? '';
        $userGroup->color = $data['color'] ?? null;
        $userGroup->sort_order = UserGroup::query()->max('sort_order') + 1;
        $userGroup->created_by = user()->id ?? null;
        $userGroup->updated_by = user()->id ?? null;
        $userGroup->save();

        return $userGroup;
    }

    /**
     * Update an existing user group with the given data.
     *
     * @param array{name: ?string, description: ?string, color: ?string} $data
     */
    public function update(UserGroup $group, array $data): UserGroup
    {
        if (isset($data['name'])) {
            $group->name = $data['name'];
        }

        if (isset($data['description'])) {
            $group->description = $data['description'];
        }

        if (isset($data['color'])) {
            $group->color = $data['color'];
        }

        $group->updated_by = user()->id ?? null;
        $group->save();

        return $group;
    }

    /**
     * Delete a user group.
     */
    public function delete(UserGroup $group): void
    {
        $group->users()->detach();
        $group->contentItems()->delete();
        $group->delete();
    }

    /**
     * Update the users assigned to this group.
     *
     * @param array<int> $userIds
     */
    public function updateUsers(UserGroup $group, array $userIds): void
    {
        $syncData = [];
        foreach ($userIds as $index => $userId) {
            $syncData[$userId] = [
                'sort_order' => $index,
                'created_at' => now(),
            ];
        }

        $group->users()->sync($syncData);
    }

    /**
     * Update the content (shelves and books) assigned to this group.
     *
     * @param array{shelves: array<int>, books: array<int>} $contentData
     */
    public function updateContent(UserGroup $group, array $contentData): void
    {
        // Delete existing content associations
        $group->contentItems()->delete();

        // Add shelves
        if (!empty($contentData['shelves'])) {
            foreach ($contentData['shelves'] as $shelfId) {
                $group->contentItems()->create([
                    'entity_id' => $shelfId,
                    'entity_type' => 'bookshelf',
                    'created_at' => now(),
                ]);
            }
        }

        // Add books
        if (!empty($contentData['books'])) {
            foreach ($contentData['books'] as $bookId) {
                $group->contentItems()->create([
                    'entity_id' => $bookId,
                    'entity_type' => 'book',
                    'created_at' => now(),
                ]);
            }
        }
    }

    /**
     * Get all users not currently in this group.
     */
    public function getAvailableUsers(UserGroup $group): Collection
    {
        $currentUserIds = $group->users()->pluck('users.id');

        return User::query()
            ->whereNotIn('id', $currentUserIds)
            ->where(function($query) {
                $query->whereNull('system_name')
                      ->orWhere('system_name', '!=', 'public');
            })
            ->orderBy('name')
            ->get();
    }

    /**
     * Get all shelves not currently assigned to this group.
     */
    public function getAvailableShelves(UserGroup $group): Collection
    {
        $assignedShelfIds = $group->contentItems()
            ->where('entity_type', 'bookshelf')
            ->pluck('entity_id');

        return Bookshelf::query()
            ->whereNotIn('id', $assignedShelfIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * Get all books not currently assigned to this group.
     */
    public function getAvailableBooks(UserGroup $group): Collection
    {
        $assignedBookIds = $group->contentItems()
            ->where('entity_type', 'book')
            ->pluck('entity_id');

        return Book::query()
            ->whereNotIn('id', $assignedBookIds)
            ->orderBy('name')
            ->get(['id', 'name', 'slug']);
    }

    /**
     * Update the sort order of user groups.
     *
     * @param array<int> $groupIds Ordered array of group IDs
     */
    public function updateSortOrder(array $groupIds): void
    {
        foreach ($groupIds as $index => $groupId) {
            UserGroup::query()
                ->where('id', $groupId)
                ->update(['sort_order' => $index]);
        }
    }
}

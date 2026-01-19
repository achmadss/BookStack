<?php

namespace BookStack\UserGroups;

use BookStack\Entities\Models\Entity;
use BookStack\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * UserGroupApplicator applies user group restrictions to queries
 * and checks user access to entities via their group memberships.
 */
class UserGroupApplicator
{
    /**
     * Check if a user has access to an entity via their user groups.
     */
    public function userHasGroupAccess(User $user, Entity $entity): bool
    {
        // Admin always has access
        if ($user->hasSystemRole('admin')) {
            return true;
        }

        // Check the cache table
        return DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->where('entity_id', $entity->id)
            ->where('entity_type', $entity->getMorphClass())
            ->where('has_access', true)
            ->exists();
    }

    /**
     * Apply user group restrictions to an entity query.
     * This filters the query to only show entities accessible via user groups.
     *
     * @param Builder<Entity> $query
     * @param string          $action Currently only 'view' is supported
     * @return Builder<Entity>
     */
    public function restrictEntityQuery(Builder $query, string $action = 'view'): Builder
    {
        $user = user();

        // Admin can see everything
        if (!$user || $user->hasSystemRole('admin')) {
            return $query;
        }

        // Determine entity type from the query model
        $model = $query->getModel();
        $entityType = $model->getMorphClass();

        // Join with the cache table to filter accessible entities
        $query->whereIn('id', function ($subQuery) use ($user, $entityType) {
            $subQuery->select('entity_id')
                ->from('user_group_joint_permissions')
                ->where('user_id', $user->id)
                ->where('entity_type', $entityType)
                ->where('has_access', true);
        });

        return $query;
    }

    /**
     * Get all shelves accessible to a user via their user groups.
     */
    public function getAccessibleShelves(User $user): Collection
    {
        if ($user->hasSystemRole('admin')) {
            return \BookStack\Entities\Models\Bookshelf::query()->get();
        }

        $shelfIds = DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->where('entity_type', 'bookshelf')
            ->where('has_access', true)
            ->pluck('entity_id');

        return \BookStack\Entities\Models\Bookshelf::query()
            ->whereIn('id', $shelfIds)
            ->get();
    }

    /**
     * Get all books accessible to a user via their user groups.
     */
    public function getAccessibleBooks(User $user): Collection
    {
        if ($user->hasSystemRole('admin')) {
            return \BookStack\Entities\Models\Book::query()->get();
        }

        $bookIds = DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->where('entity_type', 'book')
            ->where('has_access', true)
            ->pluck('entity_id');

        return \BookStack\Entities\Models\Book::query()
            ->whereIn('id', $bookIds)
            ->get();
    }

    /**
     * Get all chapters accessible to a user via their user groups.
     */
    public function getAccessibleChapters(User $user): Collection
    {
        if ($user->hasSystemRole('admin')) {
            return \BookStack\Entities\Models\Chapter::query()->get();
        }

        $chapterIds = DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->where('entity_type', 'chapter')
            ->where('has_access', true)
            ->pluck('entity_id');

        return \BookStack\Entities\Models\Chapter::query()
            ->whereIn('id', $chapterIds)
            ->get();
    }

    /**
     * Get all pages accessible to a user via their user groups.
     */
    public function getAccessiblePages(User $user): Collection
    {
        if ($user->hasSystemRole('admin')) {
            return \BookStack\Entities\Models\Page::query()->get();
        }

        $pageIds = DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->where('entity_type', 'page')
            ->where('has_access', true)
            ->pluck('entity_id');

        return \BookStack\Entities\Models\Page::query()
            ->whereIn('id', $pageIds)
            ->get();
    }

    /**
     * Get all entity IDs of a specific type accessible to a user via their user groups.
     *
     * @return array<int>
     */
    public function getAccessibleEntityIds(User $user, string $entityType): array
    {
        if ($user->hasSystemRole('admin')) {
            return [];  // Empty array means no restriction for admin
        }

        return DB::table('user_group_joint_permissions')
            ->where('user_id', $user->id)
            ->where('entity_type', $entityType)
            ->where('has_access', true)
            ->pluck('entity_id')
            ->toArray();
    }

    /**
     * Check if user group restrictions are enabled for the system.
     * This can be controlled via a setting in the future.
     */
    public function isEnabled(): bool
    {
        // For now, always enabled. Can add a setting later:
        // return setting('user-groups-enabled', true);
        return true;
    }
}

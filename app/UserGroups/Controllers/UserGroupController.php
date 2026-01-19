<?php

namespace BookStack\UserGroups\Controllers;

use BookStack\Http\Controller;
use BookStack\UserGroups\Models\UserGroup;
use BookStack\UserGroups\UserGroupPermissionBuilder;
use BookStack\UserGroups\UserGroupRepository;
use BookStack\Users\Models\Role;
use BookStack\Util\SimpleListOptions;
use Illuminate\Http\Request;

class UserGroupController extends Controller
{
    public function __construct(
        protected UserGroupRepository $repository,
        protected UserGroupPermissionBuilder $permissionBuilder
    ) {
    }

    /**
     * Show a listing of all user groups.
     */
    public function index(Request $request)
    {
        $this->checkPermission('settings-manage');

        $listOptions = SimpleListOptions::fromRequest($request, 'user-groups')->withSortOptions([
            'name' => trans('common.sort_name'),
            'users_count' => trans('settings.user_group_members'),
            'content_count' => trans('settings.user_group_content'),
            'created_at' => trans('common.sort_created_at'),
            'updated_at' => trans('common.sort_updated_at'),
        ]);

        $userGroups = $this->repository->getAllPaginated(20, $listOptions);
        $userGroups->appends($listOptions->getPaginationAppends());

        $this->setPageTitle(trans('settings.user_groups'));

        return view('user-groups.index', [
            'userGroups' => $userGroups,
            'listOptions' => $listOptions,
        ]);
    }

    /**
     * Show the form for creating a new user group.
     */
    public function create()
    {
        $this->checkPermission('settings-manage');

        $this->setPageTitle(trans('settings.user_group_create'));

        return view('user-groups.create');
    }

    /**
     * Store a newly created user group.
     */
    public function store(Request $request)
    {
        $this->checkPermission('settings-manage');

        $data = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $group = $this->repository->create($data);

        $this->logActivity('user_group_create', $group);

        return redirect('/settings/user-groups/' . $group->id);
    }

    /**
     * Show the form for editing the specified user group.
     */
    public function edit(int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);
        $availableUsers = $this->repository->getAvailableUsers($group);
        $availableShelves = $this->repository->getAvailableShelves($group);
        $availableBooks = $this->repository->getAvailableBooks($group);
        $allRoles = Role::query()->orderBy('display_name')->get();

        $this->setPageTitle(trans('settings.user_group_edit'));

        return view('user-groups.edit', [
            'group' => $group,
            'availableUsers' => $availableUsers,
            'availableShelves' => $availableShelves,
            'availableBooks' => $availableBooks,
            'allRoles' => $allRoles,
        ]);
    }

    /**
     * Update the specified user group.
     */
    public function update(Request $request, int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        $data = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $this->repository->update($group, $data);

        $this->logActivity('user_group_update', $group);

        return redirect('/settings/user-groups/' . $group->id);
    }

    /**
     * Remove the specified user group.
     */
    public function destroy(int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        $this->logActivity('user_group_delete', $group);

        $this->repository->delete($group);

        return redirect('/settings/user-groups');
    }

    /**
     * Show the delete confirmation page.
     */
    public function showDelete(int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        $this->setPageTitle(trans('settings.user_group_delete'));

        return view('user-groups.delete', [
            'group' => $group,
        ]);
    }

    /**
     * Add a user or multiple users to the group (AJAX endpoint).
     */
    public function addUser(Request $request, int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        // Support both single user_id and array of user_ids
        $data = $this->validate($request, [
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $userIds = [];
        if (isset($data['user_id'])) {
            $userIds = [$data['user_id']];
        } elseif (isset($data['user_ids'])) {
            $userIds = $data['user_ids'];
        }

        if (empty($userIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No users provided',
            ], 400);
        }

        $users = \BookStack\Users\Models\User::whereIn('id', $userIds)->get();
        
        foreach ($users as $user) {
            $group->addUser($user);
            // Rebuild permission cache for this user
            $this->permissionBuilder->rebuildForUser($user);
        }

        $count = count($users);
        $message = $count === 1 ? 'User added to group' : "{$count} users added to group";

        return response()->json([
            'success' => true,
            'message' => $message,
            'count' => $count,
        ]);
    }

    /**
     * Remove a user or multiple users from the group (AJAX endpoint).
     */
    public function removeUser(Request $request, int $groupId, int $userId = null)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($groupId);

        // Support both single user removal via URL parameter and batch removal via request body
        $userIds = [];
        
        if ($userId !== null) {
            // Single user removal from URL parameter
            $userIds = [$userId];
        } else {
            // Batch removal from request body
            $data = $this->validate($request, [
                'user_ids' => ['required', 'array'],
                'user_ids.*' => ['integer', 'exists:users,id'],
            ]);
            $userIds = $data['user_ids'];
        }

        $users = \BookStack\Users\Models\User::whereIn('id', $userIds)->get();

        foreach ($users as $user) {
            $group->removeUser($user);
            // Rebuild permission cache for this user
            $this->permissionBuilder->rebuildForUser($user);
        }

        $count = count($users);
        $message = $count === 1 ? 'User removed from group' : "{$count} users removed from group";

        return response()->json([
            'success' => true,
            'message' => $message,
            'count' => $count,
        ]);
    }

    /**
     * Update the order of users within the group (AJAX endpoint).
     */
    public function updateUserOrder(Request $request, int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        $data = $this->validate($request, [
            'user_ids' => ['required', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
        ]);

        $this->repository->updateUsers($group, $data['user_ids']);

        return response()->json([
            'success' => true,
            'message' => 'User order updated',
        ]);
    }

    /**
     * Update the sort order of groups (AJAX endpoint).
     */
    public function updateGroupOrder(Request $request)
    {
        $this->checkPermission('settings-manage');

        $data = $this->validate($request, [
            'group_ids' => ['required', 'array'],
            'group_ids.*' => ['integer', 'exists:user_groups,id'],
        ]);

        $this->repository->updateSortOrder($data['group_ids']);

        return response()->json([
            'success' => true,
            'message' => 'Group order updated',
        ]);
    }

    /**
     * Add content (shelf or book) or multiple items to the group (AJAX endpoint).
     */
    public function addContent(Request $request, int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        // Support both single entity and array of entities
        $data = $this->validate($request, [
            'entity_id' => ['nullable', 'integer'],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer'],
            'entity_type' => ['required', 'string', 'in:bookshelf,book'],
        ]);

        $entityIds = [];
        if (isset($data['entity_id'])) {
            $entityIds = [$data['entity_id']];
        } elseif (isset($data['entity_ids'])) {
            $entityIds = $data['entity_ids'];
        }

        if (empty($entityIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No content provided',
            ], 400);
        }

        $entityClass = $data['entity_type'] === 'bookshelf' 
            ? \BookStack\Entities\Models\Bookshelf::class 
            : \BookStack\Entities\Models\Book::class;

        $entities = $entityClass::whereIn('id', $entityIds)->get();
        
        foreach ($entities as $entity) {
            $group->addContent($entity);
        }

        // Rebuild permission cache for this group
        $this->permissionBuilder->rebuildForGroup($group);

        $count = count($entities);
        $typeLabel = $data['entity_type'] === 'bookshelf' ? 'shelf' : 'book';
        $typeLabelPlural = $data['entity_type'] === 'bookshelf' ? 'shelves' : 'books';
        $message = $count === 1 
            ? ucfirst($typeLabel) . ' added to group' 
            : "{$count} {$typeLabelPlural} added to group";

        return response()->json([
            'success' => true,
            'message' => $message,
            'count' => $count,
        ]);
    }

    /**
     * Remove content or multiple content items from the group (AJAX endpoint).
     */
    public function removeContent(Request $request, int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        // Support both single entity and array of entities
        $data = $this->validate($request, [
            'entity_id' => ['nullable', 'integer'],
            'entity_ids' => ['nullable', 'array'],
            'entity_ids.*' => ['integer'],
            'entity_type' => ['required', 'string', 'in:bookshelf,book'],
        ]);

        $entityIds = [];
        if (isset($data['entity_id'])) {
            $entityIds = [$data['entity_id']];
        } elseif (isset($data['entity_ids'])) {
            $entityIds = $data['entity_ids'];
        }

        if (empty($entityIds)) {
            return response()->json([
                'success' => false,
                'message' => 'No content provided',
            ], 400);
        }

        $entityClass = $data['entity_type'] === 'bookshelf' 
            ? \BookStack\Entities\Models\Bookshelf::class 
            : \BookStack\Entities\Models\Book::class;

        $entities = $entityClass::whereIn('id', $entityIds)->get();

        foreach ($entities as $entity) {
            $group->removeContent($entity);
        }

        // Rebuild permission cache for this group
        $this->permissionBuilder->rebuildForGroup($group);

        $count = count($entities);
        $typeLabel = $data['entity_type'] === 'bookshelf' ? 'shelf' : 'book';
        $typeLabelPlural = $data['entity_type'] === 'bookshelf' ? 'shelves' : 'books';
        $message = $count === 1 
            ? ucfirst($typeLabel) . ' removed from group' 
            : "{$count} {$typeLabelPlural} removed from group";

        return response()->json([
            'success' => true,
            'message' => $message,
            'count' => $count,
        ]);
    }

    /**
     * Update all users and content for a group at once.
     */
    public function updateMembership(Request $request, int $id)
    {
        $this->checkPermission('settings-manage');

        $group = $this->repository->getById($id);

        $data = $this->validate($request, [
            'user_ids' => ['nullable', 'array'],
            'user_ids.*' => ['integer', 'exists:users,id'],
            'shelf_ids' => ['nullable', 'array'],
            'shelf_ids.*' => ['integer', 'exists:entities,id'],
            'book_ids' => ['nullable', 'array'],
            'book_ids.*' => ['integer', 'exists:entities,id'],
        ]);

        // Update users
        if (isset($data['user_ids'])) {
            $this->repository->updateUsers($group, $data['user_ids']);
        }

        // Update content
        $this->repository->updateContent($group, [
            'shelves' => $data['shelf_ids'] ?? [],
            'books' => $data['book_ids'] ?? [],
        ]);

        // Rebuild permission cache for this group
        $this->permissionBuilder->rebuildForGroup($group);

        $this->logActivity('user_group_update', $group);

        return redirect('/settings/user-groups/' . $group->id);
    }
}

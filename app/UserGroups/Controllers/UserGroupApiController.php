<?php

namespace BookStack\UserGroups\Controllers;

use BookStack\Http\ApiController;
use BookStack\UserGroups\UserGroupRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserGroupApiController extends ApiController
{
    public function __construct(
        protected UserGroupRepository $repository
    ) {
    }

    /**
     * Get a listing of all user groups.
     */
    public function index(): JsonResponse
    {
        $groups = $this->repository->getAll();

        return response()->json([
            'data' => $groups->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'description' => $group->description,
                    'color' => $group->color,
                    'users_count' => $group->getUsersCount(),
                    'content_count' => $group->getContentItemsCount(),
                    'created_at' => $group->created_at,
                    'updated_at' => $group->updated_at,
                ];
            }),
        ]);
    }

    /**
     * Get a single user group by ID.
     */
    public function show(int $id): JsonResponse
    {
        $group = $this->repository->getById($id);

        return response()->json([
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'color' => $group->color,
                'users' => $group->users->map(fn($u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'email' => $u->email,
                ]),
                'content' => $group->contentItems->map(fn($c) => [
                    'entity_id' => $c->entity_id,
                    'entity_type' => $c->entity_type,
                ]),
                'created_at' => $group->created_at,
                'updated_at' => $group->updated_at,
            ],
        ]);
    }

    /**
     * Create a new user group.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $this->validate($request, [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $group = $this->repository->create($data);

        return response()->json([
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'color' => $group->color,
                'created_at' => $group->created_at,
            ],
        ], 201);
    }

    /**
     * Update an existing user group.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $group = $this->repository->getById($id);

        $data = $this->validate($request, [
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'color' => ['nullable', 'string', 'max:7'],
        ]);

        $this->repository->update($group, $data);

        return response()->json([
            'data' => [
                'id' => $group->id,
                'name' => $group->name,
                'description' => $group->description,
                'color' => $group->color,
                'updated_at' => $group->updated_at,
            ],
        ]);
    }

    /**
     * Delete a user group.
     */
    public function destroy(int $id): JsonResponse
    {
        $group = $this->repository->getById($id);
        $this->repository->delete($group);

        return response()->json(null, 204);
    }
}

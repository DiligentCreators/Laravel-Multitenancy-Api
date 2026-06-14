<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Role\StoreRoleRequest;
use App\Http\Requests\Central\Api\V1\Role\UpdateRoleRequest;
use App\Http\Resources\Central\Api\V1\Role\ListRoleResource;
use App\Http\Resources\Central\Api\V1\Role\RoleResource;
use App\Models\Central\Role;
use App\Services\ApiResponseService;
use App\Services\Central\RoleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class RoleController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly RoleService $roleService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Role::class);

        $roles = $this->roleService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'roles retrieved successfully',
            ListRoleResource::collection($roles),
        );
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        Gate::authorize('create', Role::class);

        $role = $this->roleService->create($request->validated());

        return $this->api->success(
            'Role has been created successfully',
            new RoleResource($role),
            201,
        );
    }

    public function show(Role $role): JsonResponse
    {
        Gate::authorize('view', $role);

        $role = $this->roleService->getRolePermissions($role);
        $allPermissions = $this->roleService->getAllPermissionsWithAssignment($role);

        return $this->api->success(
            'Role retrieved successfully',
            new RoleResource($role, $allPermissions),
        );
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        Gate::authorize('update', $role);

        if (in_array($role->name, RoleService::protectedRoles())) {
            return $this->api->error('This role is protected and cannot be modified.', 403);
        }

        $this->roleService->update($role, $request->safe()->except('permissions'));

        if ($request->filled('permissions')) {
            Gate::authorize('update', $role);

            $this->roleService->syncRolePermission($role, $request->input('permissions'));
        }

        return $this->api->success(
            'Role has been updated successfully',
            new RoleResource($role->load('permissions')),
        );
    }
}

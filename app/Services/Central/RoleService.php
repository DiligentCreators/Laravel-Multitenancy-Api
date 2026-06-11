<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\RoleScopeEnum;
use App\Models\Central\Permission;
use App\Models\Central\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoleService
{
    public function __construct(
        protected Role $role,
    ) {}

    public function query(Request $request): Builder
    {
        return $this->role
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query->where('id', 'like', "%{$search}%");
                });
            })
            ->where('scope', RoleScopeEnum::CENTRAL)
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Role
    {
        return $this->role
            ->query()
            ->findOrFail($id);
    }

    public function create(array $data): Role
    {
        return $this->role->newQuery()->create($data);
    }

    public function update(Role $role, array $data): Role
    {
        $role->update($data);

        return $role;
    }

    public function getRolePermissions(Role $role): Role
    {
        return $role->load(['permissions' => function ($query) {
            $query->where('scope', RoleScopeEnum::CENTRAL);
        }]);
    }

    public function getAllPermissionsWithAssignment(Role $role): Collection
    {
        $rolePermissionIds = $role->permissions->pluck('id');

        return Permission::where('scope', RoleScopeEnum::CENTRAL)
            ->get()
            ->map(function (Permission $permission) use ($rolePermissionIds) {
                $permission->is_assigned = $rolePermissionIds->contains($permission->id);

                return $permission;
            });
    }

    public function syncRolePermission(Role $role, array $permissions): void
    {
        $role->permissions()->sync($permissions);
    }
}

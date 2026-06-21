<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Central\Permission;
use App\Models\Central\Role;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoleService
{
    private const ALLOWED_SORT_COLUMNS = [
        'id', 'name', 'guard_name', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function __construct(
        protected Role $role,
    ) {}

    public static function protectedRoles(): array
    {
        return config('central-protected-roles.protected', []);
    }

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->role
            ->query()
            ->whereNull('tenant_id')
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Role::search($search)->keys();
                $query->whereIn((new Role)->getQualifiedKeyName(), $ids);
            })
            ->whereNotIn('name', self::protectedRoles())
            ->orderBy($sort, $direction);
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
        return $role->load('permissions');
    }

    public function getAllPermissionsWithAssignment(Role $role): Collection
    {
        $rolePermissionIds = $role->permissions->pluck('id');

        return Permission::where('guard_name', 'central-api')
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

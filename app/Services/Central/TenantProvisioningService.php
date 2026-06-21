<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Permission;
use App\Models\Tenant;
use App\Models\Tenant\Role;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class TenantProvisioningService
{
    public function provision(Tenant $tenant, array $userData): User
    {
        tenancy()->initialize($tenant);

        $user = DB::transaction(function () use ($tenant, $userData) {
            $this->createPermissions();
            $this->createRoles($tenant->id);
            $this->syncRolePermissions();

            $user = User::create($userData);

            $this->assignSuperadminRole($user, $tenant);

            return $user;
        });

        tenancy()->end();

        return $user;
    }

    private function createPermissions(): void
    {
        $permissions = config('tenant-permissions');

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'tenant-api',
                ]);
            }
        }
    }

    private function createRoles(string $tenantId): void
    {
        $roles = [
            ['name' => 'superadmin', 'guard_name' => 'tenant-api', 'tenant_id' => $tenantId],
            ['name' => 'admin', 'guard_name' => 'tenant-api', 'tenant_id' => $tenantId],
            ['name' => 'manager', 'guard_name' => 'tenant-api', 'tenant_id' => $tenantId],
            ['name' => 'staff', 'guard_name' => 'tenant-api', 'tenant_id' => $tenantId],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
    }

    private function syncRolePermissions(): void
    {
        $tenantPermissions = Permission::where('guard_name', 'tenant-api')->get();

        $rolePermissions = [
            'superadmin' => $tenantPermissions,
            'admin' => $tenantPermissions,
            'manager' => $tenantPermissions->whereNotIn('name', $this->getExcludedPermissions(['create', 'delete'])),
            'staff' => $tenantPermissions->whereNotIn('name', $this->getExcludedPermissions(['create', 'update', 'delete'])),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)
                ->where('guard_name', 'tenant-api')
                ->first();
            if ($role) {
                $role->syncPermissions($permissions);
            }
        }
    }

    private function getExcludedPermissions(array $excludedParts): Collection
    {
        return Permission::where('guard_name', 'tenant-api')
            ->where(function ($q) use ($excludedParts) {
                foreach ($excludedParts as $part) {
                    $q->where('name', 'like', "%.{$part}");
                }
            })
            ->pluck('name');
    }

    private function assignSuperadminRole(User $user, Tenant $tenant): void
    {
        $superadmin = Role::where('name', 'superadmin')
            ->where('guard_name', 'tenant-api')
            ->where('tenant_id', $tenant->id)
            ->first();

        if ($superadmin) {
            $user->roles()->attach($superadmin->id);
        }
    }
}

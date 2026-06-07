<?php

namespace Database\Seeders\Central;

use App\Models\Central\Permission;
use App\Models\Central\Role;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class RolePermissionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Define role-based permissions
        $rolePermissions = [
            'superadmin' => Permission::all(),
            'tester' => Permission::all(),
            'developer' => Permission::all(),
            'admin' => Permission::all(),
            'manager' => Permission::whereNotIn('name', $this->getExcludedPermissions(['delete', 'restore', 'force.delete']))->get(),
            'staff' => Permission::whereNotIn('name', $this->getExcludedPermissions(['create', 'update', 'delete', 'restore', 'force.delete']))->get(),
        ];

        foreach ($rolePermissions as $roleName => $permissions) {
            $role = Role::where('name', $roleName)->first();
            if ($role) {
                $role->syncPermissions($permissions);
            }
        }
    }

    /**
     * Return excluded permissions for roles based on wildcards
     *
     * @return Collection<int, string>
     */
    protected function getExcludedPermissions(array $excludedParts): Collection
    {
        return Permission::where(function ($query) use ($excludedParts) {
            foreach ($excludedParts as $part) {
                $query->orWhere('name', 'like', "%{$part}%");
            }
        })->pluck('name');
    }
}

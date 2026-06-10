<?php

namespace Database\Seeders\Tenant;

use App\Enums\RoleScopeEnum;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = 'tenant-api';
        $scope = RoleScopeEnum::TENANT;
        $roles = [
            ['name' => 'superadmin', 'guard_name' => $guardName, 'scope' => $scope],
            ['name' => 'admin', 'guard_name' => $guardName, 'scope' => $scope],
            ['name' => 'manager', 'guard_name' => $guardName, 'scope' => $scope],
            ['name' => 'staff', 'guard_name' => $guardName, 'scope' => $scope],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
    }
}

<?php

namespace Database\Seeders\Tenant;

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
        $roles = [
            ['name' => 'superadmin', 'guard_name' => $guardName],
            ['name' => 'admin', 'guard_name' => $guardName],
            ['name' => 'manager', 'guard_name' => $guardName],
            ['name' => 'staff', 'guard_name' => $guardName],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
    }
}

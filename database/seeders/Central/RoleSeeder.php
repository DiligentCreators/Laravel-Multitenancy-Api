<?php

namespace Database\Seeders\Central;

use App\Models\Central\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $guardName = 'central-api';
        $roles = [
            ['name' => 'superadmin', 'guard_name' => $guardName],
            ['name' => 'tester', 'guard_name' => $guardName],
            ['name' => 'developer', 'guard_name' => $guardName],
            ['name' => 'admin', 'guard_name' => $guardName],
            ['name' => 'manager', 'guard_name' => $guardName],
            ['name' => 'staff', 'guard_name' => $guardName],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate($role);
        }
    }
}

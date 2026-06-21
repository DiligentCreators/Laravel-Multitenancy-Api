<?php

namespace Database\Seeders\Central;

use Illuminate\Database\Seeder;

class CentralDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            CentralUserSeeder::class,

            // Create Permissions
            PermissionsSeeder::class,

            // Create Roles
            RoleSeeder::class,

            // Assign Role to User
            AssignRoleToUserSeeder::class,

            // Sync Roles & Permissions
            RolePermissionSeeder::class,
        ]);
    }
}

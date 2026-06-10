<?php

namespace Database\Seeders\Tenant;

use Illuminate\Database\Seeder;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->call([
            // UserSeeder::class,

            // Create Permissions
            PermissionsSeeder::class,

            // Create Roles
            RoleSeeder::class,

            // Assign Role to User
            // AssignRoleToUserSeeder::class,

            // Sync Roles & Permissions
            // RolePermissionSeeder::class,
        ]);
    }
}

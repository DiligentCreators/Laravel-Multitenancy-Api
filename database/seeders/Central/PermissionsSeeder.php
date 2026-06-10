<?php

namespace Database\Seeders\Central;

use App\Enums\RoleScopeEnum;
use App\Models\Central\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Disable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Permission::where('guard_name', 'central-api')->delete();

        // Re-enable foreign key checks
        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        // Fetch permissions from the config and seed
        $permissions = config('central-permissions');

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'central-api',
                    'scope' => RoleScopeEnum::CENTRAL,
                ]);
            }
        }
    }
}

<?php

declare(strict_types=1);

namespace Database\Seeders\Tenant;

use App\Enums\RoleScopeEnum;
use App\Models\Permission;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        Permission::where('guard_name', 'tenant-api')->delete();

        DB::statement('SET FOREIGN_KEY_CHECKS=1;');

        $permissions = config('tenant-permissions');

        foreach ($permissions as $module => $actions) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$module}.{$action}",
                    'guard_name' => 'tenant-api',
                    'scope' => RoleScopeEnum::TENANT,
                ]);
            }
        }
    }
}

<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasScopeColumnOnPermissions = Schema::hasColumn('permissions', 'scope');

        if (! $hasScopeColumnOnPermissions) {
            return;
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->dropForeign(['tenant_id']);
            $table->dropColumn(['scope', 'tenant_id']);
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_name_guard_name_unique');
            $table->dropColumn('scope');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->unique(['tenant_id', 'name', 'guard_name'], 'roles_tenant_id_name_guard_name_unique');
        });
    }

    public function down(): void
    {
        $hasScopeColumnOnPermissions = Schema::hasColumn('permissions', 'scope');

        if ($hasScopeColumnOnPermissions) {
            return;
        }

        Schema::table('permissions', function (Blueprint $table) {
            $table->string('scope')->nullable()->after('guard_name');
            $table->string('tenant_id')->nullable()->after('scope');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->dropUnique('roles_tenant_id_name_guard_name_unique');
        });

        Schema::table('roles', function (Blueprint $table) {
            $table->string('scope')->nullable()->after('guard_name');
            $table->unique(['name', 'guard_name'], 'roles_name_guard_name_unique');
        });
    }
};

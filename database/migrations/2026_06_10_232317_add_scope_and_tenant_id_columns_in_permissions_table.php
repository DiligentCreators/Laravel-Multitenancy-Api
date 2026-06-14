<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Superseded by 2026_06_14_001535_refactor_permissions_and_roles_schema.
 *
 * The scope and tenant_id columns were removed from permissions since
 * permissions are global and shared across all tenants.
 */
return new class extends Migration
{
    public function up(): void
    {
        // No-op: schema handled by the refactoring migration.
    }

    public function down(): void
    {
        // No-op: see refactoring migration for reversal.
    }
};

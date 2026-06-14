<?php

use Illuminate\Database\Migrations\Migration;

/**
 * Superseded by 2026_06_14_001535_refactor_permissions_and_roles_schema.
 *
 * The tenant_id column was originally added here but is now part of the
 * base Spatie migration. The scope column was removed entirely.
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

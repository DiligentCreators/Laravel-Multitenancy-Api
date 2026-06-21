<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->dropForeign(['central_user_id']);
            $table->foreignId('central_user_id')->nullable()->change();
        });

        Schema::table('tenant_export_records', function (Blueprint $table) {
            $table->dropForeign(['central_user_id']);
            $table->foreignId('central_user_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('admin_audit_logs', function (Blueprint $table) {
            $table->foreign('central_user_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('tenant_export_records', function (Blueprint $table) {
            $table->foreign('central_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }
};

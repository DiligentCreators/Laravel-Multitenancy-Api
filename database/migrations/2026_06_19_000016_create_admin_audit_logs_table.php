<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('admin_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('central_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('action'); // login, logout, impersonate_start, impersonate_stop, config_change, permission_change, system_setting_change, data_export, data_delete
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent')->nullable();
            $table->json('context')->nullable();
            $table->timestamps();

            $table->index(['central_user_id', 'created_at']);
            $table->index('action');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('admin_audit_logs');
    }
};

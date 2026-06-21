<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenant_export_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 255);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('central_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type'); // full, settings, users, activity
            $table->string('format', 10); // json, csv, xlsx
            $table->string('file_path')->nullable();
            $table->string('file_size')->nullable();
            $table->string('status')->default('pending'); // pending, processing, completed, failed
            $table->text('error_message')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_export_records');
    }
};

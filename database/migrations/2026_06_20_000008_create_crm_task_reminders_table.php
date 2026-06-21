<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_task_reminders', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('task_id')->constrained('crm_tasks')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('remind_at');
            $table->timestamp('notified_at')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'task_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_task_reminders');
    }
};

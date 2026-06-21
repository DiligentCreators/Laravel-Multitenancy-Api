<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_notes', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->morphs('noteable');
            $table->longText('content');
            $table->boolean('is_pinned')->default(false);
            $table->timestamps();
            $table->softDeletes();
            $table->index(['tenant_id', 'noteable_type', 'noteable_id']);
            $table->index(['tenant_id', 'is_pinned']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_notes');
    }
};

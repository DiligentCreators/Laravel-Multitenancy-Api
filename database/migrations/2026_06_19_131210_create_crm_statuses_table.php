<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_statuses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreignId('type_id')->constrained('crm_status_types')->cascadeOnDelete();
            $table->string('name');
            $table->string('key');
            $table->string('color', 7)->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'type_id', 'key']);
            $table->index('type_id');
            $table->index('order');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_statuses');
    }
};

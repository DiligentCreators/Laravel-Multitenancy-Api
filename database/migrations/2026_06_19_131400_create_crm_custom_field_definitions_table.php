<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_custom_field_definitions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('entity_type'); // person, organization, lead, task, document
            $table->string('name');
            $table->string('key');
            $table->string('type'); // text, textarea, number, decimal, date, datetime, checkbox, select, multiselect, email, phone, url, json
            $table->json('options')->nullable(); // for select/multiselect
            $table->boolean('is_required')->default(false);
            $table->boolean('is_unique')->default(false);
            $table->json('validation_rules')->nullable();
            $table->json('default_value')->nullable();
            $table->unsignedInteger('order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['tenant_id', 'entity_type', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_custom_field_definitions');
    }
};

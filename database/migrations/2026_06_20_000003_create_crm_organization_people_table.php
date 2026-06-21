<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_organization_people', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained('crm_organizations')->cascadeOnDelete();
            $table->foreignId('person_id')->constrained('crm_people')->cascadeOnDelete();
            $table->string('role')->nullable(); // employee, contractor, manager, director, ceo, etc.
            $table->boolean('is_primary')->default(false);
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'person_id', 'end_date'], 'org_person_unique_active');
            $table->index(['organization_id']);
            $table->index(['person_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_organization_people');
    }
};

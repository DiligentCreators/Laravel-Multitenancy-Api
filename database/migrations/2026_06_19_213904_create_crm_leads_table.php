<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_leads', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('crm_people')->nullOnDelete();
            $table->foreignId('organization_id')->nullable()->constrained('crm_organizations')->nullOnDelete();
            $table->foreignId('source_id')->nullable()->constrained('crm_sources')->nullOnDelete();
            $table->foreignId('status_id')->nullable()->constrained('crm_statuses')->nullOnDelete();
            $table->foreignId('pipeline_id')->nullable()->constrained('crm_pipelines')->nullOnDelete();
            $table->foreignId('pipeline_stage_id')->nullable()->constrained('crm_pipeline_stages')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->decimal('value', 15, 2)->nullable();
            $table->integer('probability')->nullable();
            $table->date('expected_close_date')->nullable();
            $table->timestamp('won_at')->nullable();
            $table->timestamp('lost_at')->nullable();
            $table->json('custom_fields')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['pipeline_id', 'pipeline_stage_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_leads');
    }
};

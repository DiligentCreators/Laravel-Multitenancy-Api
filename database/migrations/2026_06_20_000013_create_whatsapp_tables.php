<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_whatsapp_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('provider')->default('meta_cloud');
            $table->string('business_account_id');
            $table->string('app_id');
            $table->text('app_secret');
            $table->text('access_token');
            $table->string('webhook_verify_token');
            $table->string('status')->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
        });

        Schema::create('crm_whatsapp_phone_numbers', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('whatsapp_account_id')->constrained('crm_whatsapp_accounts')->cascadeOnDelete();
            $table->string('phone_number_id');
            $table->string('display_phone_number');
            $table->string('verified_name');
            $table->string('quality_rating')->nullable();
            $table->string('status');
            $table->json('metadata')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'whatsapp_account_id']);
        });

        Schema::create('crm_whatsapp_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('conversation_id')->nullable()->constrained('crm_conversations')->nullOnDelete();
            $table->foreignId('person_id')->nullable()->constrained('crm_people')->nullOnDelete();
            $table->foreignId('whatsapp_phone_number_id')->constrained('crm_whatsapp_phone_numbers');
            $table->string('provider_message_id');
            $table->string('direction');
            $table->string('type');
            $table->string('from_number');
            $table->string('to_number');
            $table->text('content')->nullable();
            $table->string('media_url')->nullable();
            $table->string('status');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['tenant_id', 'person_id']);
            $table->index(['tenant_id', 'direction']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'provider_message_id']);
        });

        Schema::create('crm_whatsapp_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('whatsapp_account_id')->nullable()->constrained('crm_whatsapp_accounts')->nullOnDelete();
            $table->string('event_type');
            $table->json('payload');
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_whatsapp_webhook_logs');
        Schema::dropIfExists('crm_whatsapp_messages');
        Schema::dropIfExists('crm_whatsapp_phone_numbers');
        Schema::dropIfExists('crm_whatsapp_accounts');
    }
};

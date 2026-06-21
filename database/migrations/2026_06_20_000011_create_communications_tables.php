<?php

use App\Enums\ConversationStatusEnum;
use App\Enums\MessageStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_conversations', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->uuid()->unique();
            $table->string('subject')->nullable();
            $table->string('channel');
            $table->string('status')->default(ConversationStatusEnum::OPEN->value);
            $table->timestamp('last_message_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'last_message_at']);
        });

        Schema::create('crm_conversation_participants', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('conversation_id')->constrained('crm_conversations')->cascadeOnDelete();
            $table->string('participant_type');
            $table->unsignedBigInteger('participant_id');
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['participant_type', 'participant_id'], 'crm_cpart_type_id_idx');
            $table->unique(['conversation_id', 'participant_type', 'participant_id'], 'crm_conv_part_unique');
        });

        Schema::create('crm_messages', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('conversation_id')->constrained('crm_conversations')->cascadeOnDelete();
            $table->string('sender_type');
            $table->unsignedBigInteger('sender_id');
            $table->string('direction');
            $table->text('body')->nullable();
            $table->string('status')->default(MessageStatusEnum::DRAFT->value);
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->json('metadata')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'conversation_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'direction']);
            $table->index(['tenant_id', 'sent_at']);
        });

        Schema::create('crm_message_templates', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->string('channel');
            $table->string('category')->nullable();
            $table->text('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'channel']);
            $table->index(['tenant_id', 'is_active']);
        });

        Schema::create('crm_message_attachments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('message_id')->constrained('crm_messages')->cascadeOnDelete();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'message_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_message_attachments');
        Schema::dropIfExists('crm_message_templates');
        Schema::dropIfExists('crm_messages');
        Schema::dropIfExists('crm_conversation_participants');
        Schema::dropIfExists('crm_conversations');
    }
};

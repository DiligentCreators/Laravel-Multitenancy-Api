<?php

use App\Enums\DocumentStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_document_folders', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('name');
            $table->foreignId('parent_id')->nullable()->constrained('crm_document_folders')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->integer('sort_order')->default(0);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'parent_id']);
            $table->index(['tenant_id', 'sort_order']);
        });

        Schema::create('crm_documents', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('folder_id')->nullable()->constrained('crm_document_folders')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->string('extension')->nullable();
            $table->string('version')->default('1.0');
            $table->string('status')->default(DocumentStatusEnum::DRAFT->value);
            $table->boolean('is_locked')->default(false);
            $table->timestamp('expires_at')->nullable();
            $table->string('documentable_type')->nullable();
            $table->unsignedBigInteger('documentable_id')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('team_id')->nullable()->index();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'folder_id']);
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'mime_type']);
            $table->index(['documentable_type', 'documentable_id']);
        });

        Schema::create('crm_document_versions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained('crm_documents')->cascadeOnDelete();
            $table->string('version');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('mime_type');
            $table->unsignedBigInteger('file_size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'document_id']);
        });

        Schema::create('crm_document_shares', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('document_id')->constrained('crm_documents')->cascadeOnDelete();
            $table->string('share_token')->unique();
            $table->timestamp('expires_at')->nullable();
            $table->boolean('password_protected')->default(false);
            $table->string('password')->nullable();
            $table->unsignedInteger('access_count')->default(0);
            $table->timestamp('last_accessed_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'document_id']);
            $table->index(['tenant_id', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_document_shares');
        Schema::dropIfExists('crm_document_versions');
        Schema::dropIfExists('crm_documents');
        Schema::dropIfExists('crm_document_folders');
    }
};

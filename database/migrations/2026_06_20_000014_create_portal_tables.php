<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('portal_users', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->string('name');
            $table->string('email');
            $table->string('password');
            $table->boolean('is_active')->default(true);
            $table->rememberToken();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->unique(['tenant_id', 'email']);
        });

        Schema::create('portal_person_links', function (Blueprint $table) {
            $table->id();
            $table->char('tenant_id', 36);
            $table->unsignedBigInteger('portal_user_id');
            $table->unsignedBigInteger('person_id')->nullable();
            $table->unsignedBigInteger('organization_id')->nullable();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->onDelete('cascade');
            $table->foreign('portal_user_id')->references('id')->on('portal_users')->onDelete('cascade');
            $table->foreign('person_id')->references('id')->on('crm_people')->onDelete('set null');
            $table->foreign('organization_id')->references('id')->on('crm_organizations')->onDelete('set null');
        });

        Schema::create('portal_password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('portal_password_reset_tokens');
        Schema::dropIfExists('portal_person_links');
        Schema::dropIfExists('portal_users');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_usage_counters', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('feature_key');
            $table->bigInteger('count')->default(0);
            $table->timestamp('last_reset_at')->nullable();
            $table->timestamps();
            $table->unique(['tenant_id', 'feature_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_usage_counters');
    }
};

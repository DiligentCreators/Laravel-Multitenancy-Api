<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_recurring_event_patterns', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('frequency');
            $table->integer('interval')->default(1);
            $table->date('ends_at')->nullable();
            $table->integer('occurrences_limit')->nullable();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index('tenant_id');
            $table->index(['tenant_id', 'frequency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_recurring_event_patterns');
    }
};

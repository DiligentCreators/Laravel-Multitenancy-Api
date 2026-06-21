<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('usage_counters', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature');
            $table->bigInteger('used')->default(0);
            $table->bigInteger('limit')->default(0);
            $table->string('period')->default('monthly');
            $table->timestamp('reset_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature', 'period']);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('usage_counters');
    }
};

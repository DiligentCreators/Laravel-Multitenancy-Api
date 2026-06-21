<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proration_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->string('tenant_id', 255);
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->string('type'); // upgrade, downgrade, addon, remove_addon, cancel, reactivate
            $table->decimal('credit_amount', 10, 2)->default(0);
            $table->decimal('charge_amount', 10, 2)->default(0);
            $table->decimal('net_amount', 10, 2)->default(0);
            $table->string('currency', 3)->default('USD');
            $table->json('details')->nullable();
            $table->string('status')->default('pending'); // pending, applied, refunded, failed
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proration_records');
    }
};

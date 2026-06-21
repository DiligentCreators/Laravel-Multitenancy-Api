<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overage_charges', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->string('feature');
            $table->integer('quantity');
            $table->decimal('unit_price', 10, 2);
            $table->decimal('amount', 12, 2);
            $table->string('status')->default('pending');
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('overage_charges');
    }
};

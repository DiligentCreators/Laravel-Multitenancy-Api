<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_addresses', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->morphs('addressable');
            $table->string('type')->default('office'); // billing, shipping, office, site, property
            $table->string('country', 2)->nullable();
            $table->string('state')->nullable();
            $table->string('city')->nullable();
            $table->string('postal_code', 20)->nullable();
            $table->string('address_line_1');
            $table->string('address_line_2')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->index(['tenant_id', 'addressable_type', 'addressable_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_addresses');
    }
};

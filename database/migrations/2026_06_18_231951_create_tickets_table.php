<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->string('ticket_number')->unique();
            $table->string('tenant_id');
            $table->string('subject');
            $table->text('description');
            $table->string('priority')->default('low');
            $table->string('status')->default('open');
            $table->foreignId('assigned_to')->nullable()->constrained('central_users')->nullOnDelete();
            $table->softDeletes();
            $table->timestamps();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['tenant_id', 'status']);
            $table->index('assigned_to');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tickets');
    }
};

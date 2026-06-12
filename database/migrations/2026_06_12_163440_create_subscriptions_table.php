<?php

declare(strict_types=1);

use App\Enums\Central\SubscriptionStatusEnum;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();

            $table->string('tenant_id');
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->foreignId('plan_id')
                ->constrained()
                ->cascadeOnDelete();

            $table->dateTime('starts_at');
            $table->dateTime('ends_at')->nullable();

            $table->string('billing_cycle');

            $table->string('status')->default(SubscriptionStatusEnum::TRIAL->value);

            $table->softDeletes();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
    }
};

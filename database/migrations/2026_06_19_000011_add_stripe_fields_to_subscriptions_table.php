<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->unique()->after('plan_id');
            $table->string('stripe_status')->nullable()->after('stripe_id');
            $table->string('stripe_price')->nullable()->after('stripe_status');
            $table->integer('quantity')->nullable()->default(1)->after('stripe_price');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_id',
                'stripe_status',
                'stripe_price',
                'quantity',
            ]);
        });
    }
};

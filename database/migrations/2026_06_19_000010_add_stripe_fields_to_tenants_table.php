<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_id')->nullable()->unique()->after('data');
            $table->string('stripe_customer_email')->nullable()->after('stripe_id');
            $table->string('card_brand')->nullable()->after('stripe_customer_email');
            $table->string('card_last_four', 4)->nullable()->after('card_brand');
            $table->timestamp('trial_ends_at')->nullable()->after('card_last_four');
            $table->string('stripe_account_id')->nullable()->after('trial_ends_at');
            $table->boolean('stripe_account_enabled')->default(false)->after('stripe_account_id');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_id',
                'stripe_customer_email',
                'card_brand',
                'card_last_four',
                'trial_ends_at',
                'stripe_account_id',
                'stripe_account_enabled',
            ]);
        });
    }
};

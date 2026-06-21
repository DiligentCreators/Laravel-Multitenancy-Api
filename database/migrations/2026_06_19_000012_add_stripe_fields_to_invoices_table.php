<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('stripe_invoice_id')->nullable()->unique()->after('subscription_id');
            $table->string('stripe_charge_id')->nullable()->after('stripe_invoice_id');
            $table->string('stripe_payment_intent_id')->nullable()->after('stripe_charge_id');
            $table->string('billing_reason')->nullable()->after('stripe_payment_intent_id');
            $table->string('invoice_pdf_url')->nullable()->after('billing_reason');
            $table->string('hosted_invoice_url')->nullable()->after('invoice_pdf_url');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn([
                'stripe_invoice_id',
                'stripe_charge_id',
                'stripe_payment_intent_id',
                'billing_reason',
                'invoice_pdf_url',
                'hosted_invoice_url',
            ]);
        });
    }
};

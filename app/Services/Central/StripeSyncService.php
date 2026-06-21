<?php

namespace App\Services\Central;

use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;

class StripeSyncService
{
    public function __construct(
        private readonly StripeClient $stripe,
    ) {}

    public function syncCustomer(Tenant $tenant): Tenant
    {
        if ($tenant->stripe_id) {
            $customer = $this->stripe->customers->retrieve($tenant->stripe_id);
        } else {
            $customer = $this->stripe->customers->create([
                'email' => $tenant->email,
                'name' => $tenant->company_name ?? $tenant->name,
                'metadata' => [
                    'tenant_id' => $tenant->id,
                ],
            ]);

            $tenant->stripe_id = $customer->id;
        }

        $tenant->stripe_customer_email = $customer->email;
        $tenant->card_brand = $customer->invoice_settings?->default_payment_method?->card?->brand;
        $tenant->card_last_four = $customer->invoice_settings?->default_payment_method?->card?->last4;
        $tenant->save();

        return $tenant;
    }

    public function syncSubscription(Subscription $subscription): Subscription
    {
        if (! $subscription->stripe_id) {
            return $subscription;
        }

        $stripeSubscription = $this->stripe->subscriptions->retrieve($subscription->stripe_id);

        $subscription->stripe_status = $stripeSubscription->status;
        $subscription->stripe_price = $stripeSubscription->items->first()?->price?->id;
        $subscription->quantity = $stripeSubscription->items->first()?->quantity;
        $subscription->save();

        return $subscription;
    }

    public function syncInvoice(Invoice $invoice): Invoice
    {
        if (! $invoice->stripe_invoice_id) {
            return $invoice;
        }

        return DB::transaction(function () use ($invoice) {
            $stripeInvoice = $this->stripe->invoices->retrieve($invoice->stripe_invoice_id);

            $invoice->stripe_charge_id = $stripeInvoice->charge;
            $invoice->stripe_payment_intent_id = $stripeInvoice->payment_intent;
            $invoice->invoice_pdf_url = $stripeInvoice->invoice_pdf;
            $invoice->hosted_invoice_url = $stripeInvoice->hosted_invoice_url;
            $invoice->status = $stripeInvoice->status;

            if ($stripeInvoice->status === 'paid' && ! $invoice->paid_at) {
                $invoice->paid_at = now();
                $invoice->save();

                Payment::create([
                    'invoice_id' => $invoice->id,
                    'tenant_id' => $invoice->tenant_id,
                    'amount' => $stripeInvoice->total / 100,
                    'currency' => strtoupper($stripeInvoice->currency),
                    'gateway' => 'stripe',
                    'transaction_id' => $stripeInvoice->charge,
                    'status' => 'completed',
                    'paid_at' => now(),
                ]);
            }

            $invoice->save();

            return $invoice;
        });
    }
}

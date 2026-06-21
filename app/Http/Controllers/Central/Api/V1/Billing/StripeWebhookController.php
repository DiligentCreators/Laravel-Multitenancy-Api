<?php

namespace App\Http\Controllers\Central\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Services\Central\StripeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

class StripeWebhookController extends Controller
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripeSyncService $syncService,
    ) {}

    public function handleWebhook(Request $request): JsonResponse
    {
        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        $endpointSecret = config('services.stripe.webhook_secret');

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $endpointSecret);
        } catch (\UnexpectedValueException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid payload'], 400);
        } catch (SignatureVerificationException) {
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 400);
        }

        return match ($event->type) {
            'customer.subscription.created',
            'customer.subscription.updated',
            'customer.subscription.deleted' => $this->handleSubscriptionEvent($event),
            'invoice.paid',
            'invoice.payment_succeeded' => $this->handleInvoicePaid($event),
            'invoice.payment_failed' => $this->handleInvoicePaymentFailed($event),
            'customer.updated' => $this->handleCustomerUpdated($event),
            default => response()->json(['status' => 'success', 'message' => 'Event not handled']),
        };
    }

    protected function handleSubscriptionEvent(Event $event): JsonResponse
    {
        $stripeSubscription = $event->data->object;
        $tenant = Tenant::where('stripe_id', $stripeSubscription->customer)->first();

        if (! $tenant) {
            return response()->json(['status' => 'error', 'message' => 'Tenant not found'], 404);
        }

        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->where('stripe_id', $stripeSubscription->id)
            ->first();

        if (! $subscription) {
            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'stripe_id' => $stripeSubscription->id,
                'stripe_status' => $stripeSubscription->status,
                'stripe_price' => $stripeSubscription->items->first()?->price?->id,
                'quantity' => $stripeSubscription->items->first()?->quantity ?? 1,
                'status' => $this->mapStripeStatus($stripeSubscription->status),
                'starts_at' => now(),
                'trial_ends_at' => $stripeSubscription->trial_end
                    ? now()->timestamp($stripeSubscription->trial_end)
                    : null,
                'ends_at' => $stripeSubscription->current_period_end
                    ? now()->timestamp($stripeSubscription->current_period_end)
                    : null,
            ]);
        }

        $this->syncService->syncSubscription($subscription);

        return response()->json(['status' => 'success', 'message' => 'Subscription synced']);
    }

    protected function handleInvoicePaid(Event $event): JsonResponse
    {
        $stripeInvoice = $event->data->object;

        $invoice = Invoice::where('stripe_invoice_id', $stripeInvoice->id)->first();

        if (! $invoice) {
            $tenant = Tenant::where('stripe_id', $stripeInvoice->customer)->first();
            if (! $tenant) {
                return response()->json(['status' => 'error', 'message' => 'Tenant not found'], 404);
            }

            $invoice = Invoice::create([
                'invoice_number' => 'STRIPE-'.$stripeInvoice->number,
                'tenant_id' => $tenant->id,
                'stripe_invoice_id' => $stripeInvoice->id,
                'amount' => $stripeInvoice->subtotal / 100,
                'tax_amount' => ($stripeInvoice->tax ?? 0) / 100,
                'total_amount' => $stripeInvoice->total / 100,
                'currency' => strtoupper($stripeInvoice->currency),
                'status' => 'paid',
                'paid_at' => now(),
                'due_date' => now(),
            ]);
        }

        $this->syncService->syncInvoice($invoice);

        return response()->json(['status' => 'success', 'message' => 'Invoice synced']);
    }

    protected function handleInvoicePaymentFailed(Event $event): JsonResponse
    {
        $stripeInvoice = $event->data->object;

        $invoice = Invoice::where('stripe_invoice_id', $stripeInvoice->id)->first();

        if ($invoice) {
            $invoice->update(['status' => 'overdue']);
        }

        return response()->json(['status' => 'success', 'message' => 'Payment failure recorded']);
    }

    protected function handleCustomerUpdated(Event $event): JsonResponse
    {
        $stripeCustomer = $event->data->object;
        $tenant = Tenant::where('stripe_id', $stripeCustomer->id)->first();

        if ($tenant) {
            $tenant->stripe_customer_email = $stripeCustomer->email;
            $tenant->card_brand = $stripeCustomer->invoice_settings?->default_payment_method?->card?->brand;
            $tenant->card_last_four = $stripeCustomer->invoice_settings?->default_payment_method?->card?->last4;
            $tenant->save();
        }

        return response()->json(['status' => 'success', 'message' => 'Customer updated']);
    }

    protected function mapStripeStatus(string $stripeStatus): string
    {
        return match ($stripeStatus) {
            'trialing' => 'trial',
            'active' => 'active',
            'past_due' => 'suspended',
            'canceled' => 'cancelled',
            'unpaid' => 'suspended',
            'incomplete' => 'active',
            'incomplete_expired' => 'cancelled',
            default => 'active',
        };
    }
}

<?php

namespace App\Http\Controllers\Central\Api\V1\Billing;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\Central\StripeSyncService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Stripe\StripeClient;

class BillingPortalController extends Controller
{
    public function __construct(
        private readonly StripeClient $stripe,
        private readonly StripeSyncService $syncService,
    ) {}

    public function createPortalSession(Tenant $tenant): JsonResponse
    {
        if (! $tenant->stripe_id) {
            $this->syncService->syncCustomer($tenant);
        }

        $session = $this->stripe->billingPortal->sessions->create([
            'customer' => $tenant->stripe_id,
            'return_url' => config('app.url').'/api/central/v1/billing/portal-return',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Billing portal session created.',
            'data' => [
                'url' => $session->url,
            ],
        ]);
    }

    public function createCheckoutSession(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'tenant_id' => 'required|string|exists:tenants,id',
            'price_id' => 'required|string',
            'success_url' => 'required|url',
            'cancel_url' => 'required|url',
            'quantity' => 'integer|min:1',
        ]);

        $tenant = Tenant::findOrFail($validated['tenant_id']);

        if (! $tenant->stripe_id) {
            $this->syncService->syncCustomer($tenant);
        }

        $session = $this->stripe->checkout->sessions->create([
            'customer' => $tenant->stripe_id,
            'mode' => 'subscription',
            'line_items' => [[
                'price' => $validated['price_id'],
                'quantity' => $validated['quantity'] ?? 1,
            ]],
            'success_url' => $validated['success_url'],
            'cancel_url' => $validated['cancel_url'],
            'metadata' => [
                'tenant_id' => $tenant->id,
            ],
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Checkout session created.',
            'data' => [
                'url' => $session->url,
                'session_id' => $session->id,
            ],
        ]);
    }

    public function getPaymentMethods(Tenant $tenant): JsonResponse
    {
        if (! $tenant->stripe_id) {
            return response()->json([
                'status' => 'success',
                'message' => 'No payment methods.',
                'data' => [],
            ]);
        }

        $paymentMethods = $this->stripe->paymentMethods->all([
            'customer' => $tenant->stripe_id,
            'type' => 'card',
        ]);

        $methods = collect($paymentMethods->data)->map(fn ($pm) => [
            'id' => $pm->id,
            'brand' => $pm->card->brand,
            'last4' => $pm->card->last4,
            'exp_month' => $pm->card->exp_month,
            'exp_year' => $pm->card->exp_year,
            'is_default' => $pm->id === $tenant->stripe_id.'_default',
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment methods retrieved.',
            'data' => $methods,
        ]);
    }

    public function getInvoices(Tenant $tenant): JsonResponse
    {
        if (! $tenant->stripe_id) {
            return response()->json([
                'status' => 'success',
                'message' => 'No invoices.',
                'data' => [],
            ]);
        }

        $invoices = $this->stripe->invoices->all([
            'customer' => $tenant->stripe_id,
            'limit' => 50,
        ]);

        $data = collect($invoices->data)->map(fn ($inv) => [
            'id' => $inv->id,
            'number' => $inv->number,
            'amount_due' => $inv->amount_due / 100,
            'amount_paid' => $inv->amount_paid / 100,
            'status' => $inv->status,
            'currency' => strtoupper($inv->currency),
            'pdf_url' => $inv->invoice_pdf,
            'hosted_url' => $inv->hosted_invoice_url,
            'created' => now()->timestamp($inv->created)->toDateTimeString(),
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Invoices retrieved.',
            'data' => $data,
        ]);
    }
}

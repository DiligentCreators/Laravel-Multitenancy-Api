<?php

declare(strict_types=1);

namespace App\Jobs\Central;

use App\Models\Invoice;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class BillingAutomationJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function handle(): void
    {
        $this->generateRecurringInvoices();
        $this->sendRenewalReminders();
        $this->retryFailedPayments();
    }

    private function generateRecurringInvoices(): void
    {
        Subscription::query()
            ->where('status', 'active')
            ->whereDate('ends_at', '<=', Carbon::now()->addDay())
            ->whereDate('ends_at', '>=', Carbon::now()->subDay())
            ->chunk(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    $exists = Invoice::query()
                        ->where('subscription_id', $subscription->id)
                        ->where('status', 'draft')
                        ->exists();

                    if ($exists) {
                        continue;
                    }

                    $plan = $subscription->plan;

                    Invoice::create([
                        'tenant_id' => $subscription->tenant_id,
                        'subscription_id' => $subscription->id,
                        'invoice_number' => $this->generateInvoiceNumber(),
                        'amount' => $subscription->billing_cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price,
                        'total_amount' => $subscription->billing_cycle === 'yearly' ? $plan->yearly_price : $plan->monthly_price,
                        'currency' => 'USD',
                        'status' => 'pending',
                        'due_date' => Carbon::now()->addDays(7),
                    ]);

                    Log::info('Recurring invoice generated', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                    ]);
                }
            });
    }

    private function sendRenewalReminders(): void
    {
        Subscription::query()
            ->where('status', 'active')
            ->whereDate('ends_at', Carbon::now()->addDays(7))
            ->chunk(100, function ($subscriptions) {
                foreach ($subscriptions as $subscription) {
                    Log::info('Renewal reminder for subscription', [
                        'subscription_id' => $subscription->id,
                        'tenant_id' => $subscription->tenant_id,
                        'ends_at' => $subscription->ends_at,
                    ]);
                }
            });
    }

    private function retryFailedPayments(): void
    {
        Invoice::query()
            ->where('status', 'overdue')
            ->where('due_date', '<', Carbon::now())
            ->chunk(100, function ($invoices) {
                foreach ($invoices as $invoice) {
                    $invoice->update(['status' => 'overdue']);

                    Log::info('Invoice marked as overdue after retry', [
                        'invoice_id' => $invoice->id,
                        'tenant_id' => $invoice->tenant_id,
                    ]);
                }
            });
    }

    private function generateInvoiceNumber(): string
    {
        $prefix = 'INV-';
        $date = Carbon::now()->format('Ymd');
        $last = Invoice::query()->withTrashed()
            ->where('invoice_number', 'like', "{$prefix}{$date}-%")
            ->latest()
            ->first();

        $sequence = $last ? (int) substr($last->invoice_number, -4) + 1 : 1;

        return sprintf('%s%s-%04d', $prefix, $date, $sequence);
    }

    public function retryUntil(): Carbon
    {
        return now()->addMinutes(5);
    }

    public function backoff(): array
    {
        return [2, 5, 10, 30];
    }

    public function failed(\Throwable $exception): void
    {
        Log::error('BillingAutomationJob failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

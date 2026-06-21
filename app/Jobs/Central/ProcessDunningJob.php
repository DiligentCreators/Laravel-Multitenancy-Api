<?php

namespace App\Jobs\Central;

use App\Models\Invoice;
use App\Services\Central\DunningService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ProcessDunningJob implements ShouldQueue
{
    use Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function handle(DunningService $dunningService): void
    {
        $overdueInvoices = Invoice::where('status', 'overdue')
            ->whereNull('paid_at')
            ->whereHas('payments', function ($query) {
                $query->where('status', 'failed');
            })
            ->get();

        foreach ($overdueInvoices as $invoice) {
            $lastPayment = $invoice->payments()
                ->where('status', 'failed')
                ->latest()
                ->first();

            if (! $lastPayment) {
                continue;
            }

            $data = $lastPayment->data ?? [];
            $failedAttempts = $data['failed_attempts'] ?? 0;
            $nextRetry = isset($data['next_retry_at'])
                ? Carbon::parse($data['next_retry_at'])
                : null;

            if ($nextRetry && $nextRetry->isFuture()) {
                continue;
            }

            if ($failedAttempts >= 5) {
                $dunningService->escalateToManual($invoice);

                continue;
            }

            $dunningService->sendDunningNotification($invoice, $failedAttempts + 1);
        }

        Log::info('Dunning job processed.', ['invoices_checked' => $overdueInvoices->count()]);
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
        Log::error('ProcessDunningJob failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

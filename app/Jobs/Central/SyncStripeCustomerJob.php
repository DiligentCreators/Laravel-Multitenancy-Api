<?php

namespace App\Jobs\Central;

use App\Models\Tenant;
use App\Services\Central\StripeSyncService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class SyncStripeCustomerJob implements ShouldQueue
{
    use Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function __construct(
        private readonly string $tenantId,
    ) {}

    public function handle(StripeSyncService $syncService): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::warning('Tenant not found for Stripe sync.', ['tenant_id' => $this->tenantId]);

            return;
        }

        $syncService->syncCustomer($tenant);

        Log::info('Stripe customer synced.', ['tenant_id' => $this->tenantId]);
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
        Log::error('SyncStripeCustomerJob failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

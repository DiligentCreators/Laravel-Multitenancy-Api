<?php

namespace App\Jobs\Central;

use App\Services\Central\TenantDataService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TenantCleanupJob implements ShouldQueue
{
    use Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function handle(TenantDataService $dataService): void
    {
        $cleaned = $dataService->cleanup(30);

        Log::info('Tenant export cleanup completed.', ['records_cleaned' => $cleaned]);
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
        Log::error('TenantCleanupJob failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

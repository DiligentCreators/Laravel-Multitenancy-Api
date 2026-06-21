<?php

namespace App\Jobs\Central;

use App\Models\Tenant;
use App\Services\Central\TenantDataService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class TenantExportJob implements ShouldQueue
{
    use Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function __construct(
        private readonly string $tenantId,
        private readonly string $type = 'full',
        private readonly string $format = 'json',
    ) {}

    public function handle(TenantDataService $dataService): void
    {
        $tenant = Tenant::find($this->tenantId);

        if (! $tenant) {
            Log::warning('Tenant not found for export.', ['tenant_id' => $this->tenantId]);

            return;
        }

        try {
            $dataService->export($tenant, $this->type, $this->format);
            Log::info('Tenant export completed.', [
                'tenant_id' => $this->tenantId,
                'type' => $this->type,
                'format' => $this->format,
            ]);
        } catch (\Throwable $e) {
            Log::error('Tenant export failed.', [
                'tenant_id' => $this->tenantId,
                'error' => $e->getMessage(),
            ]);
        }
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
        Log::error('TenantExportJob failed', [
            'job' => self::class,
            'error' => $exception->getMessage(),
        ]);
    }
}

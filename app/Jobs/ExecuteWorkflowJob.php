<?php

namespace App\Jobs;

use App\Models\Crm\WorkflowDefinition;
use App\Services\Crm\WorkflowService;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function __construct(
        public string $tenantId,
        public int $workflowId,
        public string $entityClass,
        public int|string $entityKey,
    ) {
        $this->onQueue('workflows')->afterCommit();
    }

    public function handle(WorkflowService $workflow): void
    {
        $wasInitialized = tenancy()->initialized;

        tenancy()->initialize($this->tenantId);

        try {
            $workflowDef = WorkflowDefinition::findOrFail($this->workflowId);

            $entity = $this->entityClass::findOrFail($this->entityKey);

            if ($workflowDef->tenant_id !== $this->tenantId) {
                return;
            }

            $workflow->execute($workflowDef, $entity);
        } finally {
            if (! $wasInitialized && tenancy()->initialized) {
                tenancy()->end();
            }
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
        Log::error('ExecuteWorkflowJob failed', [
            'job' => self::class,
            'tenant_id' => $this->tenantId ?? null,
            'error' => $exception->getMessage(),
        ]);

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}

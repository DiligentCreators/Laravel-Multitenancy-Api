<?php

namespace App\Jobs;

use App\Models\Crm\TimelineEntry;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class RecordTimelineEntryJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public $tries = 0;

    public $maxExceptions = 3;

    public $timeout = 60;

    public function __construct(
        public string $tenantId,
        public string $entityType,
        public int $entityId,
        public string $eventType,
        public string $title,
        public ?string $description = null,
        public ?array $meta = null,
        public ?int $causedBy = null,
        public ?string $occurredAt = null,
    ) {
        $this->onQueue('timeline')->afterCommit();
    }

    public function handle(): void
    {
        $wasInitialized = tenancy()->initialized;

        tenancy()->initialize($this->tenantId);

        try {
            TimelineEntry::firstOrCreate(
                [
                    'tenant_id' => $this->tenantId,
                    'entity_type' => $this->entityType,
                    'entity_id' => $this->entityId,
                    'event_type' => $this->eventType,
                    'occurred_at' => $this->occurredAt ?? now(),
                ],
                [
                    'title' => $this->title,
                    'description' => $this->description,
                    'meta' => $this->meta,
                    'caused_by' => $this->causedBy,
                ],
            );
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
        Log::error('RecordTimelineEntryJob failed', [
            'job' => self::class,
            'tenant_id' => $this->tenantId ?? null,
            'error' => $exception->getMessage(),
        ]);

        if (tenancy()->initialized) {
            tenancy()->end();
        }
    }
}

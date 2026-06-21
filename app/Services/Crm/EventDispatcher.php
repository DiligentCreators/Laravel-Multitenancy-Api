<?php

namespace App\Services\Crm;

use App\Jobs\RecordTimelineEntryJob;
use App\Jobs\TriggerWorkflowJob;
use Illuminate\Database\Eloquent\Model;

class EventDispatcher
{
    public function __construct(
        private readonly MorphableEntityResolver $morphResolver,
    ) {}

    public function record(
        Model $entity,
        string $eventType,
        string $title,
        ?string $description = null,
        ?array $meta = null,
        ?int $causedBy = null,
    ): void {
        $this->recordTimeline($entity, $eventType, $title, $description, $meta, $causedBy);

        $this->triggerWorkflows($entity, $eventType);
    }

    public function recordGeneric(
        string $entityType,
        int $entityId,
        string $eventType,
        string $title,
        ?string $description = null,
        ?array $meta = null,
        ?int $causedBy = null,
    ): void {
        $this->recordTimelineGeneric($entityType, $entityId, $eventType, $title, $description, $meta, $causedBy);
    }

    public function recordTimeline(
        Model $entity,
        string $eventType,
        string $title,
        ?string $description = null,
        ?array $meta = null,
        ?int $causedBy = null,
    ): void {
        $tenantId = tenant()?->id;

        if ($tenantId === null) {
            return;
        }

        RecordTimelineEntryJob::dispatch(
            $tenantId,
            $entity->getMorphClass(),
            $entity->id,
            $eventType,
            $title,
            $description,
            $meta,
            $causedBy,
            now(),
        );
    }

    public function recordTimelineGeneric(
        string $entityType,
        int $entityId,
        string $eventType,
        string $title,
        ?string $description = null,
        ?array $meta = null,
        ?int $causedBy = null,
    ): void {
        $tenantId = tenant()?->id;

        if ($tenantId === null) {
            return;
        }

        RecordTimelineEntryJob::dispatch(
            $tenantId,
            $entityType,
            $entityId,
            $eventType,
            $title,
            $description,
            $meta,
            $causedBy,
            now(),
        );
    }

    public function triggerWorkflows(Model $entity, string $event): void
    {
        $tenantId = tenant()?->id;

        if ($tenantId === null) {
            return;
        }

        TriggerWorkflowJob::dispatch($tenantId, $event, get_class($entity), $entity->id);
    }

    public function getEventType(string $entity, string $action): string
    {
        $key = $this->morphResolver->getMorphKey($entity);

        return ($key ?? strtolower(class_basename($entity))).'.'.$action;
    }
}

<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Resources\Tenant\Api\V1\Crm\TimelineEntryResource;
use App\Models\Crm\TimelineEntry;
use App\Services\ApiResponseService;
use App\Services\Crm\TimelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TimelineController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TimelineService $timelineService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', TimelineEntry::class);

        $perPage = min((int) request('per_page', 25), 100);
        $entries = $this->timelineService->paginateWithFilters(request()->only([
            'entity_type', 'entity_id', 'event_type', 'search', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Timeline retrieved successfully', TimelineEntryResource::collection($entries));
    }

    public function show(TimelineEntry $timelineEntry): JsonResponse
    {
        Gate::authorize('view', $timelineEntry);

        $timelineEntry = $this->timelineService->find($timelineEntry->id);

        return $this->api->success('Timeline entry retrieved successfully', new TimelineEntryResource($timelineEntry));
    }

    public function byEntity(string $entityType, int $entityId): JsonResponse
    {
        Gate::authorize('viewAny', TimelineEntry::class);

        $perPage = min((int) request('per_page', 25), 100);
        $entries = $this->timelineService->getForEntityPaginated($entityType, $entityId, $perPage);

        return $this->api->success('Timeline entries retrieved successfully', TimelineEntryResource::collection($entries));
    }
}

<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreActivityRequest;
use App\Http\Requests\Crm\UpdateActivityRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\ActivityResource;
use App\Models\Crm\Activity;
use App\Services\ApiResponseService;
use App\Services\Crm\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ActivityController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly ActivityService $activityService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Activity::class);

        $perPage = min((int) request('per_page', 25), 100);
        $activities = $this->activityService->paginateWithFilters(request()->only([
            'search', 'type', 'status', 'owner_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Activities retrieved successfully', ActivityResource::collection($activities));
    }

    public function store(StoreActivityRequest $request): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $activity = $this->activityService->create($request->validated());

        return $this->api->success('Activity created successfully', new ActivityResource($activity), 201);
    }

    public function show(Activity $activity): JsonResponse
    {
        Gate::authorize('view', $activity);

        $activity = $this->activityService->find($activity->id);

        return $this->api->success('Activity retrieved successfully', new ActivityResource($activity));
    }

    public function update(UpdateActivityRequest $request, Activity $activity): JsonResponse
    {
        Gate::authorize('update', $activity);

        $activity = $this->activityService->update($activity, $request->validated());

        return $this->api->success('Activity updated successfully', new ActivityResource($activity));
    }

    public function destroy(Activity $activity): JsonResponse
    {
        Gate::authorize('delete', $activity);

        $this->activityService->delete($activity);

        return $this->api->success('Activity deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Activity::class);

        $this->activityService->restore($id);

        return $this->api->success('Activity restored successfully');
    }

    public function byEntity(string $type, int $id): JsonResponse
    {
        Gate::authorize('viewAny', Activity::class);

        $perPage = min((int) request('per_page', 25), 100);
        $activities = $this->activityService->getForEntityPaginated($type, $id, $perPage);

        return $this->api->success('Activities retrieved successfully', ActivityResource::collection($activities));
    }
}

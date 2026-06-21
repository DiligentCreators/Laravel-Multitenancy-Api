<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreStatusRequest;
use App\Http\Requests\Crm\UpdateStatusRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\StatusResource;
use App\Models\Crm\Status;
use App\Services\ApiResponseService;
use App\Services\Crm\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StatusController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly StatusService $statusService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Status::class);

        $perPage = min((int) request('per_page', 50), 100);
        $statuses = $this->statusService->paginateWithFilters(
            request('entity_type'),
            request('type_id') ? (int) request('type_id') : null,
            $perPage
        );

        return $this->api->success('Statuses retrieved successfully', StatusResource::collection($statuses));
    }

    public function store(StoreStatusRequest $request): JsonResponse
    {
        Gate::authorize('create', Status::class);

        $status = $this->statusService->create($request->validated());

        return $this->api->success('Status created successfully', new StatusResource($status), 201);
    }

    public function show(Status $status): JsonResponse
    {
        Gate::authorize('view', $status);

        return $this->api->success('Status retrieved successfully', new StatusResource($status->load('type')));
    }

    public function update(UpdateStatusRequest $request, Status $status): JsonResponse
    {
        Gate::authorize('update', $status);

        $status = $this->statusService->update($status, $request->validated());

        return $this->api->success('Status updated successfully', new StatusResource($status));
    }

    public function byEntity(string $entityType): JsonResponse
    {
        Gate::authorize('viewAny', Status::class);

        $statuses = $this->statusService->getStatusesForEntity($entityType);

        return $this->api->success('Statuses retrieved successfully', StatusResource::collection($statuses));
    }

    public function destroy(Status $status): JsonResponse
    {
        Gate::authorize('delete', $status);

        $this->statusService->delete($status);

        return $this->api->success('Status deleted successfully');
    }
}

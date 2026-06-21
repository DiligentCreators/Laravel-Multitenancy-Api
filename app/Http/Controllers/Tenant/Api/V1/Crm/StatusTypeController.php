<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreStatusTypeRequest;
use App\Http\Requests\Crm\UpdateStatusTypeRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\StatusTypeResource;
use App\Models\Crm\StatusType;
use App\Services\ApiResponseService;
use App\Services\Crm\StatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class StatusTypeController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly StatusService $statusService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', StatusType::class);

        return $this->api->success(
            'Status types retrieved successfully',
            StatusTypeResource::collection($this->statusService->getStatusTypes()->get())
        );
    }

    public function store(StoreStatusTypeRequest $request): JsonResponse
    {
        Gate::authorize('create', StatusType::class);

        $type = $this->statusService->createType($request->validated());

        return $this->api->success('Status type created successfully', new StatusTypeResource($type), 201);
    }

    public function show(StatusType $statusType): JsonResponse
    {
        Gate::authorize('view', $statusType);

        return $this->api->success('Status type retrieved successfully', new StatusTypeResource($statusType->load('statuses')));
    }

    public function update(UpdateStatusTypeRequest $request, StatusType $statusType): JsonResponse
    {
        Gate::authorize('update', $statusType);

        $type = $this->statusService->updateType($statusType, $request->validated());

        return $this->api->success('Status type updated successfully', new StatusTypeResource($type));
    }

    public function destroy(StatusType $statusType): JsonResponse
    {
        Gate::authorize('delete', $statusType);

        $this->statusService->deleteType($statusType);

        return $this->api->success('Status type deleted successfully');
    }
}

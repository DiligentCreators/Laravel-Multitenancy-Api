<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StorePipelineRequest;
use App\Http\Requests\Crm\UpdatePipelineRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\PipelineResource;
use App\Models\Crm\Pipeline;
use App\Services\ApiResponseService;
use App\Services\Crm\PipelineService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PipelineController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PipelineService $pipelineService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Pipeline::class);

        $perPage = min((int) request('per_page', 25), 100);
        $pipelines = $this->pipelineService->paginateWithFilters(request()->only([
            'search', 'is_active', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Pipelines retrieved successfully', PipelineResource::collection($pipelines));
    }

    public function store(StorePipelineRequest $request): JsonResponse
    {
        Gate::authorize('create', Pipeline::class);

        $pipeline = $this->pipelineService->create($request->validated());

        return $this->api->success('Pipeline created successfully', new PipelineResource($pipeline), 201);
    }

    public function show(Pipeline $pipeline): JsonResponse
    {
        Gate::authorize('view', $pipeline);

        $pipeline = $this->pipelineService->find($pipeline->id);

        return $this->api->success('Pipeline retrieved successfully', new PipelineResource($pipeline));
    }

    public function update(UpdatePipelineRequest $request, Pipeline $pipeline): JsonResponse
    {
        Gate::authorize('update', $pipeline);

        $pipeline = $this->pipelineService->update($pipeline, $request->validated());

        return $this->api->success('Pipeline updated successfully', new PipelineResource($pipeline));
    }

    public function destroy(Pipeline $pipeline): JsonResponse
    {
        Gate::authorize('delete', $pipeline);

        $this->pipelineService->delete($pipeline);

        return $this->api->success('Pipeline deleted successfully');
    }
}

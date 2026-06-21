<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StorePipelineStageRequest;
use App\Http\Requests\Crm\UpdatePipelineStageRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\PipelineStageResource;
use App\Models\Crm\PipelineStage;
use App\Services\ApiResponseService;
use App\Services\Crm\PipelineStageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class PipelineStageController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PipelineStageService $pipelineStageService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PipelineStage::class);

        $perPage = min((int) request('per_page', 25), 100);
        $stages = $this->pipelineStageService->paginateWithFilters(request()->only([
            'pipeline_id', 'search', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Pipeline stages retrieved successfully', PipelineStageResource::collection($stages));
    }

    public function store(StorePipelineStageRequest $request): JsonResponse
    {
        Gate::authorize('create', PipelineStage::class);

        $stage = $this->pipelineStageService->create($request->validated());

        return $this->api->success('Pipeline stage created successfully', new PipelineStageResource($stage), 201);
    }

    public function show(PipelineStage $pipelineStage): JsonResponse
    {
        Gate::authorize('view', $pipelineStage);

        $pipelineStage = $this->pipelineStageService->find($pipelineStage->id);

        return $this->api->success('Pipeline stage retrieved successfully', new PipelineStageResource($pipelineStage));
    }

    public function update(UpdatePipelineStageRequest $request, PipelineStage $pipelineStage): JsonResponse
    {
        Gate::authorize('update', $pipelineStage);

        $pipelineStage = $this->pipelineStageService->update($pipelineStage, $request->validated());

        return $this->api->success('Pipeline stage updated successfully', new PipelineStageResource($pipelineStage));
    }

    public function destroy(PipelineStage $pipelineStage): JsonResponse
    {
        Gate::authorize('delete', $pipelineStage);

        $this->pipelineStageService->delete($pipelineStage);

        return $this->api->success('Pipeline stage deleted successfully');
    }

    public function byPipeline(int $pipelineId): JsonResponse
    {
        Gate::authorize('viewAny', PipelineStage::class);

        $stages = $this->pipelineStageService->getByPipeline($pipelineId);

        return $this->api->success('Pipeline stages retrieved successfully', PipelineStageResource::collection($stages));
    }

    public function reorder(Request $request): JsonResponse
    {
        Gate::authorize('update', PipelineStage::class);

        $request->validate(['order' => ['required', 'array'], 'order.*' => ['integer', 'exists:crm_pipeline_stages,id']]);

        $this->pipelineStageService->reorder($request->input('order'));

        return $this->api->success('Pipeline stages reordered successfully');
    }
}

<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreWorkflowRequest;
use App\Http\Requests\Crm\UpdateWorkflowRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\WorkflowDefinitionResource;
use App\Http\Resources\Tenant\Api\V1\Crm\WorkflowLogResource;
use App\Models\Crm\WorkflowDefinition;
use App\Services\ApiResponseService;
use App\Services\Crm\WorkflowDefinitionService;
use App\Services\Crm\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class WorkflowDefinitionController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly WorkflowDefinitionService $workflowDefinitionService,
        private readonly WorkflowService $workflowService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', WorkflowDefinition::class);

        $perPage = min((int) request('per_page', 25), 100);
        $workflows = $this->workflowDefinitionService->paginate($perPage);

        return $this->api->success('Workflows retrieved successfully', WorkflowDefinitionResource::collection($workflows));
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        Gate::authorize('create', WorkflowDefinition::class);

        $workflow = $this->workflowDefinitionService->create($request->validated());

        return $this->api->success('Workflow created successfully', new WorkflowDefinitionResource($workflow), 201);
    }

    public function show(WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('view', $workflow);

        return $this->api->success('Workflow retrieved successfully', new WorkflowDefinitionResource($workflow));
    }

    public function update(UpdateWorkflowRequest $request, WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('update', $workflow);

        $workflow = $this->workflowDefinitionService->update($workflow, $request->validated());

        return $this->api->success('Workflow updated successfully', new WorkflowDefinitionResource($workflow));
    }

    public function destroy(WorkflowDefinition $workflow): JsonResponse
    {
        Gate::authorize('delete', $workflow);

        $this->workflowDefinitionService->delete($workflow);

        return $this->api->success('Workflow deleted successfully');
    }

    public function logs(WorkflowDefinition $workflowDefinition): JsonResponse
    {
        Gate::authorize('view', $workflowDefinition);

        $perPage = min((int) request('per_page', 25), 100);
        $logs = $this->workflowService->paginateLogs($workflowDefinition->id, $perPage);

        return $this->api->success('Workflow logs retrieved successfully', WorkflowLogResource::collection($logs));
    }
}

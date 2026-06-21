<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreLeadRequest;
use App\Http\Requests\Crm\UpdateLeadRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\LeadResource;
use App\Models\Crm\Lead;
use App\Services\ApiResponseService;
use App\Services\Crm\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeadController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly LeadService $leadService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);

        $perPage = min((int) request('per_page', 25), 100);
        $leads = $this->leadService->paginateWithFilters(request()->only([
            'search', 'status_id', 'source_id', 'owner_id', 'pipeline_id', 'pipeline_stage_id', 'sort_by', 'sort_order',
        ]), $perPage);

        return $this->api->success('Leads retrieved successfully', LeadResource::collection($leads));
    }

    public function store(StoreLeadRequest $request): JsonResponse
    {
        Gate::authorize('create', Lead::class);

        $lead = $this->leadService->create($request->validated());

        return $this->api->success('Lead created successfully', new LeadResource($lead), 201);
    }

    public function show(Lead $lead): JsonResponse
    {
        Gate::authorize('view', $lead);

        $lead = $this->leadService->find($lead->id);

        return $this->api->success('Lead retrieved successfully', new LeadResource($lead));
    }

    public function update(UpdateLeadRequest $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $lead = $this->leadService->update($lead, $request->validated());

        return $this->api->success('Lead updated successfully', new LeadResource($lead));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);

        $this->leadService->delete($lead);

        return $this->api->success('Lead deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Lead::class);

        $this->leadService->restore($id);

        return $this->api->success('Lead restored successfully');
    }

    public function moveStage(Request $request, Lead $lead): JsonResponse
    {
        Gate::authorize('update', $lead);

        $request->validate([
            'pipeline_stage_id' => ['required', 'integer', 'exists:crm_pipeline_stages,id'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $lead = $this->leadService->moveStage($lead, (int) $request->input('pipeline_stage_id'), $request->input('reason'));
        $lead->load('pipelineStage');

        return $this->api->success('Lead stage moved successfully', new LeadResource($lead));
    }
}

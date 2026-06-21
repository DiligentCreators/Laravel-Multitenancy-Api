<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreOrganizationRequest;
use App\Http\Requests\Crm\UpdateOrganizationRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\OrganizationResource;
use App\Models\Crm\Organization;
use App\Services\ApiResponseService;
use App\Services\Crm\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly OrganizationService $organizationService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Organization::class);

        $perPage = min((int) request('per_page', 25), 100);
        $organizations = $this->organizationService->paginateWithFilters(
            request()->only(['search', 'status_id', 'source_id', 'owner_id', 'sort_by', 'sort_order']),
            $perPage
        );

        return $this->api->success('Organizations retrieved successfully', OrganizationResource::collection($organizations));
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $organization = $this->organizationService->create($request->validated());

        return $this->api->success('Organization created successfully', new OrganizationResource($organization), 201);
    }

    public function show(Organization $organization): JsonResponse
    {
        Gate::authorize('view', $organization);

        $organization = $this->organizationService->find($organization->id);

        return $this->api->success('Organization retrieved successfully', new OrganizationResource($organization));
    }

    public function update(UpdateOrganizationRequest $request, Organization $organization): JsonResponse
    {
        Gate::authorize('update', $organization);

        $organization = $this->organizationService->update($organization, $request->validated());

        return $this->api->success('Organization updated successfully', new OrganizationResource($organization));
    }

    public function destroy(Organization $organization): JsonResponse
    {
        Gate::authorize('delete', $organization);

        $this->organizationService->delete($organization);

        return $this->api->success('Organization deleted successfully');
    }

    public function restore(int $id): JsonResponse
    {
        Gate::authorize('create', Organization::class);

        $this->organizationService->restore($id);

        return $this->api->success('Organization restored successfully');
    }

    public function forceDelete(int $id): JsonResponse
    {
        Gate::authorize('delete', Organization::class);

        $this->organizationService->forceDelete($id);

        return $this->api->success('Organization permanently deleted successfully');
    }
}

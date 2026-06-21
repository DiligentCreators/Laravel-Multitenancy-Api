<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StoreOrganizationPersonRequest;
use App\Http\Requests\Crm\UpdateOrganizationPersonRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\OrganizationPersonResource;
use App\Models\Crm\OrganizationPerson;
use App\Services\ApiResponseService;
use App\Services\Crm\OrganizationPersonService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class OrganizationPersonController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly OrganizationPersonService $organizationPersonService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', OrganizationPerson::class);

        $perPage = min((int) request('per_page', 25), 100);
        $records = $this->organizationPersonService->paginate($perPage);

        return $this->api->success('Organization people retrieved successfully', OrganizationPersonResource::collection($records));
    }

    public function store(StoreOrganizationPersonRequest $request): JsonResponse
    {
        Gate::authorize('create', OrganizationPerson::class);

        $record = $this->organizationPersonService->create($request->validated());

        return $this->api->success('Organization person created successfully', new OrganizationPersonResource($record), 201);
    }

    public function show(OrganizationPerson $organizationPerson): JsonResponse
    {
        Gate::authorize('view', $organizationPerson);

        $organizationPerson = $this->organizationPersonService->find($organizationPerson->id);

        return $this->api->success('Organization person retrieved successfully', new OrganizationPersonResource($organizationPerson));
    }

    public function update(UpdateOrganizationPersonRequest $request, OrganizationPerson $organizationPerson): JsonResponse
    {
        Gate::authorize('update', $organizationPerson);

        $record = $this->organizationPersonService->update($organizationPerson, $request->validated());

        return $this->api->success('Organization person updated successfully', new OrganizationPersonResource($record));
    }

    public function destroy(OrganizationPerson $organizationPerson): JsonResponse
    {
        Gate::authorize('delete', $organizationPerson);

        $this->organizationPersonService->delete($organizationPerson);

        return $this->api->success('Organization person deleted successfully');
    }

    public function byOrganization(int $orgId): JsonResponse
    {
        Gate::authorize('viewAny', OrganizationPerson::class);

        $records = $this->organizationPersonService->getPeopleForOrganization($orgId);

        return $this->api->success('People for organization retrieved successfully', OrganizationPersonResource::collection($records));
    }

    public function byPerson(int $personId): JsonResponse
    {
        Gate::authorize('viewAny', OrganizationPerson::class);

        $records = $this->organizationPersonService->getOrganizationsForPerson($personId);

        return $this->api->success('Organizations for person retrieved successfully', OrganizationPersonResource::collection($records));
    }
}

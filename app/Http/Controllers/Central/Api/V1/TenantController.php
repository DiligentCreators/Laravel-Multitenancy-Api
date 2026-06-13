<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Tenant\StoreTenantRequest;
use App\Http\Requests\Central\Api\V1\Tenant\UpdateTenantRequest;
use App\Http\Resources\Central\Api\V1\Tenant\ListTenantResource;
use App\Http\Resources\Central\Api\V1\Tenant\TenantResource;
use App\Models\Tenant;
use App\Services\ApiResponseService;
use App\Services\Central\TenantService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TenantController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly TenantService $tenantService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Tenant::class);

        $tenants = $this->tenantService->paginate(
            request(),
            $this->perPage(request())
        );

        return $this->api->success(
            'Tenants retrieved successfully',
            ListTenantResource::collection($tenants),
        );
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        Gate::authorize('create', Tenant::class);

        $validated = $request->validated();

        $tenant = $this->tenantService->create($validated);

        return $this->api->success(
            'Tenant has been created successfully',
            new TenantResource($tenant),
            201,
        );
    }

    public function show(Tenant $tenant): JsonResponse
    {
        Gate::authorize('view', $tenant);

        if ($tenant->trashed()) {
            return $this->api->notFound('Tenant has been deleted.');
        }

        return $this->api->success(
            'Tenant retrieved successfully',
            new TenantResource($tenant),
        );
    }

    public function update(UpdateTenantRequest $request, Tenant $tenant): JsonResponse
    {
        Gate::authorize('update', $tenant);

        if ($tenant->trashed()) {
            return $this->api->notFound('Cannot update a deleted tenant.');
        }

        $this->tenantService->update($tenant, $request->validated());

        return $this->api->success(
            'Tenant has been updated successfully',
            new TenantResource($tenant),
        );
    }

    public function destroy(Tenant $tenant): JsonResponse
    {
        Gate::authorize('delete', $tenant);

        if ($tenant->trashed()) {
            return $this->api->notFound('Tenant is already deleted.');
        }

        $tenant->delete();

        return $this->api->success(
            'Tenant has been deleted successfully',
            null,
            200,
        );
    }

    public function restore(Tenant $tenant): JsonResponse
    {
        Gate::authorize('restore', $tenant);

        if (! $tenant->trashed()) {
            return $this->api->notFound('Tenant is not deleted.');
        }

        $tenant->restore();

        $tenant->users()->onlyTrashed()->restore();
        $tenant->domains()->onlyTrashed()->restore();

        return $this->api->success(
            'Tenant has been restored successfully',
            new TenantResource($tenant),
        );
    }

    public function forceDelete(Tenant $tenant): JsonResponse
    {
        Gate::authorize('forceDelete', $tenant);

        if (! $tenant->trashed()) {
            return $this->api->error('Tenant must be deleted before force deleting.', 400);
        }

        $tenant->users()->forceDelete();
        $tenant->domains()->forceDelete();
        $tenant->subscriptions()->forceDelete();

        $tenant->forceDelete();

        return $this->api->success(
            'Tenant has been force deleted successfully',
            null,
            200,
        );
    }
}

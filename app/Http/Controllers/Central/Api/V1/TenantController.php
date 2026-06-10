<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\StoreTenantRequest;
use App\Http\Requests\Central\Api\V1\UpdateTenantRequest;
use App\Http\Resources\Central\Api\V1\ListTenantResource;
use App\Http\Resources\Central\Api\V1\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class TenantController extends Controller
{
    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Tenant::class);

        $tenants = Tenant::query()
            ->when(request('search'), function ($query, $search) {
                $query->where(function ($q) use ($search) {
                    $q->where('company_name', 'like', '%'.$search.'%')
                        ->orWhere('id', 'like', '%'.$search.'%');
                });
            })
            ->when(request('trashed') === 'true', function ($query) {
                $query->withTrashed();
            })
            ->when(request('trashed') === 'only', function ($query) {
                $query->onlyTrashed();
            })
            ->orderBy(request('sort', 'created_at'), request('direction', 'desc'))
            ->paginate($this->perPage(request()));

        return $this->api->success(
            'Tenants retrieved successfully',
            ListTenantResource::collection($tenants),
        );
    }

    public function store(StoreTenantRequest $request): JsonResponse
    {
        Gate::authorize('create', Tenant::class);

        $validated = $request->validated();

        $tenant = Tenant::create($validated);

        $domain = $tenant->domains()->create([
            'domain' => $validated['domain'],
        ]);

        $user = User::create([
            'tenant_id' => $domain->tenant_id,
            'username' => $validated['username'],
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => bcrypt($validated['password']),
        ]);

        $tenant->load('domains');

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

        $tenant->update($request->validated());

        $domain = $tenant->load('domains');

        $domain->domains()->first()->update([
            'domain' => $request->domain,
        ]);

        $user = $tenant->users()->first();

        $user->update([
            'username' => $request->username,
            'name' => $request->name,
            'email' => $request->email,
            'password' => bcrypt($request->password),
        ]);

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

        $tenant->forceDelete();

        return $this->api->success(
            'Tenant has been force deleted successfully',
            null,
            200,
        );
    }
}

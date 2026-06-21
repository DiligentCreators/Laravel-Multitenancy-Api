<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StorePortalUserRequest;
use App\Http\Requests\Crm\UpdatePortalUserRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\PortalUserResource;
use App\Models\Crm\PortalUser;
use App\Services\ApiResponseService;
use App\Services\Crm\PortalUserService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PortalUserController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PortalUserService $portalUserService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PortalUser::class);

        $perPage = min((int) request('per_page', 25), 100);
        $portalUsers = $this->portalUserService->paginate($perPage);

        return $this->api->success('Portal users retrieved successfully', PortalUserResource::collection($portalUsers));
    }

    public function store(StorePortalUserRequest $request): JsonResponse
    {
        Gate::authorize('create', PortalUser::class);

        $portalUser = $this->portalUserService->create($request->validated());

        return $this->api->success('Portal user created successfully', new PortalUserResource($portalUser), 201);
    }

    public function show(PortalUser $portalUser): JsonResponse
    {
        Gate::authorize('view', $portalUser);

        return $this->api->success('Portal user retrieved successfully', new PortalUserResource($portalUser));
    }

    public function update(UpdatePortalUserRequest $request, PortalUser $portalUser): JsonResponse
    {
        Gate::authorize('update', $portalUser);

        $portalUser = $this->portalUserService->update($portalUser, $request->validated());

        return $this->api->success('Portal user updated successfully', new PortalUserResource($portalUser));
    }

    public function destroy(PortalUser $portalUser): JsonResponse
    {
        Gate::authorize('delete', $portalUser);

        $this->portalUserService->delete($portalUser);

        return $this->api->success('Portal user deleted successfully');
    }

    public function invite(PortalUser $portalUser): JsonResponse
    {
        Gate::authorize('update', $portalUser);

        $this->portalUserService->invite($portalUser);

        return $this->api->success('Invitation sent successfully');
    }

    public function activate(PortalUser $portalUser): JsonResponse
    {
        Gate::authorize('update', $portalUser);

        $this->portalUserService->activate($portalUser);

        return $this->api->success('Portal user activated successfully');
    }

    public function deactivate(PortalUser $portalUser): JsonResponse
    {
        Gate::authorize('update', $portalUser);

        $this->portalUserService->deactivate($portalUser);

        return $this->api->success('Portal user deactivated successfully');
    }
}

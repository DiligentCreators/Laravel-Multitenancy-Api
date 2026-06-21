<?php

namespace App\Http\Controllers\Tenant\Api\V1\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\StorePortalPersonLinkRequest;
use App\Http\Resources\Tenant\Api\V1\Crm\PortalPersonLinkResource;
use App\Models\Crm\PortalPersonLink;
use App\Services\ApiResponseService;
use App\Services\Crm\PortalPersonLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PortalPersonLinkController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PortalPersonLinkService $portalPersonLinkService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', PortalPersonLink::class);

        $perPage = min((int) request('per_page', 25), 100);
        $links = $this->portalPersonLinkService->paginate($perPage);

        return $this->api->success('Portal person links retrieved successfully', PortalPersonLinkResource::collection($links));
    }

    public function store(StorePortalPersonLinkRequest $request): JsonResponse
    {
        Gate::authorize('create', PortalPersonLink::class);

        $link = $this->portalPersonLinkService->create($request->validated());

        return $this->api->success('Portal person link created successfully', new PortalPersonLinkResource($link), 201);
    }

    public function show(PortalPersonLink $portalPersonLink): JsonResponse
    {
        Gate::authorize('view', $portalPersonLink);

        return $this->api->success('Portal person link retrieved successfully', new PortalPersonLinkResource($portalPersonLink->load(['portalUser', 'person', 'organization'])));
    }

    public function destroy(PortalPersonLink $portalPersonLink): JsonResponse
    {
        Gate::authorize('delete', $portalPersonLink);

        $this->portalPersonLinkService->delete($portalPersonLink);

        return $this->api->success('Portal person link deleted successfully');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\ApiKey\StoreApiKeyRequest;
use App\Http\Requests\Central\Api\V1\ApiKey\UpdateApiKeyRequest;
use App\Http\Resources\Central\Api\V1\ApiKey\ApiKeyResource;
use App\Http\Resources\Central\Api\V1\ApiKey\ListApiKeyResource;
use App\Models\ApiKey;
use App\Services\ApiResponseService;
use App\Services\Central\ApiKeyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class ApiKeyController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly ApiKeyService $apiKeyService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', ApiKey::class);

        $apiKeys = $this->apiKeyService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'apiKeys retrieved successfully',
            ListApiKeyResource::collection($apiKeys),
        );
    }

    public function store(StoreApiKeyRequest $request): JsonResponse
    {
        Gate::authorize('create', ApiKey::class);

        $apiKey = $this->apiKeyService->create($request->validated());

        $resource = new ApiKeyResource($apiKey);
        $resource->showKey = true;

        return $this->api->success(
            'ApiKey has been created successfully',
            $resource,
            201,
        );
    }

    public function show(ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('view', $apiKey);

        if ($apiKey->trashed()) {
            return $this->api->notFound('ApiKey has been deleted.');
        }

        return $this->api->success(
            'ApiKey retrieved successfully',
            new ApiKeyResource($apiKey),
        );
    }

    public function update(UpdateApiKeyRequest $request, ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('update', $apiKey);

        if ($apiKey->trashed()) {
            return $this->api->notFound('Cannot update a deleted apiKey.');
        }

        $this->apiKeyService->update($apiKey, $request->validated());

        return $this->api->success(
            'ApiKey has been updated successfully',
            new ApiKeyResource($apiKey),
        );
    }

    public function destroy(ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('delete', $apiKey);

        if ($apiKey->trashed()) {
            return $this->api->notFound('ApiKey is already deleted.');
        }

        $apiKey->delete();

        return $this->api->success(
            'ApiKey has been deleted successfully',
            null,
            200,
        );
    }

    public function regenerate(ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('update', $apiKey);

        if ($apiKey->trashed()) {
            return $this->api->notFound('Cannot regenerate a deleted API key.');
        }

        $newKey = $this->apiKeyService->regenerate($apiKey);

        return $this->api->success(
            'API key has been regenerated successfully',
            ['key' => $newKey],
        );
    }

    public function revoke(ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('delete', $apiKey);

        if ($apiKey->trashed()) {
            return $this->api->notFound('API key is already deleted.');
        }

        $this->apiKeyService->revoke($apiKey);

        return $this->api->success(
            'API key has been revoked successfully',
            new ApiKeyResource($apiKey->fresh()),
        );
    }

    public function restore(ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('restore', $apiKey);

        if (! $apiKey->trashed()) {
            return $this->api->notFound('ApiKey is not deleted.');
        }

        $apiKey->restore();

        return $this->api->success(
            'ApiKey has been restored successfully',
            new ApiKeyResource($apiKey),
        );
    }

    public function forceDelete(ApiKey $apiKey): JsonResponse
    {
        Gate::authorize('forceDelete', $apiKey);

        if (! $apiKey->trashed()) {
            return $this->api->error('ApiKey must be deleted before force deleting.', 400);
        }

        $apiKey->forceDelete();

        return $this->api->success(
            'ApiKey has been force deleted successfully',
            null,
            200,
        );
    }
}

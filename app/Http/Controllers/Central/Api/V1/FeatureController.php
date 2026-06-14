<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Feature\StoreFeatureRequest;
use App\Http\Requests\Central\Api\V1\Feature\UpdateFeatureRequest;
use App\Http\Resources\Central\Api\V1\Feature\FeatureResource;
use App\Http\Resources\Central\Api\V1\Feature\ListFeatureResource;
use App\Models\Feature;
use App\Services\ApiResponseService;
use App\Services\Central\FeatureService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class FeatureController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly FeatureService $featureService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Feature::class);

        $features = $this->featureService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'features retrieved successfully',
            ListFeatureResource::collection($features),
        );
    }

    public function store(StoreFeatureRequest $request): JsonResponse
    {
        Gate::authorize('create', Feature::class);

        $feature = $this->featureService->create($request->validated());

        return $this->api->success(
            'Feature has been created successfully',
            new FeatureResource($feature),
            201,
        );
    }

    public function show(Feature $feature): JsonResponse
    {
        Gate::authorize('view', $feature);

        if ($feature->trashed()) {
            return $this->api->notFound('Feature has been deleted.');
        }

        return $this->api->success(
            'Feature retrieved successfully',
            new FeatureResource($feature),
        );
    }

    public function update(UpdateFeatureRequest $request, Feature $feature): JsonResponse
    {
        Gate::authorize('update', $feature);

        if ($feature->trashed()) {
            return $this->api->notFound('Cannot update a deleted feature.');
        }

        $this->featureService->update($feature, $request->validated());

        return $this->api->success(
            'Feature has been updated successfully',
            new FeatureResource($feature),
        );
    }

    public function destroy(Feature $feature): JsonResponse
    {
        Gate::authorize('delete', $feature);

        if ($feature->trashed()) {
            return $this->api->notFound('Feature is already deleted.');
        }

        if ($feature->plans()->count() > 0) {
            return $this->api->error(
                'Cannot delete a feature that is attached to one or more plans. Remove it from all plans first.',
                409,
            );
        }

        $feature->delete();

        return $this->api->success(
            'Feature has been deleted successfully',
            null,
            200,
        );
    }

    public function restore(Feature $feature): JsonResponse
    {
        Gate::authorize('restore', $feature);

        if (! $feature->trashed()) {
            return $this->api->notFound('Feature is not deleted.');
        }

        $feature->restore();

        return $this->api->success(
            'Feature has been restored successfully',
            new FeatureResource($feature),
        );
    }

    public function forceDelete(Feature $feature): JsonResponse
    {
        Gate::authorize('forceDelete', $feature);

        if (! $feature->trashed()) {
            return $this->api->error('Feature must be deleted before force deleting.', 400);
        }

        $feature->forceDelete();

        return $this->api->success(
            'Feature has been force deleted successfully',
            null,
            200,
        );
    }

    public function active(Feature $feature): JsonResponse
    {
        Gate::authorize('update', $feature);

        if ($feature->trashed()) {
            return $this->api->notFound('Cannot update a deleted feature.');
        }

        $this->featureService->isActive($feature);

        return $this->api->success(
            'Feature is now active successfully',
            new FeatureResource($feature),
        );
    }

    public function inactive(Feature $feature): JsonResponse
    {
        Gate::authorize('update', $feature);

        if ($feature->trashed()) {
            return $this->api->notFound('Cannot update a deleted feature.');
        }

        $this->featureService->isInactive($feature);

        return $this->api->success(
            'Feature is now inactive successfully',
            new FeatureResource($feature),
        );
    }
}

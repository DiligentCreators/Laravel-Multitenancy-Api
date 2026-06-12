<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Plan\AttachPlanFeatureRequest;
use App\Http\Requests\Central\Api\V1\Plan\UpdatePlanFeatureValueRequest;
use App\Http\Resources\Central\Api\V1\Plan\PlanFeatureResource;
use App\Models\Feature;
use App\Models\Plan;
use App\Services\ApiResponseService;
use App\Services\Central\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PlanFeatureController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PlanService $planService,
    ) {
        parent::__construct($api);
    }

    public function index(Plan $plan): JsonResponse
    {
        Gate::authorize('view', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Plan has been deleted.');
        }

        $features = $this->planService->getFeatures($plan);

        return $this->api->success(
            'Plan features retrieved successfully',
            PlanFeatureResource::collection($features),
        );
    }

    public function store(AttachPlanFeatureRequest $request, Plan $plan): JsonResponse
    {
        Gate::authorize('update', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Cannot update a deleted plan.');
        }

        $plan = $this->planService->attachFeature($plan, $request->validated());

        return $this->api->success(
            'Feature has been attached to plan successfully',
            PlanFeatureResource::collection($plan->features),
            201,
        );
    }

    public function update(UpdatePlanFeatureValueRequest $request, Plan $plan, Feature $feature): JsonResponse
    {
        Gate::authorize('update', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Cannot update a deleted plan.');
        }

        $plan = $this->planService->updateFeatureValue($plan, $feature, $request->validated());

        return $this->api->success(
            'Feature value has been updated successfully',
            PlanFeatureResource::collection($plan->features),
        );
    }

    public function destroy(Plan $plan, Feature $feature): JsonResponse
    {
        Gate::authorize('update', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Cannot update a deleted plan.');
        }

        $plan = $this->planService->removeFeature($plan, $feature);

        return $this->api->success(
            'Feature has been removed from plan successfully',
            PlanFeatureResource::collection($plan->features),
        );
    }
}

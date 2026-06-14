<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Plan\StorePlanRequest;
use App\Http\Requests\Central\Api\V1\Plan\UpdatePlanRequest;
use App\Http\Resources\Central\Api\V1\Plan\ListPlanResource;
use App\Http\Resources\Central\Api\V1\Plan\PlanResource;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\ApiResponseService;
use App\Services\Central\PlanService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class PlanController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly PlanService $planService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Plan::class);

        $plans = $this->planService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'plans retrieved successfully',
            ListPlanResource::collection($plans),
        );
    }

    public function store(StorePlanRequest $request): JsonResponse
    {
        Gate::authorize('create', Plan::class);

        $plan = $this->planService->create($request->validated());

        return $this->api->success(
            'Plan has been created successfully',
            new PlanResource($plan),
            201,
        );
    }

    public function show(Plan $plan): JsonResponse
    {
        Gate::authorize('view', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Plan has been deleted.');
        }

        return $this->api->success(
            'Plan retrieved successfully',
            new PlanResource($plan),
        );
    }

    public function update(UpdatePlanRequest $request, Plan $plan): JsonResponse
    {
        Gate::authorize('update', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Cannot update a deleted plan.');
        }

        $this->planService->update($plan, $request->validated());

        return $this->api->success(
            'Plan has been updated successfully',
            new PlanResource($plan),
        );
    }

    public function destroy(Plan $plan): JsonResponse
    {
        Gate::authorize('delete', $plan);

        if ($plan->trashed()) {
            return $this->api->notFound('Plan is already deleted.');
        }

        if (Subscription::where('plan_id', $plan->id)->active()->exists()) {
            return $this->api->error(
                'Cannot delete a plan that has active subscriptions. End or cancel related subscriptions first.',
                409,
            );
        }

        $plan->delete();

        return $this->api->success(
            'Plan has been deleted successfully',
            null,
            200,
        );
    }

    public function restore(Plan $plan): JsonResponse
    {
        Gate::authorize('restore', $plan);

        if (! $plan->trashed()) {
            return $this->api->notFound('Plan is not deleted.');
        }

        $plan->restore();

        return $this->api->success(
            'Plan has been restored successfully',
            new PlanResource($plan),
        );
    }

    public function forceDelete(Plan $plan): JsonResponse
    {
        Gate::authorize('forceDelete', $plan);

        if (! $plan->trashed()) {
            return $this->api->error('Plan must be deleted before force deleting.', 400);
        }

        $plan->forceDelete();

        return $this->api->success(
            'Plan has been force deleted successfully',
            null,
            200,
        );
    }
}

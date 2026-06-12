<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Central\Api\V1\Subscription\StoreSubscriptionRequest;
use App\Http\Requests\Central\Api\V1\Subscription\UpdateSubscriptionRequest;
use App\Http\Resources\Central\Api\V1\Subscription\ListSubscriptionResource;
use App\Http\Resources\Central\Api\V1\Subscription\SubscriptionResource;
use App\Models\Subscription;
use App\Services\ApiResponseService;
use App\Services\Central\SubscriptionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class SubscriptionController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly SubscriptionService $subscriptionService,
    ) {
        parent::__construct($api);
    }

    public function index(): JsonResponse
    {
        Gate::authorize('viewAny', Subscription::class);

        $subscriptions = $this->subscriptionService->paginate(
            request(),
            $this->perPage(request()),
        );

        return $this->api->success(
            'subscriptions retrieved successfully',
            ListSubscriptionResource::collection($subscriptions),
        );
    }

    public function store(StoreSubscriptionRequest $request): JsonResponse
    {
        Gate::authorize('create', Subscription::class);

        $subscription = $this->subscriptionService->create($request->validated());

        return $this->api->success(
            'Subscription has been created successfully',
            new SubscriptionResource($subscription),
            201,
        );
    }

    public function show(Subscription $subscription): JsonResponse
    {
        Gate::authorize('view', $subscription);

        if ($subscription->trashed()) {
            return $this->api->notFound('Subscription has been deleted.');
        }

        return $this->api->success(
            'Subscription retrieved successfully',
            new SubscriptionResource($subscription),
        );
    }

    public function update(UpdateSubscriptionRequest $request, Subscription $subscription): JsonResponse
    {
        Gate::authorize('update', $subscription);

        if ($subscription->trashed()) {
            return $this->api->notFound('Cannot update a deleted subscription.');
        }

        $this->subscriptionService->update($subscription, $request->validated());

        return $this->api->success(
            'Subscription has been updated successfully',
            new SubscriptionResource($subscription),
        );
    }

    public function destroy(Subscription $subscription): JsonResponse
    {
        Gate::authorize('delete', $subscription);

        if ($subscription->trashed()) {
            return $this->api->notFound('Subscription is already deleted.');
        }

        $subscription->delete();

        return $this->api->success(
            'Subscription has been deleted successfully',
            null,
            200,
        );
    }

    public function restore(Subscription $subscription): JsonResponse
    {
        Gate::authorize('restore', $subscription);

        if (! $subscription->trashed()) {
            return $this->api->notFound('Subscription is not deleted.');
        }

        $subscription->restore();

        return $this->api->success(
            'Subscription has been restored successfully',
            null,
            200,
        );
    }

    public function forceDelete(Subscription $subscription): JsonResponse
    {
        Gate::authorize('forceDelete', $subscription);

        if (! $subscription->trashed()) {
            return $this->api->notFound('Subscription is not deleted.');
        }

        $subscription->forceDelete();

        return $this->api->success(
            'Subscription has been force deleted successfully',
            null,
            200,
        );
    }
}

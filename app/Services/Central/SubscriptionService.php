<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SubscriptionService
{
    public function __construct(
        protected Subscription $subscription,
    ) {}

    public function query(Request $request): Builder
    {
        return $this->subscription
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $query->where(function (Builder $query) use ($search) {
                    $query->where('id', 'like', "%{$search}%");
                });
            })
            ->when(
                $request->input('trashed') === 'true',
                fn (Builder $query) => $query->withTrashed()
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->orderBy(
                $request->input('sort', 'created_at'),
                $request->input('direction', 'desc')
            );
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->paginate($perPage)
            ->withQueryString();
    }

    public function all(Request $request): Collection
    {
        return $this->query($request)->get();
    }

    public function find(int|string $id): Subscription
    {
        return $this->subscription
            ->query()
            ->withTrashed()
            ->findOrFail($id);
    }

    public function create(array $data): Subscription
    {
        $billingCycle = $data['billing_cycle'];

        $startsAt = Carbon::parse($data['starts_at']);

        if ($billingCycle == SubscriptionBillingCycleEnum::YEARLY->value) {
            $data['ends_at'] = $startsAt->addYear();
        } else {
            $data['ends_at'] = $startsAt->addMonth();
        }

        return $this->subscription->create($data);
    }

    public function update(Subscription $subscription, array $data): Subscription
    {
        if (isset($data['billing_cycle'], $data['starts_at'])) {
            $startsAt = Carbon::parse($data['starts_at']);

            $data['ends_at'] = $data['billing_cycle'] == SubscriptionBillingCycleEnum::YEARLY->value
                ? $startsAt->addYear()
                : $startsAt->addMonth();
        }

        $subscription->update($data);

        return $subscription;
    }

    public function getCurrentSubscription(Tenant $tenant): ?Subscription
    {
        return $tenant->currentSubscription();
    }

    public function hasActiveSubscription(Tenant $tenant): bool
    {
        return $tenant->hasActiveSubscription();
    }

    public function validateSubscription(Tenant $tenant): array
    {
        $subscription = $this->getCurrentSubscription($tenant);

        if ($subscription === null) {
            return [
                'valid' => false,
                'status' => 'no_subscription',
                'message' => 'No active subscription found. Please subscribe to a plan.',
            ];
        }

        if ($subscription->isSuspended()) {
            return [
                'valid' => false,
                'status' => 'suspended',
                'message' => 'Your subscription has been suspended. Please contact support.',
            ];
        }

        if ($subscription->isExpired()) {
            return [
                'valid' => false,
                'status' => 'expired',
                'message' => 'Your subscription has expired. Please renew to continue.',
            ];
        }

        if ($subscription->isCancelled()) {
            return [
                'valid' => false,
                'status' => 'cancelled',
                'message' => 'Your subscription has been cancelled. Please subscribe again.',
            ];
        }

        if (! $subscription->isCurrentlyActive()) {
            return [
                'valid' => false,
                'status' => 'inactive',
                'message' => 'Subscription is not active.',
            ];
        }

        return [
            'valid' => true,
            'status' => $subscription->status->value,
            'message' => 'Subscription is active.',
        ];
    }

    public function canAccessTenantFeatures(Tenant $tenant): bool
    {
        return $this->hasActiveSubscription($tenant);
    }

    public function hasFeature(Tenant $tenant, string $featureSlug): bool
    {
        $plan = $tenant->activePlan();

        if ($plan === null) {
            return false;
        }

        return $plan->hasFeature($featureSlug);
    }

    public function featureValue(Tenant $tenant, string $featureSlug): mixed
    {
        $plan = $tenant->activePlan();

        if ($plan === null) {
            return null;
        }

        return $plan->getFeatureValue($featureSlug);
    }

    public function checkFeatureLimit(Tenant $tenant, string $featureSlug, int $currentUsage): array
    {
        $limit = $this->featureValue($tenant, $featureSlug);

        if ($limit === null || $limit === false || $limit === '') {
            return [
                'allowed' => false,
                'current' => $currentUsage,
                'limit' => 0,
                'message' => "The '{$featureSlug}' feature is not available on your plan.",
            ];
        }

        $limitValue = (int) $limit;

        if ($currentUsage >= $limitValue) {
            return [
                'allowed' => false,
                'current' => $currentUsage,
                'limit' => $limitValue,
                'message' => "{$featureSlug} limit reached ({$currentUsage}/{$limitValue}). Please upgrade your plan.",
            ];
        }

        return [
            'allowed' => true,
            'current' => $currentUsage,
            'limit' => $limitValue,
            'message' => 'Within limit.',
        ];
    }
}

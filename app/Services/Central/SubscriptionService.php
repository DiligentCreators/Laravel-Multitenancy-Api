<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Enums\Central\SubscriptionBillingCycleEnum;
use App\Enums\Central\SubscriptionStatusEnum;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\Tenant;
use Carbon\Carbon;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class SubscriptionService
{
    private const VALID_TRANSITIONS = [
        'trial' => ['active', 'expired', 'cancelled'],
        'active' => ['expired', 'cancelled', 'suspended'],
        'expired' => ['active'],
        'cancelled' => ['active'],
        'suspended' => ['active', 'expired'],
    ];

    public function __construct(
        protected Subscription $subscription,
    ) {}

    private const ALLOWED_SORT_COLUMNS = [
        'id', 'tenant_id', 'plan_id', 'status', 'starts_at', 'ends_at',
        'billing_cycle', 'created_at', 'updated_at',
    ];

    private const ALLOWED_DIRECTIONS = ['asc', 'desc'];

    public function query(Request $request): Builder
    {
        $sort = in_array($request->input('sort', 'created_at'), self::ALLOWED_SORT_COLUMNS, true)
            ? $request->input('sort', 'created_at')
            : 'created_at';

        $direction = in_array($request->input('direction', 'desc'), self::ALLOWED_DIRECTIONS, true)
            ? $request->input('direction', 'desc')
            : 'desc';

        return $this->subscription
            ->query()
            ->when($request->filled('search'), function (Builder $query) use ($request) {
                $search = $request->string('search')->toString();

                $ids = Subscription::search($search)->keys();
                $query->whereIn((new Subscription)->getQualifiedKeyName(), $ids);
            })
            ->when(
                $request->input('trashed') === 'true',
                fn (Builder $query) => $query->withTrashed()
            )
            ->when(
                $request->input('trashed') === 'only',
                fn (Builder $query) => $query->onlyTrashed()
            )
            ->orderBy($sort, $direction);
    }

    public function paginate(Request $request, int $perPage = 15): LengthAwarePaginator
    {
        return $this->query($request)
            ->with('tenant', 'plan')
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
            ->with('tenant', 'plan')
            ->findOrFail($id);
    }

    public function create(array $data): Subscription
    {
        $status = SubscriptionStatusEnum::tryFrom($data['status'] ?? 'trial');

        if ($status !== null && $status->isValidForAccess()) {
            $activeExists = $this->subscription
                ->query()
                ->where('tenant_id', $data['tenant_id'])
                ->active()
                ->exists();

            if ($activeExists) {
                throw new \RuntimeException('Tenant already has an active subscription. Cancel or end it before creating a new one.');
            }
        }

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
        if (isset($data['status'])) {
            $newStatus = $data['status'];
            $allowed = self::VALID_TRANSITIONS[$subscription->status->value] ?? [];

            if (! in_array($newStatus, $allowed, true)) {
                throw new \InvalidArgumentException(
                    "Cannot transition from {$subscription->status->value} to {$newStatus}."
                );
            }

            if ($newStatus === 'cancelled' && ! isset($data['cancelled_at'])) {
                $data['cancelled_at'] = Carbon::now();
            }

            if ($newStatus === 'suspended' && ! isset($data['suspended_at'])) {
                $data['suspended_at'] = Carbon::now();
            }
        }

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

    public function cancel(Subscription $subscription, ?Carbon $at = null): Subscription
    {
        if ($subscription->isCancelled()) {
            throw new \RuntimeException('Subscription is already cancelled.');
        }

        $subscription->update([
            'status' => SubscriptionStatusEnum::CANCELLED,
            'cancelled_at' => Carbon::now(),
            'ends_at' => $at ?? $subscription->ends_at ?? Carbon::now(),
        ]);

        return $subscription->fresh();
    }

    public function renew(Subscription $subscription, ?Plan $newPlan = null): Subscription
    {
        $plan = $newPlan ?? $subscription->plan;

        $startsAt = Carbon::now();
        $endsAt = $subscription->billing_cycle === SubscriptionBillingCycleEnum::YEARLY
            ? $startsAt->copy()->addYear()
            : $startsAt->copy()->addMonth();

        $subscription->update([
            'plan_id' => $plan->id,
            'status' => SubscriptionStatusEnum::ACTIVE,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);

        return $subscription->fresh();
    }

    public function isTrialAvailable(Tenant $tenant, Plan $plan): bool
    {
        if ($plan->trial_days <= 0) {
            return false;
        }

        return ! $this->subscription
            ->query()
            ->where('tenant_id', $tenant->id)
            ->where('plan_id', $plan->id)
            ->where('status', SubscriptionStatusEnum::TRIAL)
            ->exists();
    }

    public function expire(string $tenantId): ?Subscription
    {
        $subscription = $this->subscription
            ->query()
            ->where('tenant_id', $tenantId)
            ->active()
            ->where('ends_at', '<', Carbon::now())
            ->first();

        if ($subscription === null) {
            return null;
        }

        $subscription->update([
            'status' => SubscriptionStatusEnum::EXPIRED,
        ]);

        return $subscription->fresh();
    }
}

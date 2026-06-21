<?php

namespace App\Services\Crm;

use App\Models\Crm\FeatureDefinition;
use App\Models\Crm\PlanFeature;
use App\Models\Crm\TenantFeatureOverride;
use App\Models\Crm\UsageCounter;
use App\Models\Tenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FeatureGateService
{
    private const CACHE_TTL = 3600;

    public function allows(Tenant $tenant, string $feature): bool
    {
        return $this->resolve($tenant, $feature)->allowed;
    }

    public function assert(Tenant $tenant, string $feature): void
    {
        $resolution = $this->resolve($tenant, $feature);

        if (! $resolution->allowed) {
            throw new FeatureGateException(
                "Feature '{$feature}' is not available: {$resolution->reason}",
                $resolution->deniedBy
            );
        }
    }

    public function limit(Tenant $tenant, string $feature): ?int
    {
        return $this->resolve($tenant, $feature)->limit;
    }

    public function usage(Tenant $tenant, string $feature): int
    {
        $counter = UsageCounter::where('tenant_id', $tenant->id)
            ->where('feature_key', $feature)
            ->first();

        return $counter ? $counter->count : 0;
    }

    public function remaining(Tenant $tenant, string $feature): ?int
    {
        $resolution = $this->resolve($tenant, $feature);

        if ($resolution->limit === null) {
            return null;
        }

        return max(0, $resolution->limit - $resolution->usage);
    }

    public function resolve(Tenant $tenant, string $feature): FeatureResolution
    {
        $version = Cache::get("crm:{$tenant->id}:feature:v", 0);
        $cacheKey = "crm:{$tenant->id}:v{$version}:{$feature}";

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($tenant, $feature) {
            return $this->doResolve($tenant, $feature);
        });
    }

    public function invalidate(Tenant $tenant, ?string $feature = null): void
    {
        if ($feature) {
            $version = Cache::get("crm:{$tenant->id}:feature:v", 0);
            Cache::forget("crm:{$tenant->id}:v{$version}:{$feature}");
        } else {
            Cache::increment("crm:{$tenant->id}:feature:v");
        }
    }

    private function doResolve(Tenant $tenant, string $feature): FeatureResolution
    {
        if (! $tenant->hasActiveSubscription()) {
            return new FeatureResolution(false, deniedBy: 'subscription', reason: 'No active subscription');
        }

        $definition = FeatureDefinition::where('key', $feature)->first();

        if (! $definition) {
            return new FeatureResolution(false, deniedBy: 'definition', reason: "Feature '{$feature}' is not defined");
        }

        $planValue = $this->getPlanFeatureValue($tenant, $feature);
        $overrideValue = $this->getOverrideValue($tenant, $feature);

        $effectiveValue = $overrideValue ?? $planValue ?? $definition->default_value;

        if ($definition->type === 'boolean') {
            $allowed = (bool) $effectiveValue;

            return new FeatureResolution($allowed, limit: $allowed ? 1 : 0);
        }

        if ($definition->type === 'integer' || $definition->type === 'float') {
            $limit = (int) $effectiveValue;

            if ($definition->is_usage_limit) {
                $currentUsage = $this->usage($tenant, $feature);
                $remaining = $limit - $currentUsage;

                if ($remaining <= 0) {
                    $overageAllowed = $this->isOverageAllowed($tenant);

                    if (! $overageAllowed) {
                        return new FeatureResolution(false, deniedBy: 'usage', reason: "Usage limit reached ({$currentUsage}/{$limit})", limit: $limit, usage: $currentUsage, isOverage: false);
                    }

                    return new FeatureResolution(true, limit: $limit, usage: $currentUsage, isOverage: true);
                }

                return new FeatureResolution(true, limit: $limit, usage: $currentUsage);
            }

            return new FeatureResolution(true, limit: $limit);
        }

        return new FeatureResolution(false, deniedBy: 'type', reason: "Unknown feature type '{$definition->type}'");
    }

    private function getPlanFeatureValue(Tenant $tenant, string $feature): mixed
    {
        $plan = $tenant->activePlan();

        if (! $plan) {
            return null;
        }

        /** @var PlanFeature|null $pivot */
        $pivot = $plan->crmFeatures()
            ->whereHas('definition', fn ($q) => $q->where('key', $feature))
            ->first();

        return $pivot?->value;
    }

    private function getOverrideValue(Tenant $tenant, string $feature): mixed
    {
        $override = TenantFeatureOverride::where('tenant_id', $tenant->id)
            ->whereHas('feature', fn ($q) => $q->where('key', $feature))
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        return $override?->value;
    }

    private function isOverageAllowed(Tenant $tenant): bool
    {
        $plan = $tenant->activePlan();

        if (! $plan) {
            return false;
        }

        $overageFeature = $plan->crmFeatures()
            ->whereHas('definition', fn ($q) => $q->where('key', 'overage.allowed'))
            ->first();

        return (bool) ($overageFeature?->value ?? false);
    }

    public function incrementUsage(Tenant $tenant, string $feature, int $amount = 1): void
    {
        DB::transaction(function () use ($tenant, $feature, $amount) {
            $counter = UsageCounter::where('tenant_id', $tenant->id)
                ->where('feature_key', $feature)
                ->first();

            if ($counter) {
                $counter->increment('count', $amount);
            } else {
                UsageCounter::create([
                    'tenant_id' => $tenant->id,
                    'feature_key' => $feature,
                    'count' => $amount,
                ]);
            }

            $this->invalidate($tenant, $feature);
        });
    }

    public function decrementUsage(Tenant $tenant, string $feature, int $amount = 1): void
    {
        DB::transaction(function () use ($tenant, $feature, $amount) {
            $counter = UsageCounter::where('tenant_id', $tenant->id)
                ->where('feature_key', $feature)
                ->first();

            if ($counter) {
                $counter->decrement('count', $amount);
            }

            $this->invalidate($tenant, $feature);
        });
    }
}

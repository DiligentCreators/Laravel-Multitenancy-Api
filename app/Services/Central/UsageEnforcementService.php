<?php

namespace App\Services\Central;

use App\Models\Subscription;
use App\Models\Tenant;

class UsageEnforcementService
{
    /**
     * Check if a tenant can perform an action based on usage limits.
     *
     * @return array{allowed: bool, reason: ?string, limit: ?int, current: ?int}
     */
    public function checkLimit(Tenant $tenant, string $featureSlug, int $required = 1): array
    {
        $subscription = Subscription::where('tenant_id', $tenant->id)
            ->whereIn('status', ['active', 'trial'])
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()))
            ->first();

        if (! $subscription) {
            return [
                'allowed' => false,
                'reason' => 'No active subscription found.',
                'limit' => 0,
                'current' => 0,
            ];
        }

        $plan = $subscription->plan;
        $featureValue = $plan->getFeatureValue($featureSlug);

        if ($featureValue === null || $featureValue === '' || $featureValue === '0') {
            return [
                'allowed' => false,
                'reason' => "Feature '{$featureSlug}' is not available on your plan.",
                'limit' => 0,
                'current' => 0,
            ];
        }

        if ($featureValue === '-1' || strtolower((string) $featureValue) === 'unlimited') {
            return [
                'allowed' => true,
                'reason' => null,
                'limit' => -1,
                'current' => 0,
            ];
        }

        $limit = (int) $featureValue;
        $usageService = app(UsageService::class);
        $usageResult = $usageService->checkLimit($tenant, $featureSlug);
        $current = $usageResult['used'] ?? 0;

        if (($current + $required) > $limit) {
            return [
                'allowed' => false,
                'reason' => "Usage limit exceeded for '{$featureSlug}'. Limit: {$limit}, Current: {$current}.",
                'limit' => $limit,
                'current' => $current,
            ];
        }

        return [
            'allowed' => true,
            'reason' => null,
            'limit' => $limit,
            'current' => $current,
        ];
    }
}

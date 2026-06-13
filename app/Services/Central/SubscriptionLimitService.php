<?php

declare(strict_types=1);

namespace App\Services\Central;

use App\Models\Tenant;

class SubscriptionLimitService
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
    ) {}

    public function checkLimit(Tenant $tenant, string $featureSlug, int $currentUsage): array
    {
        return $this->subscriptionService->checkFeatureLimit($tenant, $featureSlug, $currentUsage);
    }

    public function getLimit(Tenant $tenant, string $featureSlug): ?int
    {
        $value = $this->subscriptionService->featureValue($tenant, $featureSlug);

        if ($value === null || $value === false || $value === '') {
            return null;
        }

        return (int) $value;
    }
}

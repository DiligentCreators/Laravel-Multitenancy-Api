<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Models\OverageCharge;
use App\Models\Tenant;
use App\Services\Central\OverageService;
use App\Services\Central\UsageService;

class CalculateOverageAction
{
    public function __construct(
        protected UsageService $usageService,
        protected OverageService $overageService,
    ) {}

    public function execute(Tenant $tenant, string $feature, float $unitPrice, int $limit): ?OverageCharge
    {
        $usage = $this->usageService->checkLimit($tenant, $feature);

        if (! $usage['allowed'] && $usage['remaining'] < 0) {
            return null;
        }

        $overage = max(0, $usage['used'] - $limit);

        if ($overage <= 0) {
            return null;
        }

        return $this->overageService->calculateAndCharge($tenant, $feature, $overage, $unitPrice);
    }
}

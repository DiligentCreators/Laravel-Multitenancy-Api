<?php

declare(strict_types=1);

namespace App\Actions\Central;

use App\Models\Tenant;
use App\Services\Central\UsageService;

class CheckUsageAction
{
    public function __construct(
        protected UsageService $usageService,
    ) {}

    public function execute(Tenant $tenant, string $feature): array
    {
        return $this->usageService->checkLimit($tenant, $feature);
    }
}

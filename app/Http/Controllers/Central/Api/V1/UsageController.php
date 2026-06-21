<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Services\ApiResponseService;
use App\Services\Central\UsageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class UsageController extends Controller
{
    public function __construct(
        ApiResponseService $api,
        private readonly UsageService $usageService,
    ) {
        parent::__construct($api);
    }

    public function show(Tenant $tenant, string $feature): JsonResponse
    {
        Gate::authorize('view', $tenant);

        $usage = $this->usageService->checkLimit($tenant, $feature);

        return $this->api->success(
            'Usage retrieved successfully',
            $usage,
        );
    }

    public function reset(Tenant $tenant, string $feature): JsonResponse
    {
        Gate::authorize('update', $tenant);

        $this->usageService->reset($tenant, $feature);

        return $this->api->success(
            'Usage counter reset successfully',
        );
    }
}

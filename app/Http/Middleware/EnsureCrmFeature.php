<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\Crm\FeatureGateException;
use App\Services\Crm\FeatureGateService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureCrmFeature
{
    public function __construct(
        private readonly FeatureGateService $featureGate,
        private readonly ApiResponseService $api,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! tenancy()->initialized) {
            return $this->api->error('Tenant context is required.', 403);
        }

        try {
            $this->featureGate->assert(tenant(), $feature);
        } catch (FeatureGateException $e) {
            return $this->api->error($e->getMessage(), 403);
        }

        return $next($request);
    }
}

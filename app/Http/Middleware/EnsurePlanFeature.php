<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\Central\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePlanFeature
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly ApiResponseService $api,
    ) {}

    public function handle(Request $request, Closure $next, string $feature): Response
    {
        if (! tenancy()->initialized) {
            return $this->api->error('Tenant context is required.', 403);
        }

        $tenant = tenant();

        if (! $this->subscriptionService->hasActiveSubscription($tenant)) {
            return $this->api->error('No active subscription.', 402);
        }

        if (! $this->subscriptionService->hasFeature($tenant, $feature)) {
            return $this->api->error(
                "The '{$feature}' feature is not available on your current plan.",
                403,
            );
        }

        return $next($request);
    }
}

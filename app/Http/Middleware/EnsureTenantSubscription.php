<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Services\ApiResponseService;
use App\Services\Central\SubscriptionService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureTenantSubscription
{
    public function __construct(
        private readonly SubscriptionService $subscriptionService,
        private readonly ApiResponseService $api,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            return $this->api->error(
                'Tenant context is required.',
                403,
            );
        }

        $tenant = tenant();

        $validation = $this->subscriptionService->validateSubscription($tenant);

        if (! $validation['valid']) {
            $statusCode = match ($validation['status']) {
                'suspended' => 403,
                default => 402,
            };

            return $this->api->error(
                $validation['message'],
                $statusCode,
            );
        }

        return $next($request);
    }
}

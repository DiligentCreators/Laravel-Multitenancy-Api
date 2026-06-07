<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Domain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/*
 * Flexible tenant initialization middleware.
 *
 * Resolves the current tenant using multiple strategies (in order):
 *
 *   1. Domain  — Matches the request host against the domains table
 *                Works for: https://tenant1.domain.com/...
 *
 *   2. Header  — Reads X-Tenant-Domain or X-Tenant headers
 *                Works for: curl -H "X-Tenant-Domain: tenant1.domain.com" ...
 *
 *   3. Input   — Reads tenant_id / tenant_domain from request body/query
 *                Works for: POST /api/v1/tenant/auth/login with JSON body
 *
 * This allows tenant routes to be accessed from both tenant domains AND
 * the central domain, making the API accessible regardless of DNS setup.
 *
 * Future resolution strategies (subdomain, slug, JWT claim, etc.) can be
 * added by extending the resolveTenant() method.
 *
 * Route usage:
 *   Route::middleware('tenancy')->group(function () { ... });
 */
class InitializeTenancy
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! tenancy()->initialized) {
            $tenant = $this->resolveTenant($request);

            if ($tenant) {
                tenancy()->initialize($tenant);
            }
        }

        return $next($request);
    }

    protected function resolveTenant(Request $request): ?Tenant
    {
        // 1. Domain-based resolution (tenant1.domain.com → Domain → Tenant)
        $domain = Domain::where('domain', $request->getHost())->first();

        if ($domain) {
            return $domain->tenant;
        }

        // 2. Header-based resolution
        $identifier = $request->header('X-Tenant-Domain')
                   ?? $request->header('X-Tenant');

        if ($identifier !== null) {
            $tenant = $this->findTenant($identifier);

            if ($tenant !== null) {
                return $tenant;
            }
        }

        // 3. Request input (body / query parameter)
        $input = $request->input('tenant_domain')
              ?? $request->input('tenant_id');

        if (is_string($input)) {
            return $this->findTenant($input);
        }

        return null;
    }

    protected function findTenant(string $identifier): ?Tenant
    {
        // Try as domain first
        $domain = Domain::where('domain', $identifier)->first();

        if ($domain) {
            return $domain->tenant;
        }

        // Try as tenant ID (UUID)
        return Tenant::find($identifier);
    }
}

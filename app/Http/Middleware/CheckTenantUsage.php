<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Services\Central\UsageEnforcementService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckTenantUsage
{
    public function __construct(
        private readonly UsageEnforcementService $usageService,
    ) {}

    public function handle(Request $request, Closure $next, string $featureSlug, int $required = 1): Response
    {
        $tenantId = $request->route('tenant')
            ?? $request->route('tenant_id')
            ?? $request->input('tenant_id');

        if (! $tenantId) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant ID is required.',
            ], 400);
        }

        $tenant = Tenant::find($tenantId);

        if (! $tenant) {
            return response()->json([
                'status' => 'error',
                'message' => 'Tenant not found.',
            ], 404);
        }

        $result = $this->usageService->checkLimit($tenant, $featureSlug, $required);

        if (! $result['allowed']) {
            return response()->json([
                'status' => 'error',
                'message' => $result['reason'],
                'data' => $result,
            ], 403);
        }

        return $next($request);
    }
}

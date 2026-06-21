<?php

declare(strict_types=1);

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ApiResponseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ImpersonationController extends Controller
{
    public function __construct(
        ApiResponseService $api,
    ) {
        parent::__construct($api);
    }

    public function start(Tenant $tenant): JsonResponse
    {
        Gate::authorize('view', $tenant);

        $tenantUser = User::where('tenant_id', $tenant->id)->first();

        if (! $tenantUser) {
            return $this->api->error('No user found for this tenant.', 404);
        }

        tenancy()->initialize($tenant);

        $token = $tenantUser->createToken('impersonation-token', ['*'])->plainTextToken;

        activity()
            ->causedBy(Auth::user())
            ->event('impersonation.started')
            ->withProperties(['tenant_id' => $tenant->id, 'tenant_name' => $tenant->company_name])
            ->log('Central admin impersonated tenant: '.$tenant->company_name);

        tenancy()->end();

        return $this->api->success(
            'Impersonation started successfully',
            [
                'token' => $token,
                'type' => 'Bearer',
                'tenant' => [
                    'id' => $tenant->id,
                    'company_name' => $tenant->company_name,
                ],
                'user' => [
                    'id' => $tenantUser->id,
                    'name' => $tenantUser->name,
                    'email' => $tenantUser->email,
                ],
            ],
        );
    }

    public function stop(): JsonResponse
    {
        if (tenancy()->initialized) {
            tenancy()->end();
        }

        activity()
            ->causedBy(Auth::user())
            ->event('impersonation.stopped')
            ->log('Central admin stopped impersonation');

        return $this->api->success('Impersonation stopped successfully');
    }
}

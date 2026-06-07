<?php

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return $this->api->success(
            message: 'Dashboard data retrieved',
            data: [
                'stats' => [
                    'total_tenants' => 0,
                    'active_tenants' => 0,
                    'total_users' => 0,
                ],
            ],
        );
    }
}

<?php

namespace App\Http\Controllers\Tenant\Api\V1;

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
                    'total_clients' => 0,
                    'active_clients' => 0,
                    'total_users' => 0,
                ],
            ],
        );
    }
}

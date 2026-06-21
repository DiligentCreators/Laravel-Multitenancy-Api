<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
        ];

        $healthy = collect($checks)->every(fn ($check) => $check['healthy']);

        return response()->json([
            'status' => $healthy ? 'healthy' : 'degraded',
            'timestamp' => now()->toIso8601String(),
            'checks' => $checks,
        ], $healthy ? 200 : 503);
    }

    private function checkDatabase(): array
    {
        try {
            DB::select('SELECT 1');

            return ['healthy' => true, 'message' => 'Connected'];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }

    private function checkCache(): array
    {
        try {
            $key = 'health:'.now()->timestamp;
            Cache::put($key, true, 1);
            $value = Cache::get($key);
            Cache::forget($key);

            return ['healthy' => $value === true, 'message' => $value === true ? 'Reachable' : 'Read/write mismatch'];
        } catch (\Throwable $e) {
            return ['healthy' => false, 'message' => $e->getMessage()];
        }
    }
}

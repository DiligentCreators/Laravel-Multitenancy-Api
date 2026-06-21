<?php

namespace App\Services\Central;

use App\Models\AdminAuditLog;
use App\Models\CentralUser;
use Illuminate\Http\Request;

class AdminAuditService
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function log(CentralUser $user, string $action, ?Request $request = null, array $context = []): AdminAuditLog
    {
        return AdminAuditLog::create([
            'central_user_id' => $user->id,
            'action' => $action,
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'context' => $context,
        ]);
    }

    /**
     * @return array{data: mixed, total: int, per_page: int}
     */
    public function paginate(int $perPage = 50): array
    {
        $logs = AdminAuditLog::with('centralUser')
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return [
            'data' => $logs->items(),
            'total' => $logs->total(),
            'per_page' => $logs->perPage(),
        ];
    }
}

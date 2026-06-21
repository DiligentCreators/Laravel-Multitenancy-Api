<?php

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdminAuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminAuditLogController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'per_page' => 'integer|min:1|max:100',
            'action' => 'string|nullable',
            'user_id' => 'integer|nullable|exists:users,id',
        ]);

        $query = AdminAuditLog::with('centralUser:id,name,email');

        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }

        if (! empty($validated['user_id'])) {
            $query->where('central_user_id', $validated['user_id']);
        }

        $logs = $query->orderByDesc('created_at')
            ->paginate($validated['per_page'] ?? 50);

        return response()->json([
            'status' => 'success',
            'message' => 'Audit logs retrieved.',
            'data' => $logs->items(),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'per_page' => $logs->perPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}

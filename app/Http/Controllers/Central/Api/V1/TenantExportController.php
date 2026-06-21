<?php

namespace App\Http\Controllers\Central\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\TenantExportRecord;
use App\Services\Central\TenantDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TenantExportController extends Controller
{
    public function __construct(
        private readonly TenantDataService $dataService,
    ) {}

    public function export(Request $request, Tenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'type' => 'string|in:full,settings,users,activity',
            'format' => 'string|in:json,csv',
        ]);

        $user = $request->user();

        $result = $this->dataService->export(
            $tenant,
            $validated['type'] ?? 'full',
            $validated['format'] ?? 'json',
            $user
        );

        return response()->json([
            'status' => 'success',
            'message' => 'Tenant export completed.',
            'data' => [
                'record_id' => $result['record']->id,
                'file_path' => $result['file_path'],
                'size' => $result['size'],
                'status' => 'completed',
            ],
        ]);
    }

    public function history(Request $request, Tenant $tenant): JsonResponse
    {
        $records = TenantExportRecord::where('tenant_id', $tenant->id)
            ->with('centralUser')
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json([
            'status' => 'success',
            'message' => 'Export history retrieved.',
            'data' => $records->items(),
            'meta' => [
                'current_page' => $records->currentPage(),
                'last_page' => $records->lastPage(),
                'per_page' => $records->perPage(),
                'total' => $records->total(),
            ],
        ]);
    }

    public function download(TenantExportRecord $tenantExportRecord): JsonResponse|BinaryFileResponse
    {
        if ($tenantExportRecord->status !== 'completed' || ! $tenantExportRecord->file_path) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export is not ready or file is missing.',
            ], 404);
        }

        $path = storage_path('app/'.$tenantExportRecord->file_path);

        if (! file_exists($path)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Export file not found.',
            ], 404);
        }

        return response()->download($path);
    }
}

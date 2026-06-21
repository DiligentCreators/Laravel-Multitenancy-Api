<?php

namespace App\Services;

use App\Models\Crm\UsageCounter;
use App\Models\Crm\WorkflowLog;
use Illuminate\Support\Facades\DB;

class MetricsService
{
    public function getJobFailureMetrics(): array
    {
        $failedJobs = DB::connection('central')->table('failed_jobs')
            ->select(DB::raw('COUNT(*) as total'), DB::raw('DATE(created_at) as date'))
            ->where('created_at', '>=', now()->subDays(7))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->toArray();

        return [
            'total_failed_7d' => DB::connection('central')->table('failed_jobs')
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
            'daily_breakdown' => $failedJobs,
            'queue_sizes' => [
                'timeline' => $this->getQueueSize('timeline'),
                'workflows' => $this->getQueueSize('workflows'),
                'default' => $this->getQueueSize('default'),
            ],
        ];
    }

    public function getWorkflowExecutionMetrics(): array
    {
        $recent = now()->subDays(7);
        $success = WorkflowLog::where('created_at', '>=', $recent)
            ->where('status', 'completed')
            ->count();
        $failed = WorkflowLog::where('created_at', '>=', $recent)
            ->where('status', 'failed')
            ->count();
        $total = WorkflowLog::where('created_at', '>=', $recent)->count();

        return [
            'total_7d' => $total,
            'completed_7d' => $success,
            'failed_7d' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 2) : 100.0,
        ];
    }

    public function getQueueHealthMetrics(): array
    {
        return [
            'queues' => [
                'timeline' => $this->getQueueHealth('timeline'),
                'workflows' => $this->getQueueHealth('workflows'),
                'default' => $this->getQueueHealth('default'),
            ],
            'recent_failures' => DB::connection('central')->table('failed_jobs')
                ->where('created_at', '>=', now()->subHours(24))
                ->count(),
        ];
    }

    public function getDocumentStorageMetrics(): array
    {
        $totalDocuments = DB::table('crm_documents')
            ->whereNull('deleted_at')
            ->count();

        $totalVersions = DB::table('crm_document_versions')->count();

        $totalStorageBytes = DB::table('crm_documents')
            ->whereNull('deleted_at')
            ->sum('file_size');

        $tenantsWithDocs = DB::table('crm_documents')
            ->whereNull('deleted_at')
            ->select('tenant_id', DB::raw('COUNT(*) as count'), DB::raw('SUM(file_size) as total_bytes'))
            ->groupBy('tenant_id')
            ->get()
            ->toArray();

        return [
            'total_documents' => $totalDocuments,
            'total_versions' => $totalVersions,
            'total_storage_bytes' => $totalStorageBytes,
            'total_storage_mb' => round($totalStorageBytes / (1024 * 1024), 2),
            'tenants_with_documents' => count($tenantsWithDocs),
        ];
    }

    public function getTenantStorageUsage(string $tenantId): array
    {
        $bytes = DB::table('crm_documents')
            ->where('tenant_id', $tenantId)
            ->whereNull('deleted_at')
            ->sum('file_size');

        $counter = UsageCounter::where('tenant_id', $tenantId)
            ->where('feature_key', 'documents.storage_mb')
            ->first();

        return [
            'tenant_id' => $tenantId,
            'storage_bytes' => (int) $bytes,
            'storage_mb' => round($bytes / (1024 * 1024), 2),
            'tracked_mb' => $counter?->count ?? 0,
        ];
    }

    private function getQueueSize(string $queue): int
    {
        return DB::connection('central')->table('jobs')
            ->where('queue', $queue)
            ->count();
    }

    private function getQueueHealth(string $queue): array
    {
        $pending = DB::connection('central')->table('jobs')
            ->where('queue', $queue)
            ->count();

        $failed24h = DB::connection('central')->table('failed_jobs')
            ->where('queue', $queue)
            ->where('created_at', '>=', now()->subHours(24))
            ->count();

        return [
            'pending' => $pending,
            'failed_24h' => $failed24h,
            'healthy' => $pending < 100 && $failed24h < 10,
        ];
    }
}

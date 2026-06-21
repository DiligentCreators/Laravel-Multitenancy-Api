<?php

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorStorageUsage extends Command
{
    protected $signature = 'monitor:storage-usage';

    protected $description = 'Log document storage usage metrics';

    public function handle(MetricsService $metrics): int
    {
        $storageMetrics = $metrics->getDocumentStorageMetrics();

        $this->info('Document Storage Metrics');
        $this->newLine();

        $this->warn("  Total documents: {$storageMetrics['total_documents']}");
        $this->warn("  Total versions: {$storageMetrics['total_versions']}");
        $this->warn("  Total storage: {$storageMetrics['total_storage_mb']} MB");
        $this->warn("  Tenants with docs: {$storageMetrics['tenants_with_documents']}");

        if ($storageMetrics['total_storage_mb'] > 1000) {
            Log::info('Document storage usage threshold', $storageMetrics);
        }

        return Command::SUCCESS;
    }
}

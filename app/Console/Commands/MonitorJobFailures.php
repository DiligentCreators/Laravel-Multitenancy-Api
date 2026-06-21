<?php

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorJobFailures extends Command
{
    protected $signature = 'monitor:job-failures';

    protected $description = 'Log job failure metrics for alerting';

    public function handle(MetricsService $metrics): int
    {
        $jobMetrics = $metrics->getJobFailureMetrics();

        $this->info('Job Failure Metrics');
        $this->newLine();

        $this->warn("  Total failed (7d): {$jobMetrics['total_failed_7d']}");

        foreach ($jobMetrics['queue_sizes'] as $queue => $size) {
            $this->line("  Queue '{$queue}': {$size} pending");
        }

        if ($jobMetrics['total_failed_7d'] > 0) {
            Log::warning('Job failure threshold exceeded', [
                'total_failed_7d' => $jobMetrics['total_failed_7d'],
                'queue_sizes' => $jobMetrics['queue_sizes'],
            ]);
        }

        return Command::SUCCESS;
    }
}

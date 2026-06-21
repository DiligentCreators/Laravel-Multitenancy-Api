<?php

namespace App\Console\Commands;

use App\Services\MetricsService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorQueueHealth extends Command
{
    protected $signature = 'monitor:queue-health';

    protected $description = 'Check queue health and log metrics';

    public function handle(MetricsService $metrics): int
    {
        $health = $metrics->getQueueHealthMetrics();

        $this->info('Queue Health Report');
        $this->newLine();

        foreach ($health['queues'] as $name => $status) {
            $healthyText = $status['healthy'] ? '<fg=green>HEALTHY</>' : '<fg=red>UNHEALTHY</>';
            $this->warn("  {$name}: {$status['pending']} pending, {$status['failed_24h']} failed (24h) - {$healthyText}");

            if (! $status['healthy']) {
                Log::warning('Queue health check failed', [
                    'queue' => $name,
                    'pending' => $status['pending'],
                    'failed_24h' => $status['failed_24h'],
                ]);
            }
        }

        $this->newLine();
        $this->info("Total failed jobs (24h): {$health['recent_failures']}");

        return Command::SUCCESS;
    }
}

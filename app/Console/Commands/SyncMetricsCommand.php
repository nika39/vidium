<?php

namespace App\Console\Commands;

use App\Services\MetricSyncService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('metrics:sync')]
#[Description('Syncs analytics data from Redis to MySQL database')]
class SyncMetricsCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(MetricSyncService $syncService)
    {
        $this->info('Starting metrics sync process...');

        $startTime = microtime(true);

        $syncedCount = $syncService->sync();

        $executionTime = round((microtime(true) - $startTime) * 1000, 2);

        $this->info("Successfully synced {$syncedCount} metric records in {$executionTime} ms.");

        return self::SUCCESS;
    }
}

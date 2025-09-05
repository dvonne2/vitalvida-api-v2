<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Jobs\CleanupOldLogs;
use App\Services\LogCleanupService;

class CleanupLogs extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'logs:cleanup {--dry-run : Show what would be cleaned without actually doing it}';

    /**
     * The console command description.
     */
    protected $description = 'Clean up old logs based on retention policies';

    /**
     * Execute the console command.
     */
    public function handle(LogCleanupService $cleanupService): int
    {
        $this->info('Starting log cleanup process...');

        if ($this->option('dry-run')) {
            $this->info('DRY RUN MODE - No logs will be deleted');
            $statistics = $cleanupService->getLogStatistics();
            
            $this->table(
                ['Log Type', 'Total Records', 'Old Records', 'Retention Days'],
                [
                    ['Activity Logs', $statistics['activity_logs']['total'], $statistics['activity_logs']['old_logs'], $statistics['activity_logs']['retention_days']],
                    ['System Logs', $statistics['system_logs']['total'], $statistics['system_logs']['old_logs'], $statistics['system_logs']['retention_days']],
                    ['Security Events', $statistics['security_events']['total'], $statistics['security_events']['old_logs'], $statistics['security_events']['retention_days']]
                ]
            );

            $this->info('Dry run completed. Use without --dry-run to perform actual cleanup.');
            return 0;
        }

        try {
            $results = $cleanupService->cleanupOldLogs();
            
            $this->info('Log cleanup completed successfully!');
            
            $this->table(
                ['Log Type', 'Deleted Count', 'Retention Days'],
                [
                    ['Activity Logs', $results['activity_logs']['deleted_count'], $results['activity_logs']['retention_days']],
                    ['System Logs', $results['system_logs']['deleted_count'], $results['system_logs']['retention_days']],
                    ['Security Events', $results['security_events']['deleted_count'], $results['security_events']['retention_days']]
                ]
            );

            $totalDeleted = array_sum(array_column($results, 'deleted_count'));
            $this->info("Total records deleted: {$totalDeleted}");

            return 0;

        } catch (\Exception $e) {
            $this->error('Log cleanup failed: ' . $e->getMessage());
            return 1;
        }
    }
} 
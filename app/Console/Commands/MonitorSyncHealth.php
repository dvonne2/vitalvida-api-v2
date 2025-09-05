<?php

namespace App\Console\Commands;

use App\Services\SyncMonitoringService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MonitorSyncHealth extends Command
{
    protected $signature = 'integration:monitor-health 
                            {--auto-recover : Automatically trigger recovery actions}
                            {--alert-threshold=warning : Alert threshold (healthy|warning|unhealthy|critical)}
                            {--continuous : Run continuous monitoring}';

    protected $description = 'Monitor VitalVida-Role integration sync health and trigger auto-recovery';

    private $syncMonitoring;

    public function __construct(SyncMonitoringService $syncMonitoring)
    {
        parent::__construct();
        $this->syncMonitoring = $syncMonitoring;
    }

    public function handle()
    {
        $this->info('🔍 Starting sync health monitoring...');

        try {
            if ($this->option('continuous')) {
                $this->runContinuousMonitoring();
            } else {
                $this->runSingleCheck();
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Monitoring failed: {$e->getMessage()}");
            Log::error('Sync health monitoring command failed', [
                'error' => $e->getMessage()
            ]);
            return Command::FAILURE;
        }
    }

    private function runSingleCheck()
    {
        $healthReport = $this->syncMonitoring->monitorSyncHealth();
        $this->displayHealthReport($healthReport);

        if ($this->shouldTriggerRecovery($healthReport)) {
            if ($this->option('auto-recover')) {
                $this->info('🔧 Triggering auto-recovery...');
                $recoveryResult = $this->syncMonitoring->triggerAutoRecovery($healthReport);
                $this->displayRecoveryResult($recoveryResult);
            } else {
                $this->warn('⚠️ Health issues detected. Use --auto-recover to trigger recovery actions.');
            }
        }
    }

    private function runContinuousMonitoring()
    {
        $this->info('🔄 Running continuous monitoring (Ctrl+C to stop)...');
        
        while (true) {
            $healthReport = $this->syncMonitoring->monitorSyncHealth();
            
            $this->line('');
            $this->line('📊 Health Check: ' . now()->format('Y-m-d H:i:s'));
            $this->displayHealthSummary($healthReport);

            if ($this->shouldTriggerRecovery($healthReport) && $this->option('auto-recover')) {
                $this->info('🔧 Auto-recovery triggered');
                $this->syncMonitoring->triggerAutoRecovery($healthReport);
            }

            sleep(60); // Check every minute
        }
    }

    private function displayHealthReport(array $healthReport)
    {
        $this->info('📊 Sync Health Report:');
        $this->line('');

        // Overall health
        $healthIcon = $this->getHealthIcon($healthReport['overall_health']);
        $this->line("Overall Health: {$healthIcon} {$healthReport['overall_health']} ({$healthReport['health_score']}%)");
        $this->line('');

        // Individual checks
        $headers = ['Check', 'Status', 'Details'];
        $rows = [];

        foreach ($healthReport['checks'] as $checkName => $check) {
            $statusIcon = $this->getHealthIcon($check['status']);
            $details = $this->formatCheckDetails($check);
            
            $rows[] = [
                ucwords(str_replace('_', ' ', $checkName)),
                "{$statusIcon} {$check['status']}",
                $details
            ];
        }

        $this->table($headers, $rows);
    }

    private function displayHealthSummary(array $healthReport)
    {
        $healthIcon = $this->getHealthIcon($healthReport['overall_health']);
        $this->line("{$healthIcon} {$healthReport['overall_health']} ({$healthReport['health_score']}%)");
        
        $unhealthyChecks = array_filter($healthReport['checks'], fn($check) => $check['status'] !== 'healthy');
        if (!empty($unhealthyChecks)) {
            foreach ($unhealthyChecks as $name => $check) {
                $icon = $this->getHealthIcon($check['status']);
                $this->line("  {$icon} {$name}: {$check['status']}");
            }
        }
    }

    private function displayRecoveryResult(array $recoveryResult)
    {
        if ($recoveryResult['success']) {
            $this->info("✅ Recovery completed: {$recoveryResult['actions_taken']} actions taken");
            
            foreach ($recoveryResult['recovery_actions'] as $action) {
                $this->line("  ✓ {$action['action']}");
            }
        } else {
            $this->error("❌ Recovery failed: {$recoveryResult['error']}");
        }
    }

    private function shouldTriggerRecovery(array $healthReport): bool
    {
        $threshold = $this->option('alert-threshold');
        
        $severityLevels = [
            'healthy' => 4,
            'warning' => 3,
            'unhealthy' => 2,
            'critical' => 1
        ];

        $currentLevel = $severityLevels[$healthReport['overall_health']] ?? 1;
        $thresholdLevel = $severityLevels[$threshold] ?? 3;

        return $currentLevel <= $thresholdLevel;
    }

    private function formatCheckDetails(array $check): string
    {
        $details = [];

        if (isset($check['success_rate'])) {
            $details[] = "Rate: {$check['success_rate']}%";
        }
        
        if (isset($check['total_conflicts'])) {
            $details[] = "Conflicts: {$check['total_conflicts']}";
        }
        
        if (isset($check['total_queue_size'])) {
            $details[] = "Queue: {$check['total_queue_size']}";
        }
        
        if (isset($check['memory_usage_percent'])) {
            $details[] = "Memory: {$check['memory_usage_percent']}%";
        }
        
        if (isset($check['response_time_ms'])) {
            $details[] = "Response: {$check['response_time_ms']}ms";
        }

        if (isset($check['error'])) {
            $details[] = "Error: " . substr($check['error'], 0, 50);
        }

        return implode(', ', $details) ?: 'OK';
    }

    private function getHealthIcon(string $status): string
    {
        return match($status) {
            'healthy' => '✅',
            'warning' => '⚠️',
            'unhealthy' => '🔴',
            'critical' => '🚨',
            default => '❓'
        };
    }
}

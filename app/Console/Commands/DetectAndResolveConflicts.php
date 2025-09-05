<?php

namespace App\Console\Commands;

use App\Services\ConflictDetectionService;
use App\Services\ConflictResolutionService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class DetectAndResolveConflicts extends Command
{
    protected $signature = 'integration:resolve-conflicts 
                            {--auto : Automatically resolve conflicts where possible}
                            {--type= : Specific conflict type to resolve}
                            {--agent= : Specific agent ID to check}
                            {--dry-run : Show conflicts without resolving}';

    protected $description = 'Detect and resolve conflicts between VitalVida and Role systems';

    private $conflictDetection;
    private $conflictResolution;

    public function __construct(
        ConflictDetectionService $conflictDetection,
        ConflictResolutionService $conflictResolution
    ) {
        parent::__construct();
        $this->conflictDetection = $conflictDetection;
        $this->conflictResolution = $conflictResolution;
    }

    public function handle()
    {
        $this->info('🔍 Starting conflict detection and resolution...');

        try {
            // Detect conflicts
            $this->info('Detecting conflicts...');
            $conflicts = $this->conflictDetection->detectAllConflicts();

            $totalConflicts = array_sum(array_map('count', $conflicts));
            
            if ($totalConflicts === 0) {
                $this->info('✅ No conflicts detected!');
                return Command::SUCCESS;
            }

            $this->displayConflictSummary($conflicts);

            if ($this->option('dry-run')) {
                $this->info('🏃 Dry run completed - no resolutions applied');
                return Command::SUCCESS;
            }

            // Auto-resolve if requested
            if ($this->option('auto')) {
                $this->info('🔧 Auto-resolving conflicts...');
                $results = $this->conflictResolution->autoResolveConflicts();
                $this->displayResolutionResults($results);
            } else {
                $this->info('💡 Use --auto flag to automatically resolve conflicts');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ Error: {$e->getMessage()}");
            Log::error('Conflict detection/resolution command failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return Command::FAILURE;
        }
    }

    private function displayConflictSummary(array $conflicts)
    {
        $this->info('📊 Conflict Summary:');
        $this->line('');

        $totalConflicts = 0;
        $severityCounts = ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0];
        $autoResolvable = 0;

        foreach ($conflicts as $category => $categoryConflicts) {
            if (empty($categoryConflicts)) continue;

            $this->line("📁 {$category}: " . count($categoryConflicts) . " conflicts");
            
            foreach ($categoryConflicts as $conflict) {
                $totalConflicts++;
                $severityCounts[$conflict['severity']]++;
                if ($conflict['auto_resolvable']) {
                    $autoResolvable++;
                }

                $icon = $this->getSeverityIcon($conflict['severity']);
                $this->line("  {$icon} {$conflict['type']}: {$conflict['description']}");
            }
            $this->line('');
        }

        $this->table(['Metric', 'Count'], [
            ['Total Conflicts', $totalConflicts],
            ['Critical', $severityCounts['critical']],
            ['High', $severityCounts['high']],
            ['Medium', $severityCounts['medium']],
            ['Low', $severityCounts['low']],
            ['Auto-Resolvable', $autoResolvable],
            ['Manual Resolution Required', $totalConflicts - $autoResolvable]
        ]);
    }

    private function displayResolutionResults(array $results)
    {
        $successful = array_filter($results, fn($r) => $r['success']);
        $failed = array_filter($results, fn($r) => !$r['success']);

        $this->info('🎯 Resolution Results:');
        $this->line('');

        $this->table(['Metric', 'Count'], [
            ['Total Attempted', count($results)],
            ['Successful', count($successful)],
            ['Failed', count($failed)],
            ['Success Rate', count($results) > 0 ? round((count($successful) / count($results)) * 100, 1) . '%' : '0%']
        ]);

        if (!empty($successful)) {
            $this->info('✅ Successful Resolutions:');
            foreach ($successful as $result) {
                $this->line("  ✓ {$result['conflict_type']}: {$result['action']}");
            }
        }

        if (!empty($failed)) {
            $this->error('❌ Failed Resolutions:');
            foreach ($failed as $result) {
                $this->line("  ✗ {$result['conflict_type']}: {$result['error']}");
            }
        }
    }

    private function getSeverityIcon(string $severity): string
    {
        return match($severity) {
            'critical' => '🚨',
            'high' => '⚠️',
            'medium' => '⚡',
            'low' => 'ℹ️',
            default => '📝'
        };
    }
}

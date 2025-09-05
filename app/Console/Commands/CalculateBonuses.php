<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\BonusCalculationService;
use App\Services\PayrollIntegrationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class CalculateBonuses extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'bonuses:calculate 
                            {--period-start= : Start date for bonus calculation (Y-m-d)}
                            {--period-end= : End date for bonus calculation (Y-m-d)}
                            {--type=* : Bonus types to calculate (performance,logistics,special)}
                            {--dry-run : Preview calculation without creating records}
                            {--employee= : Specific employee ID to calculate for}
                            {--force : Force calculation even if period overlaps existing bonuses}';

    /**
     * The console command description.
     */
    protected $description = 'Calculate bonuses automatically for employees based on performance metrics';

    protected $bonusService;
    protected $payrollService;

    /**
     * Create a new command instance.
     */
    public function __construct(BonusCalculationService $bonusService, PayrollIntegrationService $payrollService)
    {
        parent::__construct();
        $this->bonusService = $bonusService;
        $this->payrollService = $payrollService;
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('🚀 Starting bonus calculation process...');

        // Parse and validate inputs
        $periodStart = $this->option('period-start') ? 
            Carbon::parse($this->option('period-start')) : 
            now()->subMonth()->startOfMonth();

        $periodEnd = $this->option('period-end') ? 
            Carbon::parse($this->option('period-end')) : 
            now()->subMonth()->endOfMonth();

        $bonusTypes = $this->option('type') ?: ['performance', 'logistics'];
        $dryRun = $this->option('dry-run');
        $employeeId = $this->option('employee');
        $force = $this->option('force');

        // Display calculation parameters
        $this->table(['Parameter', 'Value'], [
            ['Period Start', $periodStart->format('Y-m-d')],
            ['Period End', $periodEnd->format('Y-m-d')],
            ['Bonus Types', implode(', ', $bonusTypes)],
            ['Dry Run', $dryRun ? 'Yes' : 'No'],
            ['Employee ID', $employeeId ?: 'All'],
            ['Force', $force ? 'Yes' : 'No']
        ]);

        if (!$this->confirm('Proceed with bonus calculation?')) {
            $this->warn('❌ Bonus calculation cancelled.');
            return Command::FAILURE;
        }

        try {
            $results = [];
            $totalBonuses = 0;
            $totalAmount = 0;

            // Calculate performance bonuses
            if (in_array('performance', $bonusTypes)) {
                $this->info('📊 Calculating performance bonuses...');
                
                if ($dryRun) {
                    $this->warn('   [DRY RUN] Performance bonus calculation preview');
                    $results['performance'] = ['message' => 'Performance bonus calculation would run here'];
                } else {
                    $performanceResults = $this->bonusService->calculatePerformanceBonuses($periodStart, $periodEnd);
                    $results['performance'] = $performanceResults;
                    
                    $totalBonuses += $performanceResults['total_calculated'];
                    $totalAmount += $performanceResults['total_amount'];
                    
                    $this->info("   ✅ Performance bonuses: {$performanceResults['total_calculated']} calculated, ₦" . number_format($performanceResults['total_amount'], 2));
                }
            }

            // Calculate logistics bonuses
            if (in_array('logistics', $bonusTypes)) {
                $this->info('🚚 Calculating logistics bonuses...');
                
                if ($dryRun) {
                    $this->warn('   [DRY RUN] Logistics bonus calculation preview');
                    $results['logistics'] = ['message' => 'Logistics bonus calculation would run here'];
                } else {
                    $logisticsResults = $this->bonusService->calculateLogisticsBonuses($periodStart, $periodEnd);
                    $results['logistics'] = $logisticsResults;
                    
                    $logisticsBonuses = count($logisticsResults);
                    $logisticsAmount = array_sum(array_column($logisticsResults, 'amount'));
                    
                    $totalBonuses += $logisticsBonuses;
                    $totalAmount += $logisticsAmount;
                    
                    $this->info("   ✅ Logistics bonuses: {$logisticsBonuses} calculated, ₦" . number_format($logisticsAmount, 2));
                }
            }

            // Display summary
            $this->newLine();
            $this->info('📋 Bonus Calculation Summary:');
            $this->table(['Metric', 'Value'], [
                ['Total Bonuses Created', $totalBonuses],
                ['Total Amount', '₦' . number_format($totalAmount, 2)],
                ['Period', $periodStart->format('M j, Y') . ' - ' . $periodEnd->format('M j, Y')],
                ['Execution Mode', $dryRun ? 'DRY RUN' : 'LIVE'],
                ['Status', $dryRun ? 'Preview Only' : 'Completed Successfully']
            ]);

            // Log results
            if (!$dryRun) {
                Log::info('Automated bonus calculation completed', [
                    'period_start' => $periodStart->toDateString(),
                    'period_end' => $periodEnd->toDateString(),
                    'bonus_types' => $bonusTypes,
                    'total_bonuses' => $totalBonuses,
                    'total_amount' => $totalAmount,
                    'results' => $results
                ]);

                $this->info('✅ Bonus calculation completed successfully!');
                
                // Ask if user wants to see detailed results
                if ($this->confirm('Show detailed calculation results?')) {
                    $this->displayDetailedResults($results);
                }
            } else {
                $this->info('📋 Dry run completed. No records were created.');
            }

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $this->error('❌ Bonus calculation failed: ' . $e->getMessage());
            Log::error('Automated bonus calculation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'options' => $this->options()
            ]);

            return Command::FAILURE;
        }
    }

    /**
     * Display detailed calculation results
     */
    private function displayDetailedResults(array $results): void
    {
        $this->newLine();
        $this->info('🔍 Detailed Calculation Results:');

        foreach ($results as $type => $typeResults) {
            $this->info("📊 " . ucfirst($type) . " Bonuses:");

            if (isset($typeResults['message'])) {
                $this->line("   " . $typeResults['message']);
                continue;
            }

            if ($type === 'performance') {
                $data = [
                    ['Individual Bonuses', $typeResults['individual_bonuses'] ?? 0],
                    ['Team Bonuses', $typeResults['team_bonuses'] ?? 0],
                    ['Company Bonuses', $typeResults['company_bonuses'] ?? 0],
                    ['Total Amount', '₦' . number_format($typeResults['total_amount'] ?? 0, 2)]
                ];

                if (!empty($typeResults['errors'])) {
                    $data[] = ['Errors', count($typeResults['errors'])];
                }

                $this->table(['Type', 'Count/Amount'], $data);

                if (!empty($typeResults['errors'])) {
                    $this->error('   Errors encountered:');
                    foreach ($typeResults['errors'] as $error) {
                        $this->line("   - " . $error);
                    }
                }
            }

            if ($type === 'logistics' && is_array($typeResults)) {
                $this->table(['Employee', 'Bonus Type', 'Amount'], array_map(function($bonus) {
                    return [
                        $bonus['employee_name'] ?? 'Unknown',
                        $bonus['bonus_type'] ?? 'logistics',
                        '₦' . number_format($bonus['amount'], 2)
                    ];
                }, $typeResults));
            }

            $this->newLine();
        }
    }

    /**
     * Get the console command help
     */
    public function getHelp(): string
    {
        return 'Calculate bonuses automatically based on performance metrics and business rules.

Examples:
  php artisan bonuses:calculate --dry-run
  php artisan bonuses:calculate --period-start=2025-01-01 --period-end=2025-01-31
  php artisan bonuses:calculate --type=performance --employee=123
  php artisan bonuses:calculate --period-start=2025-01-01 --period-end=2025-01-31 --type=performance --type=logistics';
    }
} 
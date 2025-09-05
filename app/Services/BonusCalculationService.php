<?php

namespace App\Services;

use App\Models\User;
use App\Models\BonusLog;
use App\Models\AgentPerformanceMetric;
use App\Models\DeliveryAgent;
use App\Models\HealthCriteriaLog;
use App\Models\ImDailyLog;
use App\Services\ThresholdValidationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BonusCalculationService
{
    protected $thresholdService;

    /**
     * Bonus calculation formulas and thresholds
     */
    private const BONUS_FORMULAS = [
        'performance' => [
            'individual' => [
                'min_percentage' => 5, // 5% of base salary
                'max_percentage' => 15, // 15% of base salary
                'base_threshold' => 80, // 80% success rate minimum
                'excellent_threshold' => 95, // 95% for maximum bonus
            ],
            'team' => [
                'min_percentage' => 2, // 2% of base salary
                'max_percentage' => 8, // 8% of base salary
                'team_threshold' => 85, // Team average 85%
            ],
            'company' => [
                'min_percentage' => 3, // 3% of base salary
                'max_percentage' => 10, // 10% of base salary
                'company_kpi_threshold' => 90, // Company KPI 90%
            ]
        ],
        'logistics' => [
            'delivery_efficiency' => [
                'min_amount' => 500,
                'max_amount' => 2000,
                'efficiency_threshold' => 85, // 85% efficiency minimum
                'excellence_threshold' => 98, // 98% for maximum
            ],
            'cost_optimization' => [
                'percentage_of_savings' => 1, // 1% of cost savings
                'max_percentage' => 3, // Maximum 3%
                'min_savings' => 5000, // Minimum ₦5,000 savings
            ],
            'quality_metrics' => [
                'min_amount' => 300,
                'max_amount' => 1500,
                'quality_score_threshold' => 85, // 85% quality minimum
            ]
        ],
        'special' => [
            'project_completion' => [
                'min_amount' => 5000,
                'max_amount' => 25000,
                'on_time_multiplier' => 1.5,
                'excellence_multiplier' => 2.0,
            ],
            'innovation' => [
                'min_amount' => 10000,
                'max_amount' => 50000,
                'impact_multiplier' => 1.0, // Based on business impact
            ],
            'retention' => [
                'percentage_of_salary' => 10, // 10% of annual salary
                'max_percentage' => 25, // Maximum 25%
                'years_multiplier' => 1.2, // 1.2x per year of service
            ]
        ]
    ];

    public function __construct(ThresholdValidationService $thresholdService)
    {
        $this->thresholdService = $thresholdService;
    }

    /**
     * Calculate performance bonuses for all eligible employees
     */
    public function calculatePerformanceBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $results = [
            'individual_bonuses' => 0,
            'team_bonuses' => 0,
            'company_bonuses' => 0,
            'total_calculated' => 0,
            'total_amount' => 0,
            'errors' => []
        ];

        try {
            DB::beginTransaction();

            // Calculate individual performance bonuses
            $individualBonuses = $this->calculateIndividualPerformanceBonuses($periodStart, $periodEnd);
            $results['individual_bonuses'] = count($individualBonuses);
            $results['total_amount'] += array_sum(array_column($individualBonuses, 'amount'));

            // Calculate team performance bonuses
            $teamBonuses = $this->calculateTeamPerformanceBonuses($periodStart, $periodEnd);
            $results['team_bonuses'] = count($teamBonuses);
            $results['total_amount'] += array_sum(array_column($teamBonuses, 'amount'));

            // Calculate company-wide bonuses
            $companyBonuses = $this->calculateCompanyPerformanceBonuses($periodStart, $periodEnd);
            $results['company_bonuses'] = count($companyBonuses);
            $results['total_amount'] += array_sum(array_column($companyBonuses, 'amount'));

            $results['total_calculated'] = $results['individual_bonuses'] + $results['team_bonuses'] + $results['company_bonuses'];

            DB::commit();

            Log::info('Performance bonus calculation completed', $results);

        } catch (\Exception $e) {
            DB::rollback();
            $results['errors'][] = $e->getMessage();
            Log::error('Performance bonus calculation failed', ['error' => $e->getMessage()]);
        }

        return $results;
    }

    /**
     * Calculate individual performance bonuses
     */
    private function calculateIndividualPerformanceBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        $formula = self::BONUS_FORMULAS['performance']['individual'];

        // Get performance metrics for the period
        $performers = AgentPerformanceMetric::with('deliveryAgent.user')
            ->whereBetween('metric_date', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('delivery_agent_id');

        foreach ($performers as $agentId => $metrics) {
            $agent = $metrics->first()->deliveryAgent;
            if (!$agent || !$agent->user) continue;

            $performanceData = $this->calculateAgentPerformanceScore($metrics);
            
            if ($performanceData['success_rate'] >= $formula['base_threshold']) {
                $baseSalary = $agent->user->base_salary ?? 50000; // Default if not set
                $bonusAmount = $this->calculatePerformanceBonusAmount($performanceData, $baseSalary, $formula);

                if ($bonusAmount > 0) {
                    $bonus = $this->createBonusLog([
                        'user_id' => $agent->user->id,
                        'bonus_type' => BonusLog::TYPE_PERFORMANCE,
                        'amount' => $bonusAmount,
                        'description' => "Individual performance bonus for {$performanceData['success_rate']}% success rate",
                        'period_start' => $periodStart,
                        'period_end' => $periodEnd,
                        'calculation_data' => $performanceData
                    ]);

                    $bonuses[] = $bonus;
                }
            }
        }

        return $bonuses;
    }

    /**
     * Calculate logistics bonuses based on delivery efficiency
     */
    public function calculateLogisticsBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        
        try {
            DB::beginTransaction();

            // Delivery efficiency bonuses
            $deliveryBonuses = $this->calculateDeliveryEfficiencyBonuses($periodStart, $periodEnd);
            $bonuses = array_merge($bonuses, $deliveryBonuses);

            // Cost optimization bonuses
            $costBonuses = $this->calculateCostOptimizationBonuses($periodStart, $periodEnd);
            $bonuses = array_merge($bonuses, $costBonuses);

            // Quality metrics bonuses
            $qualityBonuses = $this->calculateQualityMetricsBonuses($periodStart, $periodEnd);
            $bonuses = array_merge($bonuses, $qualityBonuses);

            DB::commit();

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Logistics bonus calculation failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $bonuses;
    }

    /**
     * Calculate delivery efficiency bonuses
     */
    private function calculateDeliveryEfficiencyBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        $formula = self::BONUS_FORMULAS['logistics']['delivery_efficiency'];

        $agents = AgentPerformanceMetric::with('deliveryAgent.user')
            ->whereBetween('metric_date', [$periodStart, $periodEnd])
            ->get()
            ->groupBy('delivery_agent_id');

        foreach ($agents as $agentId => $metrics) {
            $agent = $metrics->first()->deliveryAgent;
            if (!$agent || !$agent->user) continue;

            $avgSuccessRate = $metrics->avg('success_rate');
            $avgDeliveryTime = $metrics->avg('average_delivery_time');
            $totalDeliveries = $metrics->sum('deliveries_completed');

            if ($avgSuccessRate >= $formula['efficiency_threshold'] && $totalDeliveries >= 20) {
                // Calculate bonus based on efficiency
                $efficiencyScore = min($avgSuccessRate, 100);
                $timeBonus = max(0, 30 - $avgDeliveryTime) * 10; // Bonus for faster delivery
                
                $bonusAmount = $this->interpolate(
                    $efficiencyScore,
                    $formula['efficiency_threshold'],
                    $formula['excellence_threshold'],
                    $formula['min_amount'],
                    $formula['max_amount']
                ) + $timeBonus;

                $bonusAmount = max($formula['min_amount'], min($formula['max_amount'], $bonusAmount));

                $bonus = $this->createBonusLog([
                    'user_id' => $agent->user->id,
                    'bonus_type' => BonusLog::TYPE_DELIVERY,
                    'amount' => $bonusAmount,
                    'description' => "Delivery efficiency bonus - {$avgSuccessRate}% success rate, {$totalDeliveries} deliveries",
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'calculation_data' => [
                        'success_rate' => $avgSuccessRate,
                        'average_delivery_time' => $avgDeliveryTime,
                        'total_deliveries' => $totalDeliveries,
                        'efficiency_score' => $efficiencyScore,
                        'time_bonus' => $timeBonus
                    ]
                ]);

                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    /**
     * Calculate special bonuses for achievements
     */
    public function calculateSpecialBonus(User $user, string $achievementType, array $achievementData): ?BonusLog
    {
        $formula = self::BONUS_FORMULAS['special'];

        if (!isset($formula[$achievementType])) {
            throw new \InvalidArgumentException("Unknown achievement type: {$achievementType}");
        }

        $config = $formula[$achievementType];
        $bonusAmount = 0;
        $description = '';
        $calculationData = $achievementData;

        switch ($achievementType) {
            case 'project_completion':
                $baseAmount = $config['min_amount'];
                $complexityMultiplier = $achievementData['complexity_score'] ?? 1.0;
                $timelinessMultiplier = $achievementData['on_time'] ? $config['on_time_multiplier'] : 1.0;
                $qualityMultiplier = ($achievementData['quality_score'] ?? 80) >= 95 ? $config['excellence_multiplier'] : 1.0;
                
                $bonusAmount = $baseAmount * $complexityMultiplier * $timelinessMultiplier * $qualityMultiplier;
                $bonusAmount = min($bonusAmount, $config['max_amount']);
                
                $description = "Project completion bonus - {$achievementData['project_name']}";
                break;

            case 'innovation':
                $impactScore = $achievementData['impact_score'] ?? 1.0;
                $bonusAmount = $config['min_amount'] * $impactScore * $config['impact_multiplier'];
                $bonusAmount = min($bonusAmount, $config['max_amount']);
                
                $description = "Innovation bonus - {$achievementData['innovation_title']}";
                break;

            case 'retention':
                $annualSalary = $user->annual_salary ?? ($user->base_salary ?? 600000) * 12;
                $yearsOfService = $achievementData['years_of_service'] ?? 1;
                $serviceMultiplier = pow($config['years_multiplier'], $yearsOfService - 1);
                
                $bonusPercentage = min($config['percentage_of_salary'] * $serviceMultiplier, $config['max_percentage']);
                $bonusAmount = ($annualSalary * $bonusPercentage) / 100;
                
                $description = "Retention bonus - {$yearsOfService} years of service";
                break;
        }

        if ($bonusAmount > 0) {
            return $this->createBonusLog([
                'user_id' => $user->id,
                'bonus_type' => BonusLog::TYPE_SPECIAL,
                'amount' => $bonusAmount,
                'description' => $description,
                'calculation_data' => $calculationData
            ]);
        }

        return null;
    }

    /**
     * Create bonus log with threshold validation
     */
    protected function createBonusLog(array $data): array
    {
        // Validate bonus amount against threshold limits
        $validationResult = $this->thresholdService->validateCost([
            'type' => 'bonus',
            'category' => 'bonus_payment',
            'amount' => $data['amount'],
            'user_id' => $data['user_id'] ?? null,
            'description' => $data['description'] ?? 'Automated bonus calculation'
        ]);

        // Determine if approval is required
        $requiresApproval = !$validationResult['valid'] || ($data['amount'] > 15000);
        $approvalLevel = $this->determineApprovalLevel($data['amount']);

        $bonusData = array_merge($data, [
            'status' => $requiresApproval ? BonusLog::STATUS_PENDING : BonusLog::STATUS_APPROVED,
            'currency' => 'NGN',
        ]);

        // If amount exceeds thresholds, don't create automatic approval
        if (!$validationResult['valid']) {
            $bonusData['status'] = BonusLog::STATUS_PENDING;
            Log::warning('Bonus requires approval due to threshold violation', [
                'amount' => $data['amount'],
                'user_id' => $data['user_id'],
                'validation_result' => $validationResult
            ]);
        }

        $bonus = BonusLog::create($bonusData);

        Log::info('Bonus calculated and logged', [
            'bonus_id' => $bonus->id,
            'user_id' => $bonus->user_id,
            'amount' => $bonus->amount,
            'type' => $bonus->bonus_type,
            'requires_approval' => $requiresApproval,
            'approval_level' => $approvalLevel
        ]);

        return [
            'bonus_id' => $bonus->id,
            'amount' => $bonus->amount,
            'requires_approval' => $requiresApproval,
            'approval_level' => $approvalLevel,
            'status' => $bonus->status
        ];
    }

    /**
     * Calculate agent performance score from metrics
     */
    private function calculateAgentPerformanceScore($metrics): array
    {
        $successRate = $metrics->avg('success_rate');
        $avgRating = $metrics->avg('average_rating');
        $totalDeliveries = $metrics->sum('deliveries_completed');
        $avgDeliveryTime = $metrics->avg('average_delivery_time');
        $complaintsReceived = $metrics->sum('complaints_received');
        $strikesIssued = $metrics->sum('strikes_issued');

        // Calculate composite performance score
        $performanceScore = 0;
        $performanceScore += $successRate * 0.4; // 40% weight on success rate
        $performanceScore += ($avgRating ?? 3.5) * 20 * 0.3; // 30% weight on rating
        $performanceScore += min(100, $totalDeliveries * 2) * 0.2; // 20% weight on volume
        $performanceScore -= $complaintsReceived * 5; // Penalty for complaints
        $performanceScore -= $strikesIssued * 10; // Penalty for strikes

        $performanceScore = max(0, min(100, $performanceScore));

        return [
            'success_rate' => $successRate,
            'average_rating' => $avgRating,
            'total_deliveries' => $totalDeliveries,
            'average_delivery_time' => $avgDeliveryTime,
            'complaints_received' => $complaintsReceived,
            'strikes_issued' => $strikesIssued,
            'performance_score' => $performanceScore
        ];
    }

    /**
     * Calculate bonus amount based on performance
     */
    private function calculatePerformanceBonusAmount(array $performanceData, float $baseSalary, array $formula): float
    {
        $successRate = $performanceData['success_rate'];
        $performanceScore = $performanceData['performance_score'];
        
        // Base bonus percentage based on success rate
        $bonusPercentage = $this->interpolate(
            $successRate,
            $formula['base_threshold'],
            $formula['excellent_threshold'],
            $formula['min_percentage'],
            $formula['max_percentage']
        );

        // Adjust based on overall performance score
        $performanceAdjustment = ($performanceScore - 75) / 100; // Adjust based on performance above/below 75
        $bonusPercentage += $performanceAdjustment;

        // Apply boundaries
        $bonusPercentage = max($formula['min_percentage'], min($formula['max_percentage'], $bonusPercentage));

        return ($baseSalary * $bonusPercentage) / 100;
    }

    /**
     * Linear interpolation between two values
     */
    private function interpolate(float $value, float $minInput, float $maxInput, float $minOutput, float $maxOutput): float
    {
        if ($value <= $minInput) return $minOutput;
        if ($value >= $maxInput) return $maxOutput;
        
        $ratio = ($value - $minInput) / ($maxInput - $minInput);
        return $minOutput + ($ratio * ($maxOutput - $minOutput));
    }

    /**
     * Calculate team performance bonuses
     */
    private function calculateTeamPerformanceBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        $formula = self::BONUS_FORMULAS['performance']['team'];

        // Calculate team average performance
        $teamMetrics = AgentPerformanceMetric::with('deliveryAgent.user')
            ->whereBetween('metric_date', [$periodStart, $periodEnd])
            ->get();

        $teamSuccessRate = $teamMetrics->avg('success_rate');
        $teamRating = $teamMetrics->avg('average_rating');

        if ($teamSuccessRate >= $formula['team_threshold']) {
            $agents = $teamMetrics->groupBy('delivery_agent_id');
            
            foreach ($agents as $agentId => $metrics) {
                $agent = $metrics->first()->deliveryAgent;
                if (!$agent || !$agent->user) continue;

                $baseSalary = $agent->user->base_salary ?? 50000;
                $bonusPercentage = $this->interpolate(
                    $teamSuccessRate,
                    $formula['team_threshold'],
                    95, // Excellence threshold
                    $formula['min_percentage'],
                    $formula['max_percentage']
                );

                $bonusAmount = ($baseSalary * $bonusPercentage) / 100;

                $bonus = $this->createBonusLog([
                    'user_id' => $agent->user->id,
                    'bonus_type' => BonusLog::TYPE_PERFORMANCE,
                    'amount' => $bonusAmount,
                    'description' => "Team performance bonus - {$teamSuccessRate}% team success rate",
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'calculation_data' => [
                        'team_success_rate' => $teamSuccessRate,
                        'team_rating' => $teamRating,
                        'bonus_type' => 'team_performance'
                    ]
                ]);

                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    /**
     * Calculate company performance bonuses
     */
    private function calculateCompanyPerformanceBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        $formula = self::BONUS_FORMULAS['performance']['company'];

        // Mock company KPI calculation - replace with real metrics
        $companyKPI = $this->calculateCompanyKPI($periodStart, $periodEnd);

        if ($companyKPI >= $formula['company_kpi_threshold']) {
            $allUsers = User::whereIn('role', ['delivery_agent', 'inventory_manager', 'accountant'])->get();

            foreach ($allUsers as $user) {
                $baseSalary = $user->base_salary ?? 50000;
                $bonusPercentage = $this->interpolate(
                    $companyKPI,
                    $formula['company_kpi_threshold'],
                    100,
                    $formula['min_percentage'],
                    $formula['max_percentage']
                );

                $bonusAmount = ($baseSalary * $bonusPercentage) / 100;

                $bonus = $this->createBonusLog([
                    'user_id' => $user->id,
                    'bonus_type' => BonusLog::TYPE_PERFORMANCE,
                    'amount' => $bonusAmount,
                    'description' => "Company performance bonus - {$companyKPI}% company KPI achievement",
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'calculation_data' => [
                        'company_kpi' => $companyKPI,
                        'bonus_type' => 'company_performance'
                    ]
                ]);

                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    /**
     * Mock company KPI calculation
     */
    private function calculateCompanyKPI(Carbon $periodStart, Carbon $periodEnd): float
    {
        // This should be replaced with real company performance metrics
        return 92.5; // Mock 92.5% company performance
    }

    /**
     * Calculate cost optimization bonuses
     */
    private function calculateCostOptimizationBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        $formula = self::BONUS_FORMULAS['logistics']['cost_optimization'];

        // Mock cost savings calculation - replace with real data
        $costSavings = [
            ['user_id' => 1, 'savings' => 15000, 'category' => 'fuel_optimization'],
            ['user_id' => 2, 'savings' => 8000, 'category' => 'route_optimization'],
        ];

        foreach ($costSavings as $saving) {
            if ($saving['savings'] >= $formula['min_savings']) {
                $bonusAmount = $saving['savings'] * ($formula['percentage_of_savings'] / 100);
                $maxBonus = $saving['savings'] * ($formula['max_percentage'] / 100);
                $bonusAmount = min($bonusAmount, $maxBonus);

                $bonus = $this->createBonusLog([
                    'user_id' => $saving['user_id'],
                    'bonus_type' => BonusLog::TYPE_DELIVERY,
                    'amount' => $bonusAmount,
                    'description' => "Cost optimization bonus - ₦{$saving['savings']} savings in {$saving['category']}",
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'calculation_data' => $saving
                ]);

                $bonuses[] = $bonus;
            }
        }

        return $bonuses;
    }

    /**
     * Calculate quality metrics bonuses
     */
    private function calculateQualityMetricsBonuses(Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = [];
        $formula = self::BONUS_FORMULAS['logistics']['quality_metrics'];

        // Get health criteria logs for quality metrics
        $qualityMetrics = HealthCriteriaLog::with('user')
            ->whereBetween('week_start_date', [$periodStart, $periodEnd])
            ->where('overall_score', '>=', $formula['quality_score_threshold'])
            ->get();

        foreach ($qualityMetrics as $metric) {
            if (!$metric->user) continue;

            $bonusAmount = $this->interpolate(
                $metric->overall_score,
                $formula['quality_score_threshold'],
                100,
                $formula['min_amount'],
                $formula['max_amount']
            );

            $bonus = $this->createBonusLog([
                'user_id' => $metric->user->id,
                'bonus_type' => BonusLog::TYPE_PERFORMANCE,
                'amount' => $bonusAmount,
                'description' => "Quality metrics bonus - {$metric->overall_score}% quality score",
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'calculation_data' => [
                    'overall_score' => $metric->overall_score,
                    'payment_matching_accuracy' => $metric->payment_matching_accuracy,
                    'escalation_discipline_score' => $metric->escalation_discipline_score,
                    'documentation_integrity_score' => $metric->documentation_integrity_score,
                    'bonus_log_accuracy_score' => $metric->bonus_log_accuracy_score
                ]
            ]);

            $bonuses[] = $bonus;
        }

        return $bonuses;
    }

    /**
     * Determine approval level for bonus amount
     */
    private function determineApprovalLevel(float $amount): string
    {
        if ($amount > 50000) return 'CEO';
        if ($amount > 15000) return 'GM';
        return 'FC';
    }

    /**
     * Get bonus summary for user
     */
    public function getUserBonusSummary(User $user, Carbon $periodStart, Carbon $periodEnd): array
    {
        $bonuses = BonusLog::where('user_id', $user->id)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get();

        return [
            'total_bonuses' => $bonuses->count(),
            'total_amount' => $bonuses->sum('amount'),
            'pending_amount' => $bonuses->where('status', BonusLog::STATUS_PENDING)->sum('amount'),
            'approved_amount' => $bonuses->where('status', BonusLog::STATUS_APPROVED)->sum('amount'),
            'paid_amount' => $bonuses->where('status', BonusLog::STATUS_PAID)->sum('amount'),
            'by_type' => [
                'performance' => $bonuses->where('bonus_type', BonusLog::TYPE_PERFORMANCE)->sum('amount'),
                'delivery' => $bonuses->where('bonus_type', BonusLog::TYPE_DELIVERY)->sum('amount'),
                'sales' => $bonuses->where('bonus_type', BonusLog::TYPE_SALES)->sum('amount'),
                'special' => $bonuses->where('bonus_type', BonusLog::TYPE_SPECIAL)->sum('amount'),
                'referral' => $bonuses->where('bonus_type', BonusLog::TYPE_REFERRAL)->sum('amount')
            ],
            'bonuses' => $bonuses->toArray()
        ];
    }
} 
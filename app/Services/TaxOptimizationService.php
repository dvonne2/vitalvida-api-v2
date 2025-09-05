<?php

namespace App\Services;

use App\Models\TaxOptimizationStrategy;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class TaxOptimizationService
{
    /**
     * Analyze current tax position
     */
    public function analyzeCurrentPosition(): array
    {
        $availableStrategies = TaxOptimizationStrategy::getAvailableStrategies();
        $implementedStrategies = TaxOptimizationStrategy::implemented()->get();
        $highImpactStrategies = TaxOptimizationStrategy::getHighImpactStrategies();
        $lowDifficultyStrategies = TaxOptimizationStrategy::getLowDifficultyStrategies();

        $totalPotentialSavings = TaxOptimizationStrategy::getTotalPotentialSavings();
        $implementedSavings = TaxOptimizationStrategy::getImplementedSavings();

        return [
            'available_strategies_count' => $availableStrategies->count(),
            'implemented_strategies_count' => $implementedStrategies->count(),
            'high_impact_count' => $highImpactStrategies->count(),
            'low_difficulty_count' => $lowDifficultyStrategies->count(),
            'total_potential_savings' => $totalPotentialSavings,
            'implemented_savings' => $implementedSavings,
            'remaining_savings' => $totalPotentialSavings - $implementedSavings,
            'implementation_rate' => $totalPotentialSavings > 0 ? ($implementedSavings / $totalPotentialSavings) * 100 : 0,
            'formatted_total_potential' => '₦' . number_format($totalPotentialSavings, 2),
            'formatted_implemented' => '₦' . number_format($implementedSavings, 2),
            'formatted_remaining' => '₦' . number_format($totalPotentialSavings - $implementedSavings, 2),
        ];
    }

    /**
     * Get available optimization strategies
     */
    public function getAvailableStrategies(): Collection
    {
        return TaxOptimizationStrategy::getAvailableStrategies();
    }

    /**
     * Calculate potential savings from all strategies
     */
    public function calculatePotentialSavings(): array
    {
        $availableStrategies = $this->getAvailableStrategies();
        $highImpactStrategies = TaxOptimizationStrategy::getHighImpactStrategies();
        $lowDifficultyStrategies = TaxOptimizationStrategy::getLowDifficultyStrategies();
        $priorityStrategies = TaxOptimizationStrategy::getPriorityStrategies(5);

        $totalSavings = $availableStrategies->sum('potential_savings');
        $highImpactSavings = $highImpactStrategies->sum('potential_savings');
        $lowDifficultySavings = $lowDifficultyStrategies->sum('potential_savings');
        $prioritySavings = $priorityStrategies->sum('potential_savings');

        return [
            'total_savings' => $totalSavings,
            'high_impact_savings' => $highImpactSavings,
            'low_difficulty_savings' => $lowDifficultySavings,
            'priority_savings' => $prioritySavings,
            'formatted_total' => '₦' . number_format($totalSavings, 2),
            'formatted_high_impact' => '₦' . number_format($highImpactSavings, 2),
            'formatted_low_difficulty' => '₦' . number_format($lowDifficultySavings, 2),
            'formatted_priority' => '₦' . number_format($prioritySavings, 2),
            'strategies' => [
                'available' => $availableStrategies,
                'high_impact' => $highImpactStrategies,
                'low_difficulty' => $lowDifficultyStrategies,
                'priority' => $priorityStrategies,
            ],
        ];
    }

    /**
     * Implement a specific strategy
     */
    public function implementStrategy(int $strategyId): bool
    {
        try {
            $strategy = TaxOptimizationStrategy::findOrFail($strategyId);
            
            if ($strategy->implementation_status !== 'available') {
                throw new \Exception('Strategy is not available for implementation');
            }

            $strategy->implement();

            Log::info('Tax optimization strategy implemented', [
                'strategy_id' => $strategyId,
                'strategy_name' => $strategy->strategy_name,
                'potential_savings' => $strategy->potential_savings,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to implement tax optimization strategy', [
                'strategy_id' => $strategyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Generate comprehensive optimization report
     */
    public function generateOptimizationReport(): array
    {
        $currentPosition = $this->analyzeCurrentPosition();
        $potentialSavings = $this->calculatePotentialSavings();
        $optimizationSummary = TaxOptimizationStrategy::getOptimizationSummary();
        $priorityStrategies = TaxOptimizationStrategy::getPriorityStrategies(10);
        $overdueStrategies = TaxOptimizationStrategy::getOverdueStrategies();
        $dueSoonStrategies = TaxOptimizationStrategy::getDueSoonStrategies(30);

        return [
            'current_position' => $currentPosition,
            'potential_savings' => $potentialSavings,
            'optimization_summary' => $optimizationSummary,
            'priority_strategies' => $priorityStrategies,
            'overdue_strategies' => $overdueStrategies,
            'due_soon_strategies' => $dueSoonStrategies,
            'recommendations' => $this->generateRecommendations(),
            'risk_assessment' => $this->assessRisks(),
            'implementation_roadmap' => $this->createImplementationRoadmap(),
        ];
    }

    /**
     * Generate strategic recommendations
     */
    private function generateRecommendations(): array
    {
        $recommendations = [];

        // High impact, low difficulty strategies
        $highImpactLowDifficulty = TaxOptimizationStrategy::highImpact()
            ->where('implementation_status', 'available')
            ->where('difficulty_level', 'low')
            ->get();

        if ($highImpactLowDifficulty->count() > 0) {
            $recommendations[] = [
                'type' => 'high_impact_low_difficulty',
                'title' => 'Quick Wins - High Impact, Low Difficulty',
                'description' => 'Implement these strategies for maximum savings with minimal effort',
                'strategies' => $highImpactLowDifficulty,
                'priority' => 'high',
            ];
        }

        // Overdue strategies
        $overdueStrategies = TaxOptimizationStrategy::getOverdueStrategies();
        if ($overdueStrategies->count() > 0) {
            $recommendations[] = [
                'type' => 'overdue',
                'title' => 'Urgent - Overdue Strategies',
                'description' => 'These strategies have passed their deadline and need immediate attention',
                'strategies' => $overdueStrategies,
                'priority' => 'critical',
            ];
        }

        // Due soon strategies
        $dueSoonStrategies = TaxOptimizationStrategy::getDueSoonStrategies(30);
        if ($dueSoonStrategies->count() > 0) {
            $recommendations[] = [
                'type' => 'due_soon',
                'title' => 'Time-Sensitive - Due Soon',
                'description' => 'These strategies have upcoming deadlines',
                'strategies' => $dueSoonStrategies,
                'priority' => 'medium',
            ];
        }

        return $recommendations;
    }

    /**
     * Assess implementation risks
     */
    private function assessRisks(): array
    {
        $highDifficultyStrategies = TaxOptimizationStrategy::where('implementation_status', 'available')
            ->where('difficulty_level', 'high')
            ->get();

        $overdueStrategies = TaxOptimizationStrategy::getOverdueStrategies();
        $dueSoonStrategies = TaxOptimizationStrategy::getDueSoonStrategies(7);

        return [
            'high_risk_strategies' => $highDifficultyStrategies,
            'overdue_risks' => $overdueStrategies,
            'urgent_deadlines' => $dueSoonStrategies,
            'risk_level' => $this->calculateRiskLevel($highDifficultyStrategies, $overdueStrategies),
            'risk_factors' => [
                'high_difficulty_count' => $highDifficultyStrategies->count(),
                'overdue_count' => $overdueStrategies->count(),
                'urgent_deadlines_count' => $dueSoonStrategies->count(),
            ],
        ];
    }

    /**
     * Calculate overall risk level
     */
    private function calculateRiskLevel($highDifficultyStrategies, $overdueStrategies): string
    {
        $riskScore = 0;
        
        if ($overdueStrategies->count() > 0) $riskScore += 3;
        if ($highDifficultyStrategies->count() > 2) $riskScore += 2;
        elseif ($highDifficultyStrategies->count() > 0) $riskScore += 1;

        if ($riskScore >= 4) return 'critical';
        elseif ($riskScore >= 2) return 'high';
        elseif ($riskScore >= 1) return 'medium';
        else return 'low';
    }

    /**
     * Create implementation roadmap
     */
    private function createImplementationRoadmap(): array
    {
        $roadmap = [
            'immediate' => [],
            'short_term' => [],
            'medium_term' => [],
            'long_term' => [],
        ];

        $availableStrategies = TaxOptimizationStrategy::getAvailableStrategies();

        foreach ($availableStrategies as $strategy) {
            if ($strategy->is_overdue) {
                $roadmap['immediate'][] = $strategy;
            } elseif ($strategy->days_until_deadline && $strategy->days_until_deadline <= 30) {
                $roadmap['short_term'][] = $strategy;
            } elseif ($strategy->difficulty_level === 'low') {
                $roadmap['medium_term'][] = $strategy;
            } else {
                $roadmap['long_term'][] = $strategy;
            }
        }

        return $roadmap;
    }

    /**
     * Get strategy by ID
     */
    public function getStrategy(int $strategyId): ?TaxOptimizationStrategy
    {
        return TaxOptimizationStrategy::find($strategyId);
    }

    /**
     * Mark strategy as not applicable
     */
    public function markStrategyAsNotApplicable(int $strategyId): bool
    {
        try {
            $strategy = TaxOptimizationStrategy::findOrFail($strategyId);
            $strategy->markAsNotApplicable();

            Log::info('Tax optimization strategy marked as not applicable', [
                'strategy_id' => $strategyId,
                'strategy_name' => $strategy->strategy_name,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark strategy as not applicable', [
                'strategy_id' => $strategyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Reset strategy to available
     */
    public function resetStrategy(int $strategyId): bool
    {
        try {
            $strategy = TaxOptimizationStrategy::findOrFail($strategyId);
            $strategy->resetToAvailable();

            Log::info('Tax optimization strategy reset to available', [
                'strategy_id' => $strategyId,
                'strategy_name' => $strategy->strategy_name,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to reset strategy', [
                'strategy_id' => $strategyId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
} 
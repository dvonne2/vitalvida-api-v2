<?php

namespace App\Services;

use App\Models\DeliveryAgent;
use App\Models\Bin;
use App\Models\DemandForecast;
use App\Models\RiskAssessment;
use App\Models\AutomatedDecision;
use App\Models\TransferRecommendation;
use App\Models\StockMovement;
use App\Models\EventImpact;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class AutoOptimizationEngine
{
    private $optimizationRules = [
        'min_stock_level' => 5,
        'max_stock_level' => 50,
        'safety_stock_days' => 7,
        'reorder_threshold_days' => 10,
        'emergency_threshold_days' => 3
    ];

    /**
     * Run complete auto-optimization across all delivery agents
     */
    public function runCompleteOptimization()
    {
        $startTime = microtime(true);
        
        Log::info('Starting complete auto-optimization');
        
        $results = [
            'stock_level_optimization' => $this->optimizeStockLevels(),
            'reorder_automation' => $this->automateReorders(),
            'transfer_optimization' => $this->optimizeTransfers(),
            'risk_mitigation' => $this->automateRiskMitigation(),
            'performance_optimization' => $this->optimizePerformance()
        ];
        
        $executionTime = (microtime(true) - $startTime) * 1000;
        
        Log::info("Auto-optimization completed in {$executionTime}ms");
        
        return [
            'status' => 'success',
            'execution_time_ms' => round($executionTime, 2),
            'results' => $results,
            'optimized_at' => now()
        ];
    }

    /**
     * Optimize stock levels for all DAs based on demand forecasts
     */
    public function optimizeStockLevels()
    {
        $das = DeliveryAgent::where('status', 'active')->with('zobin')->get();
        $optimizations = 0;
        $recommendations = [];

        foreach ($das as $da) {
            $optimization = $this->calculateOptimalStockLevel($da);
            
            if ($optimization['requires_adjustment']) {
                $recommendations[] = $optimization;
                $optimizations++;
                
                // Auto-execute if confidence is high enough
                if ($optimization['confidence'] >= 85) {
                    $this->executeStockOptimization($da, $optimization);
                }
            }
        }

        return [
            'total_das_analyzed' => $das->count(),
            'optimizations_identified' => $optimizations,
            'auto_executed' => collect($recommendations)->where('auto_executed', true)->count(),
            'recommendations' => $recommendations
        ];
    }

    /**
     * Automate reorder decisions based on predictive analysis
     */
    public function automateReorders()
    {
        $reorders = [];
        $das = DeliveryAgent::where('status', 'active')->get();

        foreach ($das as $da) {
            $reorderAnalysis = $this->analyzeReorderNeeds($da);
            
            if ($reorderAnalysis['needs_reorder']) {
                $decision = $this->createAutomatedReorder($da, $reorderAnalysis);
                $reorders[] = $decision;
            }
        }

        return [
            'total_reorders_analyzed' => $das->count(),
            'reorders_triggered' => count($reorders),
            'emergency_reorders' => collect($reorders)->where('priority', 'emergency')->count(),
            'reorder_decisions' => $reorders
        ];
    }

    /**
     * Optimize stock transfers between DAs
     */
    public function optimizeTransfers()
    {
        $transfers = [];
        $surplusDAs = $this->identifySurplusDAs();
        $deficitDAs = $this->identifyDeficitDAs();

        foreach ($surplusDAs as $surplus) {
            $bestMatches = $this->findBestTransferTargets($surplus, $deficitDAs);
            
            foreach ($bestMatches as $match) {
                $transfer = $this->createOptimalTransfer($surplus, $match);
                if ($transfer) {
                    $transfers[] = $transfer;
                }
            }
        }

        return [
            'surplus_das' => count($surplusDAs),
            'deficit_das' => count($deficitDAs),
            'transfers_optimized' => count($transfers),
            'total_units_optimized' => collect($transfers)->sum('quantity'),
            'estimated_savings' => collect($transfers)->sum('estimated_savings'),
            'transfer_recommendations' => $transfers
        ];
    }

    /**
     * Automate risk mitigation strategies
     */
    public function automateRiskMitigation()
    {
        $mitigations = [];
        $highRiskDAs = RiskAssessment::highRisk()
            ->where('assessment_date', '>=', Carbon::today()->subDays(1))
            ->with('deliveryAgent')
            ->get();

        foreach ($highRiskDAs as $risk) {
            $mitigation = $this->createRiskMitigation($risk);
            if ($mitigation) {
                $mitigations[] = $mitigation;
            }
        }

        return [
            'high_risk_das' => $highRiskDAs->count(),
            'mitigations_created' => count($mitigations),
            'auto_executed_mitigations' => collect($mitigations)->where('auto_executed', true)->count(),
            'risk_mitigations' => $mitigations
        ];
    }

    /**
     * Optimize overall system performance
     */
    public function optimizePerformance()
    {
        return [
            'forecast_accuracy' => $this->optimizeForecastAccuracy(),
            'inventory_turnover' => $this->optimizeInventoryTurnover(),
            'cost_efficiency' => $this->optimizeCostEfficiency(),
            'service_level' => $this->optimizeServiceLevel()
        ];
    }

    // HELPER METHODS

    private function calculateOptimalStockLevel($da)
    {
        $bin = $da->zobin;
        $currentStock = $bin->current_stock_count ?? 0;
        
        // Get demand forecast for next 30 days
        $forecasts = DemandForecast::where('delivery_agent_id', $da->id)
            ->where('forecast_date', '>=', Carbon::today())
            ->where('forecast_date', '<=', Carbon::today()->addDays(30))
            ->get();

        if ($forecasts->isEmpty()) {
            return [
                'da_id' => $da->id,
                'requires_adjustment' => false,
                'confidence' => 0,
                'reason' => 'No forecast data available'
            ];
        }

        $avgDailyDemand = $forecasts->avg('predicted_demand');
        $demandVariability = $this->calculateDemandVariability($forecasts);
        $eventAdjustment = $this->getEventAdjustment($da->state, 30);
        
        // Calculate optimal levels
        $safetyStock = $avgDailyDemand * $this->optimizationRules['safety_stock_days'];
        $optimalMin = $safetyStock + ($avgDailyDemand * $this->optimizationRules['reorder_threshold_days']);
        $optimalMax = $avgDailyDemand * 30; // 30 days supply
        
        // Apply event adjustments
        $optimalMin += $eventAdjustment;
        $optimalMax += $eventAdjustment;
        
        // Check if adjustment is needed
        $requiresAdjustment = $currentStock < $optimalMin || $currentStock > $optimalMax;
        
        $targetStock = $requiresAdjustment ? 
            ($currentStock < $optimalMin ? $optimalMin : $optimalMax) : $currentStock;
        
        $confidence = $this->calculateOptimizationConfidence($forecasts);
        
        return [
            'da_id' => $da->id,
            'da_code' => $da->da_code,
            'current_stock' => $currentStock,
            'optimal_min' => round($optimalMin),
            'optimal_max' => round($optimalMax),
            'target_stock' => round($targetStock),
            'adjustment_needed' => round($targetStock - $currentStock),
            'requires_adjustment' => $requiresAdjustment,
            'confidence' => $confidence,
            'reasoning' => $this->generateOptimizationReasoning($currentStock, $targetStock, $eventAdjustment),
            'cost_impact' => $this->calculateCostImpact($currentStock, $targetStock),
            'auto_executed' => false
        ];
    }

    private function analyzeReorderNeeds($da)
    {
        $currentStock = $da->zobin->current_stock_count ?? 0;
        
        // Get next 14 days forecast
        $forecasts = DemandForecast::where('delivery_agent_id', $da->id)
            ->where('forecast_date', '>=', Carbon::today())
            ->where('forecast_date', '<=', Carbon::today()->addDays(14))
            ->get();

        if ($forecasts->isEmpty()) {
            return ['needs_reorder' => false, 'reason' => 'No forecast data'];
        }

        $totalDemand = $forecasts->sum('predicted_demand');
        $daysUntilStockout = $currentStock > 0 ? ($currentStock / ($totalDemand / 14)) : 0;
        
        $needsReorder = $daysUntilStockout <= $this->optimizationRules['reorder_threshold_days'];
        $isEmergency = $daysUntilStockout <= $this->optimizationRules['emergency_threshold_days'];
        
        return [
            'needs_reorder' => $needsReorder,
            'is_emergency' => $isEmergency,
            'days_until_stockout' => round($daysUntilStockout, 1),
            'recommended_quantity' => max(20, $totalDemand * 2), // 28 days supply
            'priority' => $isEmergency ? 'emergency' : 'normal',
            'confidence' => $forecasts->avg('confidence_score')
        ];
    }

    private function createAutomatedReorder($da, $analysis)
    {
        return AutomatedDecision::create([
            'decision_type' => 'reorder',
            'delivery_agent_id' => $da->id,
            'trigger_reason' => "Stock will run out in {$analysis['days_until_stockout']} days",
            'decision_data' => [
                'quantity' => $analysis['recommended_quantity'],
                'priority' => $analysis['priority'],
                'current_stock' => $da->zobin->current_stock_count ?? 0,
                'days_until_stockout' => $analysis['days_until_stockout']
            ],
            'confidence_score' => $analysis['confidence'],
            'status' => 'pending',
            'triggered_at' => now()
        ]);
    }

    private function identifySurplusDAs()
    {
        return DeliveryAgent::where('status', 'active')
            ->with('zobin')
            ->get()
            ->filter(function($da) {
                $position = $this->analyzeStockPosition($da);
                return $position['status'] === 'surplus';
            })
            ->toArray();
    }

    private function identifyDeficitDAs()
    {
        return DeliveryAgent::where('status', 'active')
            ->with('zobin')
            ->get()
            ->filter(function($da) {
                $position = $this->analyzeStockPosition($da);
                return $position['status'] === 'deficit';
            })
            ->toArray();
    }

    private function findBestTransferTargets($surplus, $deficitDAs)
    {
        // Simple distance-based matching (in production, use actual geographic data)
        $targets = [];
        
        foreach ($deficitDAs as $deficit) {
            if ($deficit['agent']['state'] === $surplus['agent']['state']) {
                $targets[] = $deficit;
            }
        }
        
        return array_slice($targets, 0, 3); // Top 3 matches
    }

    private function createOptimalTransfer($surplus, $deficit)
    {
        $transferQuantity = min(
            $surplus['surplus_quantity'],
            $deficit['deficit_quantity']
        );
        
        if ($transferQuantity < 5) return null; // Not worth transferring
        
        return TransferRecommendation::create([
            'from_da_id' => $surplus['agent']['id'],
            'to_da_id' => $deficit['agent']['id'],
            'quantity' => $transferQuantity,
            'priority' => 'medium',
            'cost_estimate' => $transferQuantity * 5, // ₦5 per unit transfer cost
            'estimated_savings' => $transferQuantity * 15, // ₦15 savings per unit
            'transfer_reason' => 'Surplus to deficit optimization',
            'status' => 'pending',
            'recommended_at' => now()
        ]);
    }

    private function createRiskMitigation($risk)
    {
        $strategies = [];
        
        if ($risk->stockout_probability > 70) {
            $strategies[] = 'emergency_reorder';
        }
        
        if ($risk->overstock_probability > 60) {
            $strategies[] = 'transfer_surplus';
        }
        
        if (empty($strategies)) return null;
        
        return AutomatedDecision::create([
            'decision_type' => 'risk_mitigation',
            'delivery_agent_id' => $risk->delivery_agent_id,
            'trigger_reason' => "High risk detected: {$risk->risk_level}",
            'decision_data' => [
                'strategies' => $strategies,
                'stockout_probability' => $risk->stockout_probability,
                'overstock_probability' => $risk->overstock_probability,
                'risk_level' => $risk->risk_level
            ],
            'confidence_score' => 80,
            'status' => 'pending',
            'triggered_at' => now()
        ]);
    }

    private function optimizeForecastAccuracy()
    {
        // Calculate recent forecast accuracy
        $accuracy = DB::table('demand_forecasts')
            ->whereNotNull('actual_demand')
            ->where('forecast_date', '>=', Carbon::today()->subDays(30))
            ->avg('accuracy_score');
        
        return [
            'current_accuracy' => round($accuracy ?? 0, 2),
            'target_accuracy' => 85,
            'improvement_needed' => max(0, 85 - ($accuracy ?? 0)),
            'optimization_actions' => ['retrain_models', 'adjust_parameters', 'add_features']
        ];
    }

    private function optimizeInventoryTurnover()
    {
        // Calculate inventory turnover metrics
        $totalStock = DB::table('bin_stocks')->sum('current_stock_count');
        $totalDemand = DB::table('demand_forecasts')
            ->where('forecast_date', '>=', Carbon::today()->subDays(30))
            ->sum('predicted_demand');
        
        $turnoverRate = $totalStock > 0 ? ($totalDemand / $totalStock) : 0;
        
        return [
            'current_turnover_rate' => round($turnoverRate, 2),
            'target_turnover_rate' => 2.0,
            'optimization_needed' => $turnoverRate < 2.0,
            'recommendations' => $turnoverRate < 2.0 ? 
                ['reduce_stock_levels', 'increase_demand_generation'] : 
                ['maintain_current_levels']
        ];
    }

    private function optimizeCostEfficiency()
    {
        // Calculate cost efficiency metrics
        $holdingCosts = DB::table('bin_stocks')->sum('current_stock_count') * 2.5 * 30; // Monthly holding cost
        $stockoutCosts = DB::table('risk_assessments')
            ->where('assessment_date', '>=', Carbon::today()->subDays(30))
            ->sum('potential_lost_sales');
        
        $totalCosts = $holdingCosts + $stockoutCosts;
        
        return [
            'holding_costs' => round($holdingCosts, 2),
            'stockout_costs' => round($stockoutCosts, 2),
            'total_costs' => round($totalCosts, 2),
            'optimization_opportunities' => $totalCosts > 50000 ? 
                ['reduce_safety_stock', 'improve_forecasting', 'optimize_transfers'] : 
                ['maintain_current_strategy']
        ];
    }

    private function optimizeServiceLevel()
    {
        // Calculate service level metrics
        $totalDemand = DB::table('demand_forecasts')
            ->where('forecast_date', '>=', Carbon::today()->subDays(30))
            ->sum('predicted_demand');
        
        $stockouts = DB::table('risk_assessments')
            ->where('assessment_date', '>=', Carbon::today()->subDays(30))
            ->where('stockout_probability', '>', 50)
            ->count();
        
        $serviceLevel = $totalDemand > 0 ? ((1 - ($stockouts / $totalDemand)) * 100) : 100;
        
        return [
            'current_service_level' => round($serviceLevel, 2),
            'target_service_level' => 95,
            'stockout_incidents' => $stockouts,
            'improvement_actions' => $serviceLevel < 95 ? 
                ['increase_safety_stock', 'improve_forecasting', 'faster_replenishment'] : 
                ['maintain_current_performance']
        ];
    }

    private function executeStockOptimization($da, $optimization)
    {
        // Create automated decision for stock adjustment
        AutomatedDecision::create([
            'decision_type' => 'stock_adjustment',
            'delivery_agent_id' => $da->id,
            'trigger_reason' => 'Auto-optimization: ' . $optimization['reasoning'],
            'decision_data' => $optimization,
            'confidence_score' => $optimization['confidence'],
            'status' => 'executed',
            'triggered_at' => now(),
            'executed_at' => now()
        ]);
        
        $optimization['auto_executed'] = true;
        
        Log::info("Auto-executed stock optimization for DA {$da->da_code}");
    }

    // Helper Methods

    private function calculateDemandVariability($forecasts)
    {
        $demands = $forecasts->pluck('predicted_demand')->toArray();
        $mean = array_sum($demands) / count($demands);
        
        $variance = array_sum(array_map(function($x) use ($mean) {
            return pow($x - $mean, 2);
        }, $demands)) / count($demands);
        
        return sqrt($variance);
    }

    private function getEventAdjustment($state, $days)
    {
        $events = EventImpact::where('event_date', '>=', Carbon::today())
            ->where('event_date', '<=', Carbon::today()->addDays($days))
            ->where('affected_locations', 'like', "%{$state}%")
            ->get();
        
        $totalAdjustment = 0;
        foreach ($events as $event) {
            $totalAdjustment += ($event->demand_impact / 100) * 10; // Convert % to units
        }
        
        return $totalAdjustment;
    }

    private function calculateOptimizationConfidence($forecasts)
    {
        return $forecasts->avg('confidence_score') ?? 70;
    }

    private function generateOptimizationReasoning($current, $optimal, $eventAdjustment)
    {
        $difference = $optimal - $current;
        $direction = $difference > 0 ? 'increase' : 'decrease';
        $magnitude = abs($difference);
        
        $reasoning = "Recommendation to {$direction} stock by {$magnitude} units based on demand forecasts";
        
        if ($eventAdjustment != 0) {
            $eventDirection = $eventAdjustment > 0 ? 'increase' : 'decrease';
            $reasoning .= " with {$eventDirection} due to upcoming events";
        }
        
        return $reasoning;
    }

    private function calculateCostImpact($current, $optimal)
    {
        $difference = $optimal - $current;
        $holdingCostPerUnit = 2.5; // ₦2.5 per unit per day
        $stockoutCostPerUnit = 50; // ₦50 lost revenue per stockout
        
        if ($difference > 0) {
            // Additional holding cost
            return -($difference * $holdingCostPerUnit * 30); // Monthly cost
        } else {
            // Reduced stockout risk
            return abs($difference) * $stockoutCostPerUnit * 0.3; // 30% stockout probability
        }
    }

    private function analyzeStockPosition($agent)
    {
        $currentStock = $agent->zobin->current_stock_count ?? 0;
        
        // Get next 7 days forecast
        $forecasts = DemandForecast::where('delivery_agent_id', $agent->id)
            ->where('forecast_date', '>=', Carbon::today())
            ->take(7)
            ->get();
        
        if ($forecasts->isEmpty()) {
            return ['status' => 'unknown', 'agent' => $agent];
        }
        
        $avgDemand = $forecasts->avg('predicted_demand');
        $weeklyDemand = $avgDemand * 7;
        
        if ($currentStock > $weeklyDemand * 2) {
            return [
                'status' => 'surplus',
                'agent' => $agent,
                'current_stock' => $currentStock,
                'weekly_demand' => $weeklyDemand,
                'surplus_quantity' => $currentStock - $weeklyDemand
            ];
        } elseif ($currentStock < $weeklyDemand * 0.5) {
            return [
                'status' => 'deficit',
                'agent' => $agent,
                'current_stock' => $currentStock,
                'weekly_demand' => $weeklyDemand,
                'deficit_quantity' => $weeklyDemand - $currentStock
            ];
        }
        
        return ['status' => 'balanced', 'agent' => $agent];
    }
}

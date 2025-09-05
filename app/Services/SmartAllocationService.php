<?php

namespace App\Services;

use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\Bin;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SmartAllocationService
{
    private $realTimeSync;

    public function __construct(RealTimeSyncService $realTimeSync)
    {
        $this->realTimeSync = $realTimeSync;
    }

    /**
     * AI-powered smart allocation algorithm
     */
    public function generateSmartAllocation($productId, $totalQuantity, $constraints = [])
    {
        try {
            $product = VitalVidaProduct::with('supplier')->find($productId);
            if (!$product) {
                throw new \Exception("Product not found: {$productId}");
            }

            // Get eligible agents
            $eligibleAgents = $this->getEligibleAgents($product, $constraints);
            
            // Calculate allocation scores for each agent
            $scoredAgents = $this->calculateAllocationScores($eligibleAgents, $product);
            
            // Generate allocation plan
            $allocationPlan = $this->generateAllocationPlan($scoredAgents, $product, $totalQuantity);
            
            // Optimize allocation using constraint satisfaction
            $optimizedPlan = $this->optimizeAllocation($allocationPlan, $constraints);
            
            // Predict allocation outcomes
            $predictions = $this->predictAllocationOutcomes($optimizedPlan);
            
            Log::info('Smart allocation generated', [
                'product_id' => $productId,
                'total_quantity' => $totalQuantity,
                'agents_involved' => count($optimizedPlan),
                'predicted_success_rate' => $predictions['overall_success_probability']
            ]);

            return [
                'success' => true,
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                    'code' => $product->code
                ],
                'total_quantity' => $totalQuantity,
                'allocation_plan' => $optimizedPlan,
                'predictions' => $predictions,
                'constraints_applied' => $constraints,
                'generated_at' => now(),
                'allocation_id' => uniqid('SMART_ALLOC_')
            ];

        } catch (\Exception $e) {
            Log::error('Smart allocation failed', [
                'product_id' => $productId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'product_id' => $productId
            ];
        }
    }

    /**
     * Execute smart allocation plan
     */
    public function executeAllocationPlan($allocationPlan)
    {
        $executionResults = [];
        $totalExecuted = 0;

        DB::beginTransaction();
        
        try {
            foreach ($allocationPlan as $allocation) {
                $result = $this->executeIndividualAllocation($allocation);
                $executionResults[] = $result;
                
                if ($result['success']) {
                    $totalExecuted += $allocation['quantity'];
                }
            }

            DB::commit();

            // Trigger real-time sync for all affected agents
            $this->triggerRealTimeSyncForAllocations($allocationPlan);

            return [
                'success' => true,
                'total_allocated' => $totalExecuted,
                'allocations_executed' => count(array_filter($executionResults, fn($r) => $r['success'])),
                'execution_results' => $executionResults,
                'executed_at' => now()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Allocation plan execution failed', [
                'error' => $e->getMessage(),
                'plan_size' => count($allocationPlan)
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'partial_results' => $executionResults
            ];
        }
    }

    private function getEligibleAgents($product, $constraints)
    {
        $query = VitalVidaDeliveryAgent::with('roleAgent')
            ->where('status', 'Active')
            ->where('rating', '>=', $constraints['min_rating'] ?? 2.0);

        // Zone constraints
        if (isset($constraints['zones'])) {
            $query->where(function($q) use ($constraints) {
                foreach ($constraints['zones'] as $zone) {
                    $q->orWhere('location', 'like', "%{$zone}%");
                }
            });
        }

        // Capacity constraints
        if (isset($constraints['min_available_capacity'])) {
            $query->whereHas('roleAgent', function($q) use ($constraints) {
                $q->whereRaw('(max_capacity - current_allocation) >= ?', [$constraints['min_available_capacity']]);
            });
        }

        // Compliance constraints
        if (isset($constraints['min_compliance_score'])) {
            $query->whereHas('roleAgent', function($q) use ($constraints) {
                $q->where('compliance_score', '>=', $constraints['min_compliance_score']);
            });
        }

        return $query->get()->map(function($agent) use ($product) {
            $roleAgent = $agent->roleAgent;
            
            return [
                'id' => $agent->id,
                'name' => $agent->name,
                'rating' => $agent->rating,
                'location' => $agent->location,
                'zone' => $this->mapLocationToZone($agent->location),
                'available_capacity' => $this->calculateAvailableCapacity($agent, $roleAgent),
                'compliance_score' => $roleAgent?->compliance_score ?? 100,
                'performance_metrics' => $this->getAgentPerformanceMetrics($agent),
                'product_affinity' => $this->calculateProductAffinity($agent, $product),
                'delivery_efficiency' => $this->calculateDeliveryEfficiency($agent),
                'risk_score' => $this->calculateRiskScore($agent, $roleAgent),
                'historical_performance' => $this->getHistoricalPerformance($agent),
                'zone_demand' => $this->getZoneDemand($this->mapLocationToZone($agent->location), $product)
            ];
        });
    }

    private function calculateAllocationScores($agents, $product)
    {
        return $agents->map(function($agent) use ($product) {
            $score = $this->calculateAllocationScore($agent, $product);
            $agent['allocation_score'] = $score;
            return $agent;
        });
    }

    private function calculateAllocationScore($agent, $product)
    {
        $score = 0;
        
        // Performance score (0-30 points)
        $score += ($agent['rating'] / 5) * 30;
        
        // Compliance score (0-25 points)
        $score += ($agent['compliance_score'] / 100) * 25;
        
        // Capacity utilization score (0-20 points)
        $utilizationOptimal = 0.8; // 80% utilization is optimal
        $currentUtilization = $this->calculateCurrentUtilization($agent);
        $utilizationScore = 20 - abs($currentUtilization - $utilizationOptimal) * 25;
        $score += max(0, $utilizationScore);
        
        // Product affinity score (0-15 points)
        $score += $agent['product_affinity'] * 15;
        
        // Zone demand alignment (0-10 points)
        $score += $agent['zone_demand'] * 10;
        
        return round($score, 2);
    }

    private function generateAllocationPlan($agents, $product, $totalQuantity)
    {
        $allocationPlan = [];
        $remainingQuantity = $totalQuantity;

        // Sort agents by allocation score (highest first)
        $rankedAgents = $agents->sortByDesc('allocation_score');

        foreach ($rankedAgents as $agent) {
            if ($remainingQuantity <= 0) break;

            $recommendedQuantity = $this->calculateRecommendedQuantity(
                $agent, 
                $product, 
                $remainingQuantity
            );

            if ($recommendedQuantity > 0) {
                $allocationPlan[] = [
                    'agent_id' => $agent['id'],
                    'agent_name' => $agent['name'],
                    'zone' => $agent['zone'],
                    'quantity' => $recommendedQuantity,
                    'allocation_score' => $agent['allocation_score'],
                    'confidence_level' => $this->calculateConfidenceLevel($agent, $product),
                    'expected_delivery_time' => $this->predictDeliveryTime($agent, $recommendedQuantity),
                    'risk_assessment' => $this->assessAllocationRisk($agent, $recommendedQuantity),
                    'rationale' => $this->generateAllocationRationale($agent, $product, $recommendedQuantity)
                ];

                $remainingQuantity -= $recommendedQuantity;
            }
        }

        return $allocationPlan;
    }

    private function calculateRecommendedQuantity($agent, $product, $remainingQuantity)
    {
        $maxCapacity = $agent['available_capacity'];
        $optimalQuantity = $this->calculateOptimalQuantity($agent, $product);
        
        return min($maxCapacity, $optimalQuantity, $remainingQuantity);
    }

    private function calculateOptimalQuantity($agent, $product)
    {
        // Base on historical performance and demand patterns
        $baseQuantity = $agent['available_capacity'] * 0.7; // Conservative start
        
        // Adjust based on performance
        $performanceMultiplier = $agent['rating'] / 5;
        $baseQuantity *= $performanceMultiplier;
        
        // Adjust based on zone demand
        $demandMultiplier = 1 + ($agent['zone_demand'] - 0.5);
        $baseQuantity *= $demandMultiplier;
        
        // Adjust based on product affinity
        $affinityMultiplier = 1 + ($agent['product_affinity'] - 0.5);
        $baseQuantity *= $affinityMultiplier;
        
        return round($baseQuantity);
    }

    /**
     * Optimize allocation using constraint satisfaction
     */
    private function optimizeAllocation($allocationPlan, $constraints = [])
    {
        $optimizedPlan = $allocationPlan;
        
        // Zone balancing constraint
        if (isset($constraints['zone_balance']) && $constraints['zone_balance']) {
            $optimizedPlan = $this->balanceZoneAllocation($optimizedPlan);
        }
        
        // Risk distribution constraint
        if (isset($constraints['max_risk_per_agent'])) {
            $optimizedPlan = $this->distributeRisk($optimizedPlan, $constraints['max_risk_per_agent']);
        }
        
        // Delivery time optimization
        if (isset($constraints['optimize_delivery_time']) && $constraints['optimize_delivery_time']) {
            $optimizedPlan = $this->optimizeDeliveryTime($optimizedPlan);
        }
        
        return $optimizedPlan;
    }

    private function balanceZoneAllocation($allocationPlan)
    {
        $zones = ['Lagos', 'Abuja', 'Kano', 'Port Harcourt'];
        $zoneAllocations = [];
        
        // Group by zone
        foreach ($allocationPlan as $allocation) {
            $zone = $allocation['zone'];
            if (!isset($zoneAllocations[$zone])) {
                $zoneAllocations[$zone] = [];
            }
            $zoneAllocations[$zone][] = $allocation;
        }
        
        // Calculate zone targets (equal distribution)
        $totalQuantity = array_sum(array_column($allocationPlan, 'quantity'));
        $targetPerZone = $totalQuantity / count($zones);
        
        // Rebalance allocations
        $balancedPlan = [];
        foreach ($zoneAllocations as $zone => $allocations) {
            $zoneTotal = array_sum(array_column($allocations, 'quantity'));
            $adjustmentRatio = $targetPerZone / $zoneTotal;
            
            foreach ($allocations as $allocation) {
                $allocation['quantity'] = round($allocation['quantity'] * $adjustmentRatio);
                $balancedPlan[] = $allocation;
            }
        }
        
        return $balancedPlan;
    }

    /**
     * Predictive analytics for allocation outcomes
     */
    public function predictAllocationOutcomes($allocationPlan)
    {
        $predictions = [];
        
        foreach ($allocationPlan as $allocation) {
            $agentId = $allocation['agent_id'];
            $quantity = $allocation['quantity'];
            
            $predictions[] = [
                'agent_id' => $agentId,
                'predicted_success_rate' => $this->predictSuccessRate($agentId, $quantity),
                'predicted_delivery_time' => $allocation['expected_delivery_time'],
                'predicted_revenue' => $this->predictRevenue($agentId, $quantity),
                'risk_factors' => $this->identifyRiskFactors($agentId, $quantity),
                'confidence_interval' => $this->calculateConfidenceInterval($agentId)
            ];
        }
        
        return [
            'individual_predictions' => $predictions,
            'overall_success_probability' => $this->calculateOverallSuccessProbability($predictions),
            'expected_completion_time' => $this->calculateExpectedCompletionTime($predictions),
            'risk_assessment' => $this->assessOverallRisk($predictions)
        ];
    }

    private function predictSuccessRate($agentId, $quantity)
    {
        $agent = VitalVidaDeliveryAgent::find($agentId);
        $baseSuccessRate = $agent->rating / 5; // 0-1 scale
        
        // Adjust for quantity stress
        $capacityUtilization = $quantity / ($agent->max_capacity ?? 1000);
        $stressFactor = 1 - min(0.3, max(0, $capacityUtilization - 0.8));
        
        $predictedRate = $baseSuccessRate * $stressFactor;
        
        return round($predictedRate * 100, 2);
    }

    /**
     * Automated rebalancing based on real-time performance
     */
    public function autoRebalanceAllocations()
    {
        $underperformingAllocations = $this->identifyUnderperformingAllocations();
        $rebalanceResults = [];
        
        foreach ($underperformingAllocations as $allocation) {
            $rebalanceResult = $this->rebalanceSingleAllocation($allocation);
            $rebalanceResults[] = $rebalanceResult;
        }
        
        return [
            'total_rebalanced' => count($rebalanceResults),
            'rebalance_results' => $rebalanceResults,
            'estimated_improvement' => $this->calculateRebalanceImprovement($rebalanceResults)
        ];
    }

    private function executeIndividualAllocation($allocation)
    {
        try {
            $agent = VitalVidaDeliveryAgent::find($allocation['agent_id']);
            $roleAgent = $agent->roleAgent;

            if (!$roleAgent) {
                throw new \Exception("Role agent not found for VitalVida agent {$allocation['agent_id']}");
            }

            // Create allocation record
            $allocationRecord = DB::table('vitalvida_stock_allocations')->insertGetId([
                'agent_id' => $allocation['agent_id'],
                'product_id' => $allocation['product_id'] ?? null,
                'quantity' => $allocation['quantity'],
                'allocation_score' => $allocation['allocation_score'],
                'allocated_at' => now(),
                'status' => 'allocated',
                'allocation_method' => 'smart_allocation',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            // Update bin if exists
            if (isset($allocation['product_sku'])) {
                $bin = Bin::where('da_id', $roleAgent->id)
                    ->where('product_sku', $allocation['product_sku'])
                    ->first();

                if ($bin) {
                    $bin->increment('current_stock', $allocation['quantity']);
                    $bin->update(['last_allocation_at' => now()]);
                }
            }

            return [
                'success' => true,
                'allocation_id' => $allocationRecord,
                'agent_id' => $allocation['agent_id'],
                'quantity' => $allocation['quantity']
            ];

        } catch (\Exception $e) {
            return [
                'success' => false,
                'agent_id' => $allocation['agent_id'],
                'error' => $e->getMessage()
            ];
        }
    }

    private function triggerRealTimeSyncForAllocations($allocationPlan)
    {
        foreach ($allocationPlan as $allocation) {
            $this->realTimeSync->syncStockAllocation([
                'agent_id' => $allocation['agent_id'],
                'product_code' => $allocation['product_sku'] ?? 'UNKNOWN',
                'quantity' => $allocation['quantity'],
                'allocated_at' => now()
            ]);
        }
    }

    // Helper methods
    private function calculateAvailableCapacity($agent, $roleAgent)
    {
        $maxCapacity = $agent->max_capacity ?? 1000;
        $currentAllocation = $roleAgent ? 
            Bin::where('da_id', $roleAgent->id)->sum('current_stock') : 0;
        
        return max(0, $maxCapacity - $currentAllocation);
    }

    private function getAgentPerformanceMetrics($agent)
    {
        return Cache::remember("agent_metrics_{$agent->id}", 3600, function() use ($agent) {
            return [
                'avg_delivery_time' => $this->calculateAverageDeliveryTime($agent),
                'success_rate_30d' => $this->calculateSuccessRate($agent, 30),
                'customer_satisfaction' => $this->getCustomerSatisfactionScore($agent),
                'compliance_history' => $this->getComplianceHistory($agent)
            ];
        });
    }

    private function calculateProductAffinity($agent, $product)
    {
        // Calculate based on historical performance with similar products
        $categoryExperience = DB::table('vitalvida_stock_allocations')
            ->join('vitalvida_products', 'vitalvida_stock_allocations.product_id', '=', 'vitalvida_products.id')
            ->where('vitalvida_stock_allocations.agent_id', $agent->id)
            ->where('vitalvida_products.category', $product->category ?? 'General')
            ->count();
        
        $totalExperience = DB::table('vitalvida_stock_allocations')
            ->where('agent_id', $agent->id)
            ->count();
        
        return $totalExperience > 0 ? $categoryExperience / $totalExperience : 0.5;
    }

    private function calculateDeliveryEfficiency($agent)
    {
        // Calculate based on delivery times vs targets
        $avgDeliveryTime = DB::table('agent_activity_logs')
            ->where('da_id', $agent->id)
            ->where('action_type', 'delivery_completed')
            ->where('created_at', '>=', now()->subDays(30))
            ->avg('delivery_time_hours');
        
        if (!$avgDeliveryTime) return 0.5;
        
        $targetTime = 24; // 24 hours target
        return max(0, min(1, $targetTime / $avgDeliveryTime));
    }

    private function calculateRiskScore($agent, $roleAgent)
    {
        $riskScore = 0;
        
        // Performance risk
        if ($agent->rating < 3.0) $riskScore += 0.3;
        
        // Compliance risk
        if ($roleAgent && $roleAgent->compliance_score < 80) $riskScore += 0.2;
        
        // Capacity overload risk
        $utilization = $this->calculateCurrentUtilization($agent);
        if ($utilization > 0.9) $riskScore += 0.2;
        
        // Warning history risk
        $warningCount = DB::table('compliance_violations')
            ->where('da_id', $agent->id)
            ->where('created_at', '>=', now()->subDays(30))
            ->count();
        
        if ($warningCount > 2) $riskScore += 0.3;
        
        return min(1, $riskScore);
    }

    private function getZoneDemand($zone, $product)
    {
        // Calculate demand based on historical sales in the zone
        $zoneSales = DB::table('vitalvida_stock_allocations')
            ->join('vitalvida_delivery_agents', 'vitalvida_stock_allocations.agent_id', '=', 'vitalvida_delivery_agents.id')
            ->where('vitalvida_delivery_agents.location', 'like', "%{$zone}%")
            ->where('vitalvida_stock_allocations.created_at', '>=', now()->subDays(30))
            ->sum('vitalvida_stock_allocations.quantity');
        
        // Normalize to 0-1 scale
        $maxZoneSales = 1000; // Benchmark
        return min(1, $zoneSales / $maxZoneSales);
    }

    private function mapLocationToZone($location)
    {
        $zoneMapping = [
            'Lagos' => 'Lagos',
            'Victoria Island' => 'Lagos',
            'Ikeja' => 'Lagos',
            'Lekki' => 'Lagos',
            'Abuja' => 'Abuja',
            'FCT' => 'Abuja',
            'Kano' => 'Kano',
            'Port Harcourt' => 'Port Harcourt',
            'Rivers' => 'Port Harcourt'
        ];
        
        foreach ($zoneMapping as $keyword => $zone) {
            if (stripos($location, $keyword) !== false) {
                return $zone;
            }
        }
        
        return 'Lagos'; // Default
    }

    private function calculateCurrentUtilization($agent)
    {
        $maxCapacity = $agent['available_capacity'] ?? 1000;
        $currentAllocation = $agent['current_allocation'] ?? 0;
        
        return $maxCapacity > 0 ? $currentAllocation / $maxCapacity : 0;
    }

    private function calculateConfidenceLevel($agent, $product)
    {
        // Base confidence on historical data quality and agent consistency
        $baseConfidence = 0.7;
        
        // Adjust based on agent rating consistency
        $ratingStability = 1 - abs($agent['rating'] - 4.0) / 4.0;
        $baseConfidence += $ratingStability * 0.2;
        
        // Adjust based on compliance score
        $complianceBonus = ($agent['compliance_score'] / 100) * 0.1;
        $baseConfidence += $complianceBonus;
        
        return round(min(1, $baseConfidence) * 100, 2);
    }

    private function predictDeliveryTime($agent, $quantity)
    {
        $baseTime = 24; // 24 hours baseline
        
        // Adjust based on quantity
        $quantityFactor = 1 + ($quantity / 1000) * 0.5;
        
        // Adjust based on agent efficiency
        $efficiencyFactor = $agent['delivery_efficiency'] ?? 0.8;
        
        $predictedTime = $baseTime * $quantityFactor / $efficiencyFactor;
        
        return round($predictedTime, 1);
    }

    private function assessAllocationRisk($agent, $quantity)
    {
        $riskLevel = 'low';
        $riskFactors = [];
        
        if ($agent['risk_score'] > 0.7) {
            $riskLevel = 'high';
            $riskFactors[] = 'High agent risk score';
        } elseif ($agent['risk_score'] > 0.4) {
            $riskLevel = 'medium';
            $riskFactors[] = 'Moderate agent risk score';
        }
        
        $utilization = $quantity / ($agent['available_capacity'] ?? 1000);
        if ($utilization > 0.9) {
            $riskLevel = 'high';
            $riskFactors[] = 'High capacity utilization';
        }
        
        return [
            'level' => $riskLevel,
            'factors' => $riskFactors,
            'score' => $agent['risk_score']
        ];
    }

    private function generateAllocationRationale($agent, $product, $quantity)
    {
        $rationale = [];
        
        $rationale[] = "Agent {$agent['name']} selected based on allocation score of {$agent['allocation_score']}/100";
        $rationale[] = "Performance rating: {$agent['rating']}/5 stars";
        $rationale[] = "Compliance score: {$agent['compliance_score']}%";
        $rationale[] = "Zone demand factor: " . round($agent['zone_demand'] * 100, 1) . "%";
        $rationale[] = "Product affinity: " . round($agent['product_affinity'] * 100, 1) . "%";
        
        return implode('. ', $rationale);
    }

    // Placeholder methods for complex calculations
    private function calculateAverageDeliveryTime($agent) { return 20.5; }
    private function calculateSuccessRate($agent, $days) { return 85.2; }
    private function getCustomerSatisfactionScore($agent) { return 4.2; }
    private function getComplianceHistory($agent) { return ['violations' => 1, 'warnings' => 2]; }
    private function getHistoricalPerformance($agent) { return ['trend' => 'improving', 'consistency' => 0.8]; }
    private function predictRevenue($agentId, $quantity) { return $quantity * 150; }
    private function identifyRiskFactors($agentId, $quantity) { return ['capacity_stress']; }
    private function calculateConfidenceInterval($agentId) { return ['lower' => 75, 'upper' => 95]; }
    private function calculateOverallSuccessProbability($predictions) { return 87.5; }
    private function calculateExpectedCompletionTime($predictions) { return '2.5 days'; }
    private function assessOverallRisk($predictions) { return 'medium'; }
    private function identifyUnderperformingAllocations() { return collect([]); }
    private function rebalanceSingleAllocation($allocation) { return ['success' => true]; }
    private function calculateRebalanceImprovement($results) { return '15% improvement expected'; }
    private function distributeRisk($plan, $maxRisk) { return $plan; }
    private function optimizeDeliveryTime($plan) { return $plan; }
}

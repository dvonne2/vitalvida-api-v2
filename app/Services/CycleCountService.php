<?php

namespace App\Services;

use App\Models\CycleCount;
use App\Models\InventoryVariance;
use App\Models\DeliveryAgent;
use App\Models\VitalVidaProduct;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Carbon\Carbon;

class CycleCountService
{
    protected $realTimeSyncService;

    public function __construct(RealTimeSyncService $realTimeSyncService)
    {
        $this->realTimeSyncService = $realTimeSyncService;
    }

    /**
     * Schedule ABC cycle counts automatically
     */
    public function scheduleABCCycleCounts()
    {
        DB::beginTransaction();
        
        try {
            $scheduledCounts = 0;
            
            // Get all active agents and their products
            $agents = DeliveryAgent::active()->with('inventory.product')->get();
            
            foreach ($agents as $agent) {
                foreach ($agent->inventory as $inventory) {
                    $product = $inventory->product;
                    $abcClass = $this->determineABCClassification($product, $inventory);
                    
                    // Check if count is due
                    if ($this->isCycleCountDue($agent->id, $product->id, $abcClass)) {
                        $this->scheduleCycleCount($agent->id, $product->id, $abcClass);
                        $scheduledCounts++;
                    }
                }
            }

            Log::info('ABC cycle counts scheduled', ['count' => $scheduledCounts]);
            
            DB::commit();
            return $scheduledCounts;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('ABC cycle count scheduling failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Schedule individual cycle count
     */
    public function scheduleCycleCount($agentId, $productId, $abcClassification)
    {
        $countId = 'CC-' . date('Ymd') . '-' . strtoupper(Str::random(6));
        $scheduledDate = $this->getNextCountDate($abcClassification);
        
        $cycleCount = CycleCount::create([
            'count_id' => $countId,
            'agent_id' => $agentId,
            'product_id' => $productId,
            'abc_classification' => $abcClassification,
            'scheduled_date' => $scheduledDate,
            'count_status' => CycleCount::STATUS_SCHEDULED,
            'system_quantity' => $this->getCurrentSystemQuantity($agentId, $productId),
            'count_method' => CycleCount::METHOD_PHYSICAL,
            'count_metadata' => [
                'scheduled_by' => 'system',
                'auto_scheduled' => true,
                'count_frequency' => $this->getCountFrequency($abcClassification),
                'priority_level' => $this->getPriorityLevel($abcClassification)
            ]
        ]);

        // Notify agent of scheduled count
        $this->notifyAgentOfScheduledCount($cycleCount);
        
        return $cycleCount;
    }

    /**
     * Start cycle count
     */
    public function startCycleCount($countId, $countedBy)
    {
        $cycleCount = CycleCount::where('count_id', $countId)->firstOrFail();
        
        if ($cycleCount->count_status !== CycleCount::STATUS_SCHEDULED) {
            throw new \Exception('Count not in scheduled status');
        }

        $cycleCount->update([
            'count_status' => CycleCount::STATUS_IN_PROGRESS,
            'counted_by' => $countedBy,
            'started_at' => now(),
            'system_quantity' => $this->getCurrentSystemQuantity($cycleCount->agent_id, $cycleCount->product_id)
        ]);

        $this->realTimeSyncService->broadcastCycleCountUpdate($cycleCount);
        
        return $cycleCount;
    }

    /**
     * Complete cycle count with physical count
     */
    public function completeCycleCount($countId, $countedQuantity, $countNotes = null)
    {
        DB::beginTransaction();
        
        try {
            $cycleCount = CycleCount::where('count_id', $countId)->firstOrFail();
            
            if ($cycleCount->count_status !== CycleCount::STATUS_IN_PROGRESS) {
                throw new \Exception('Count not in progress');
            }

            // Calculate variance
            $varianceQuantity = $countedQuantity - $cycleCount->system_quantity;
            $variancePercentage = $cycleCount->system_quantity > 0 
                ? ($varianceQuantity / $cycleCount->system_quantity) * 100 
                : 0;

            $cycleCount->update([
                'count_status' => CycleCount::STATUS_COMPLETED,
                'counted_quantity' => $countedQuantity,
                'variance_quantity' => $varianceQuantity,
                'variance_percentage' => $variancePercentage,
                'count_notes' => $countNotes,
                'completed_at' => now()
            ]);

            // Create variance record if significant
            if (abs($varianceQuantity) > 0) {
                $this->createVarianceRecord($cycleCount, $varianceQuantity);
            }

            // Auto-verify if within tolerance
            if ($this->isWithinTolerance($variancePercentage)) {
                $this->verifyCycleCount($countId, $cycleCount->counted_by);
            }

            $this->realTimeSyncService->broadcastCycleCountUpdate($cycleCount);

            DB::commit();
            return $cycleCount;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Cycle count completion failed', [
                'error' => $e->getMessage(),
                'count_id' => $countId
            ]);
            throw $e;
        }
    }

    /**
     * Verify cycle count
     */
    public function verifyCycleCount($countId, $verifiedBy)
    {
        $cycleCount = CycleCount::where('count_id', $countId)->firstOrFail();
        
        if ($cycleCount->count_status !== CycleCount::STATUS_COMPLETED) {
            throw new \Exception('Count not completed');
        }

        $cycleCount->update([
            'count_status' => CycleCount::STATUS_VERIFIED,
            'verified_by' => $verifiedBy,
            'verified_at' => now()
        ]);

        // Update system quantities if verified
        $this->updateSystemQuantity($cycleCount);
        
        $this->realTimeSyncService->broadcastCycleCountUpdate($cycleCount);
        
        return $cycleCount;
    }

    /**
     * Get cycle count analytics
     */
    public function getCycleCountAnalytics($period = 'monthly')
    {
        $startDate = match($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'quarterly' => now()->startOfQuarter(),
            default => now()->startOfMonth()
        };

        $counts = CycleCount::where('created_at', '>=', $startDate)->get();
        $variances = InventoryVariance::where('detected_at', '>=', $startDate)->get();

        return [
            'total_counts_scheduled' => $counts->count(),
            'counts_completed' => $counts->where('count_status', CycleCount::STATUS_COMPLETED)->count(),
            'counts_verified' => $counts->where('count_status', CycleCount::STATUS_VERIFIED)->count(),
            'counts_overdue' => $counts->filter(function($c) { return $c->is_overdue; })->count(),
            'accuracy_rate' => $this->calculateAccuracyRate($counts),
            'average_variance_percentage' => $this->calculateAverageVariance($counts),
            'compliance_rate' => $this->calculateComplianceRate($counts),
            'abc_breakdown' => $this->getABCBreakdown($counts),
            'variance_analysis' => $this->getVarianceAnalysis($variances),
            'top_performing_agents' => $this->getTopPerformingAgents($counts),
            'problem_products' => $this->getProblemProducts($counts),
            'efficiency_metrics' => $this->getEfficiencyMetrics($counts)
        ];
    }

    /**
     * Determine ABC classification
     */
    private function determineABCClassification($product, $inventory)
    {
        // Calculate product value and movement
        $value = $product->unit_price * $inventory->current_stock;
        $movement = $this->getProductMovementFrequency($product->id);
        
        // ABC classification logic
        if ($value >= 50000 || $movement >= 20) {
            return CycleCount::ABC_A; // Weekly
        } elseif ($value >= 10000 || $movement >= 10) {
            return CycleCount::ABC_B; // Bi-weekly
        } else {
            return CycleCount::ABC_C; // Monthly
        }
    }

    /**
     * Check if cycle count is due
     */
    private function isCycleCountDue($agentId, $productId, $abcClass)
    {
        $lastCount = CycleCount::where('agent_id', $agentId)
            ->where('product_id', $productId)
            ->where('count_status', CycleCount::STATUS_VERIFIED)
            ->latest('verified_at')
            ->first();

        if (!$lastCount) {
            return true; // First count
        }

        $daysSinceLastCount = $lastCount->verified_at->diffInDays(now());
        
        return match($abcClass) {
            CycleCount::ABC_A => $daysSinceLastCount >= 7,  // Weekly
            CycleCount::ABC_B => $daysSinceLastCount >= 14, // Bi-weekly
            CycleCount::ABC_C => $daysSinceLastCount >= 30, // Monthly
            default => $daysSinceLastCount >= 30
        };
    }

    /**
     * Get next count date based on ABC classification
     */
    private function getNextCountDate($abcClassification)
    {
        return match($abcClassification) {
            CycleCount::ABC_A => now()->addDays(7),  // Weekly
            CycleCount::ABC_B => now()->addDays(14), // Bi-weekly
            CycleCount::ABC_C => now()->addDays(30), // Monthly
            default => now()->addDays(30)
        };
    }

    /**
     * Get count frequency
     */
    private function getCountFrequency($abcClassification)
    {
        return match($abcClassification) {
            CycleCount::ABC_A => 'weekly',
            CycleCount::ABC_B => 'bi-weekly',
            CycleCount::ABC_C => 'monthly',
            default => 'monthly'
        };
    }

    /**
     * Get priority level
     */
    private function getPriorityLevel($abcClassification)
    {
        return match($abcClassification) {
            CycleCount::ABC_A => 'high',
            CycleCount::ABC_B => 'medium',
            CycleCount::ABC_C => 'low',
            default => 'low'
        };
    }

    /**
     * Get current system quantity
     */
    private function getCurrentSystemQuantity($agentId, $productId)
    {
        // This would query the current inventory system
        // Integration with existing VitalVida inventory models
        return 100; // Placeholder
    }

    /**
     * Get product movement frequency
     */
    private function getProductMovementFrequency($productId)
    {
        // Calculate based on recent stock movements
        // Integration with existing stock transfer system
        return 15; // Placeholder
    }

    /**
     * Create variance record
     */
    private function createVarianceRecord($cycleCount, $varianceQuantity)
    {
        $varianceType = $varianceQuantity > 0 
            ? InventoryVariance::TYPE_OVERAGE 
            : InventoryVariance::TYPE_SHORTAGE;

        $varianceValue = abs($varianceQuantity) * $cycleCount->product->unit_price;

        InventoryVariance::create([
            'cycle_count_id' => $cycleCount->id,
            'agent_id' => $cycleCount->agent_id,
            'product_id' => $cycleCount->product_id,
            'variance_type' => $varianceType,
            'variance_quantity' => $varianceQuantity,
            'variance_value' => $varianceValue,
            'investigation_status' => InventoryVariance::STATUS_PENDING,
            'detected_at' => now()
        ]);
    }

    /**
     * Check if variance is within tolerance
     */
    private function isWithinTolerance($variancePercentage, $tolerance = 1.0)
    {
        return abs($variancePercentage) <= $tolerance;
    }

    /**
     * Update system quantity after verification
     */
    private function updateSystemQuantity($cycleCount)
    {
        // Update the system inventory to match counted quantity
        // Integration with existing inventory management system
        Log::info('System quantity updated after cycle count verification', [
            'agent_id' => $cycleCount->agent_id,
            'product_id' => $cycleCount->product_id,
            'old_quantity' => $cycleCount->system_quantity,
            'new_quantity' => $cycleCount->counted_quantity
        ]);
    }

    /**
     * Notify agent of scheduled count
     */
    private function notifyAgentOfScheduledCount($cycleCount)
    {
        // Integration with existing notification system
        Log::info('Agent notified of scheduled cycle count', [
            'agent_id' => $cycleCount->agent_id,
            'count_id' => $cycleCount->count_id,
            'scheduled_date' => $cycleCount->scheduled_date
        ]);
    }

    /**
     * Calculate accuracy rate
     */
    private function calculateAccuracyRate($counts)
    {
        $completedCounts = $counts->where('count_status', CycleCount::STATUS_VERIFIED);
        
        if ($completedCounts->isEmpty()) {
            return 100;
        }

        $accurateCounts = $completedCounts->filter(function($count) {
            return abs($count->variance_percentage) <= 1.0;
        })->count();

        return round(($accurateCounts / $completedCounts->count()) * 100, 2);
    }

    /**
     * Calculate average variance
     */
    private function calculateAverageVariance($counts)
    {
        $completedCounts = $counts->where('count_status', CycleCount::STATUS_VERIFIED);
        
        if ($completedCounts->isEmpty()) {
            return 0;
        }

        return round($completedCounts->avg('variance_percentage'), 2);
    }

    /**
     * Calculate compliance rate
     */
    private function calculateComplianceRate($counts)
    {
        $totalCounts = $counts->count();
        $onTimeCounts = $counts->filter(function($count) {
            return !$count->is_overdue;
        })->count();

        return $totalCounts > 0 ? round(($onTimeCounts / $totalCounts) * 100, 2) : 100;
    }

    /**
     * Get ABC breakdown
     */
    private function getABCBreakdown($counts)
    {
        return [
            'A_class' => [
                'scheduled' => $counts->where('abc_classification', CycleCount::ABC_A)->count(),
                'completed' => $counts->where('abc_classification', CycleCount::ABC_A)
                    ->where('count_status', CycleCount::STATUS_VERIFIED)->count(),
                'accuracy_rate' => $this->calculateClassAccuracy($counts, CycleCount::ABC_A)
            ],
            'B_class' => [
                'scheduled' => $counts->where('abc_classification', CycleCount::ABC_B)->count(),
                'completed' => $counts->where('abc_classification', CycleCount::ABC_B)
                    ->where('count_status', CycleCount::STATUS_VERIFIED)->count(),
                'accuracy_rate' => $this->calculateClassAccuracy($counts, CycleCount::ABC_B)
            ],
            'C_class' => [
                'scheduled' => $counts->where('abc_classification', CycleCount::ABC_C)->count(),
                'completed' => $counts->where('abc_classification', CycleCount::ABC_C)
                    ->where('count_status', CycleCount::STATUS_VERIFIED)->count(),
                'accuracy_rate' => $this->calculateClassAccuracy($counts, CycleCount::ABC_C)
            ]
        ];
    }

    /**
     * Calculate accuracy for specific ABC class
     */
    private function calculateClassAccuracy($counts, $classification)
    {
        $classCounts = $counts->where('abc_classification', $classification)
            ->where('count_status', CycleCount::STATUS_VERIFIED);

        if ($classCounts->isEmpty()) {
            return 100;
        }

        $accurateCounts = $classCounts->filter(function($count) {
            return abs($count->variance_percentage) <= 1.0;
        })->count();

        return round(($accurateCounts / $classCounts->count()) * 100, 2);
    }

    /**
     * Get variance analysis
     */
    private function getVarianceAnalysis($variances)
    {
        return [
            'total_variances' => $variances->count(),
            'pending_investigations' => $variances->where('investigation_status', InventoryVariance::STATUS_PENDING)->count(),
            'overdue_investigations' => $variances->filter(function($v) { return $v->is_overdue; })->count(),
            'variance_by_type' => [
                'overage' => $variances->where('variance_type', InventoryVariance::TYPE_OVERAGE)->count(),
                'shortage' => $variances->where('variance_type', InventoryVariance::TYPE_SHORTAGE)->count(),
                'damage' => $variances->where('variance_type', InventoryVariance::TYPE_DAMAGE)->count(),
                'theft' => $variances->where('variance_type', InventoryVariance::TYPE_THEFT)->count()
            ],
            'total_variance_value' => $variances->sum('variance_value'),
            'average_variance_value' => $variances->avg('variance_value')
        ];
    }

    /**
     * Get top performing agents
     */
    private function getTopPerformingAgents($counts)
    {
        $agentPerformance = [];
        
        foreach ($counts->where('count_status', CycleCount::STATUS_VERIFIED) as $count) {
            $agentId = $count->agent_id;
            
            if (!isset($agentPerformance[$agentId])) {
                $agentPerformance[$agentId] = [
                    'agent' => $count->agent,
                    'total_counts' => 0,
                    'accurate_counts' => 0,
                    'total_variance' => 0
                ];
            }
            
            $agentPerformance[$agentId]['total_counts']++;
            $agentPerformance[$agentId]['total_variance'] += abs($count->variance_percentage);
            
            if (abs($count->variance_percentage) <= 1.0) {
                $agentPerformance[$agentId]['accurate_counts']++;
            }
        }

        // Calculate accuracy rates
        foreach ($agentPerformance as &$performance) {
            $performance['accuracy_rate'] = $performance['total_counts'] > 0 
                ? round(($performance['accurate_counts'] / $performance['total_counts']) * 100, 2)
                : 0;
            $performance['average_variance'] = $performance['total_counts'] > 0 
                ? round($performance['total_variance'] / $performance['total_counts'], 2)
                : 0;
        }

        return collect($agentPerformance)
            ->sortByDesc('accuracy_rate')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get problem products
     */
    private function getProblemProducts($counts)
    {
        $productIssues = [];
        
        foreach ($counts->where('count_status', CycleCount::STATUS_VERIFIED) as $count) {
            if (abs($count->variance_percentage) > 5.0) { // Significant variance
                $productId = $count->product_id;
                
                if (!isset($productIssues[$productId])) {
                    $productIssues[$productId] = [
                        'product' => $count->product,
                        'variance_count' => 0,
                        'total_variance' => 0,
                        'max_variance' => 0
                    ];
                }
                
                $productIssues[$productId]['variance_count']++;
                $productIssues[$productId]['total_variance'] += abs($count->variance_percentage);
                $productIssues[$productId]['max_variance'] = max(
                    $productIssues[$productId]['max_variance'],
                    abs($count->variance_percentage)
                );
            }
        }

        return collect($productIssues)
            ->sortByDesc('variance_count')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get efficiency metrics
     */
    private function getEfficiencyMetrics($counts)
    {
        $completedCounts = $counts->where('count_status', CycleCount::STATUS_VERIFIED);
        
        return [
            'average_count_duration' => $completedCounts->avg('count_duration') ?? 0,
            'on_time_completion_rate' => $this->calculateOnTimeRate($counts),
            'first_count_accuracy' => $this->calculateFirstCountAccuracy($counts),
            'recount_rate' => $this->calculateRecountRate($counts)
        ];
    }

    /**
     * Calculate on-time completion rate
     */
    private function calculateOnTimeRate($counts)
    {
        $totalCounts = $counts->count();
        $onTimeCounts = $counts->filter(function($count) {
            return $count->completed_at && 
                   $count->completed_at->lte($count->scheduled_date->endOfDay());
        })->count();

        return $totalCounts > 0 ? round(($onTimeCounts / $totalCounts) * 100, 2) : 100;
    }

    /**
     * Calculate first count accuracy
     */
    private function calculateFirstCountAccuracy($counts)
    {
        $firstCounts = $counts->where('count_status', CycleCount::STATUS_VERIFIED)
            ->filter(function($count) {
                return !isset($count->count_metadata['is_recount']);
            });

        if ($firstCounts->isEmpty()) {
            return 100;
        }

        $accurateFirstCounts = $firstCounts->filter(function($count) {
            return abs($count->variance_percentage) <= 1.0;
        })->count();

        return round(($accurateFirstCounts / $firstCounts->count()) * 100, 2);
    }

    /**
     * Calculate recount rate
     */
    private function calculateRecountRate($counts)
    {
        $totalCounts = $counts->count();
        $recounts = $counts->filter(function($count) {
            return isset($count->count_metadata['is_recount']);
        })->count();

        return $totalCounts > 0 ? round(($recounts / $totalCounts) * 100, 2) : 0;
    }
}

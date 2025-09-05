<?php

namespace App\Services;

use App\Models\ReturnItem;
use App\Models\DamageAssessment;
use App\Models\DeliveryAgent;
use App\Models\VitalVidaProduct;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ReverseLodisticsService
{
    protected $realTimeSyncService;

    public function __construct(RealTimeSyncService $realTimeSyncService)
    {
        $this->realTimeSyncService = $realTimeSyncService;
    }

    /**
     * Initiate return process
     */
    public function initiateReturn($agentId, $productId, $quantity, $reason, $returnType, $initiatedBy)
    {
        DB::beginTransaction();
        
        try {
            $returnId = 'RET-' . date('Ymd') . '-' . strtoupper(Str::random(6));

            $returnItem = ReturnItem::create([
                'return_id' => $returnId,
                'agent_id' => $agentId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'return_reason' => $reason,
                'return_type' => $returnType,
                'condition_on_return' => ReturnItem::CONDITION_UNKNOWN,
                'return_status' => ReturnItem::STATUS_INITIATED,
                'initiated_by' => $initiatedBy,
                'return_metadata' => [
                    'initiation_time' => now(),
                    'priority_level' => $this->determinePriorityLevel($reason, $returnType),
                    'expected_processing_time' => $this->getExpectedProcessingTime($returnType),
                    'quarantine_required' => $this->isQuarantineRequired($reason)
                ],
                'initiated_at' => now()
            ]);

            // Update agent stock
            $this->updateAgentStockForReturn($agentId, $productId, -$quantity, 'return_initiated');

            // Auto-quarantine if required
            if ($this->isQuarantineRequired($reason)) {
                $this->quarantineReturn($returnId, $initiatedBy);
            }

            Log::info('Return initiated', [
                'return_id' => $returnId,
                'agent_id' => $agentId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'reason' => $reason
            ]);

            $this->realTimeSyncService->broadcastReturnUpdate($returnItem);

            DB::commit();
            return $returnItem;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Return initiation failed', [
                'error' => $e->getMessage(),
                'agent_id' => $agentId,
                'product_id' => $productId
            ]);
            throw $e;
        }
    }

    /**
     * Quarantine returned items
     */
    public function quarantineReturn($returnId, $quarantinedBy, $location = null)
    {
        $returnItem = ReturnItem::where('return_id', $returnId)->firstOrFail();
        
        if ($returnItem->return_status !== ReturnItem::STATUS_INITIATED) {
            throw new \Exception('Return not in initiated status');
        }

        $quarantineLocation = $location ?? $this->assignQuarantineLocation($returnItem);

        $returnItem->update([
            'return_status' => ReturnItem::STATUS_QUARANTINED,
            'quarantine_location' => $quarantineLocation,
            'quarantined_at' => now(),
            'return_metadata' => array_merge($returnItem->return_metadata ?? [], [
                'quarantine_location' => $quarantineLocation,
                'quarantined_by' => $quarantinedBy,
                'quarantine_time' => now()
            ])
        ]);

        $this->realTimeSyncService->broadcastReturnUpdate($returnItem);
        
        return $returnItem;
    }

    /**
     * Inspect returned items
     */
    public function inspectReturn($returnId, $inspectedBy, $conditionAssessment, $inspectionNotes = null)
    {
        DB::beginTransaction();
        
        try {
            $returnItem = ReturnItem::where('return_id', $returnId)->firstOrFail();
            
            if ($returnItem->return_status !== ReturnItem::STATUS_QUARANTINED) {
                throw new \Exception('Return not in quarantined status');
            }

            $returnItem->update([
                'return_status' => ReturnItem::STATUS_INSPECTING,
                'condition_on_return' => $conditionAssessment,
                'processed_by' => $inspectedBy,
                'inspected_at' => now(),
                'return_metadata' => array_merge($returnItem->return_metadata ?? [], [
                    'inspection_notes' => $inspectionNotes,
                    'inspected_by' => $inspectedBy,
                    'inspection_time' => now()
                ])
            ]);

            // Create damage assessment if damaged
            if (in_array($conditionAssessment, [ReturnItem::CONDITION_DAMAGED, ReturnItem::CONDITION_CONTAMINATED])) {
                $this->createDamageAssessment($returnItem, $inspectedBy);
            }

            // Move to pending disposition
            $returnItem->update([
                'return_status' => ReturnItem::STATUS_PENDING_DISPOSITION
            ]);

            $this->realTimeSyncService->broadcastReturnUpdate($returnItem);

            DB::commit();
            return $returnItem;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Return inspection failed', [
                'error' => $e->getMessage(),
                'return_id' => $returnId
            ]);
            throw $e;
        }
    }

    /**
     * Make disposition decision
     */
    public function makeDispositionDecision($returnId, $disposition, $approvedBy, $dispositionNotes = null)
    {
        DB::beginTransaction();
        
        try {
            $returnItem = ReturnItem::where('return_id', $returnId)->firstOrFail();
            
            if ($returnItem->return_status !== ReturnItem::STATUS_PENDING_DISPOSITION) {
                throw new \Exception('Return not pending disposition');
            }

            $dispositionValue = $this->calculateDispositionValue($returnItem, $disposition);

            $returnItem->update([
                'return_status' => ReturnItem::STATUS_APPROVED,
                'disposition_decision' => $disposition,
                'disposition_value' => $dispositionValue,
                'approved_by' => $approvedBy,
                'processed_at' => now(),
                'return_metadata' => array_merge($returnItem->return_metadata ?? [], [
                    'disposition_notes' => $dispositionNotes,
                    'disposition_approved_by' => $approvedBy,
                    'disposition_time' => now(),
                    'disposition_value' => $dispositionValue
                ])
            ]);

            // Execute disposition
            $this->executeDisposition($returnItem, $disposition);

            $this->realTimeSyncService->broadcastReturnUpdate($returnItem);

            DB::commit();
            return $returnItem;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Disposition decision failed', [
                'error' => $e->getMessage(),
                'return_id' => $returnId
            ]);
            throw $e;
        }
    }

    /**
     * Complete return process
     */
    public function completeReturn($returnId, $completedBy)
    {
        $returnItem = ReturnItem::where('return_id', $returnId)->firstOrFail();
        
        if ($returnItem->return_status !== ReturnItem::STATUS_APPROVED) {
            throw new \Exception('Return not approved');
        }

        $returnItem->update([
            'return_status' => ReturnItem::STATUS_COMPLETED,
            'completed_at' => now(),
            'return_metadata' => array_merge($returnItem->return_metadata ?? [], [
                'completed_by' => $completedBy,
                'completion_time' => now(),
                'total_processing_time' => $returnItem->processing_time
            ])
        ]);

        $this->realTimeSyncService->broadcastReturnUpdate($returnItem);
        
        return $returnItem;
    }

    /**
     * Get returns analytics
     */
    public function getReturnsAnalytics($period = 'monthly')
    {
        $startDate = match($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'quarterly' => now()->startOfQuarter(),
            default => now()->startOfMonth()
        };

        $returns = ReturnItem::where('created_at', '>=', $startDate)->get();
        $assessments = DamageAssessment::whereHas('returnItem', function($q) use ($startDate) {
            $q->where('created_at', '>=', $startDate);
        })->get();

        return [
            'total_returns' => $returns->count(),
            'completed_returns' => $returns->where('return_status', ReturnItem::STATUS_COMPLETED)->count(),
            'pending_returns' => $returns->whereNotIn('return_status', [
                ReturnItem::STATUS_COMPLETED, 
                ReturnItem::STATUS_CANCELLED
            ])->count(),
            'overdue_returns' => $returns->filter(function($r) { return $r->is_overdue; })->count(),
            'return_value' => $returns->sum('estimated_value'),
            'recovered_value' => $this->calculateRecoveredValue($returns),
            'processing_efficiency' => $this->calculateProcessingEfficiency($returns),
            'return_reasons_breakdown' => $this->getReturnReasonsBreakdown($returns),
            'disposition_breakdown' => $this->getDispositionBreakdown($returns),
            'damage_analysis' => $this->getDamageAnalysis($assessments),
            'agent_return_rates' => $this->getAgentReturnRates($returns),
            'product_return_rates' => $this->getProductReturnRates($returns),
            'cost_analysis' => $this->getCostAnalysis($returns, $assessments)
        ];
    }

    /**
     * Determine priority level
     */
    private function determinePriorityLevel($reason, $returnType)
    {
        if ($returnType === ReturnItem::TYPE_EMERGENCY) {
            return 'critical';
        }

        if (in_array($reason, [ReturnItem::REASON_EXPIRED, ReturnItem::REASON_RECALL])) {
            return 'high';
        }

        if ($reason === ReturnItem::REASON_DAMAGED) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Get expected processing time
     */
    private function getExpectedProcessingTime($returnType)
    {
        return match($returnType) {
            ReturnItem::TYPE_EMERGENCY => 4,   // 4 hours
            ReturnItem::TYPE_MANDATORY => 24,  // 24 hours
            ReturnItem::TYPE_VOLUNTARY => 72,  // 72 hours
            ReturnItem::TYPE_ROUTINE => 168,   // 1 week
            default => 72
        };
    }

    /**
     * Check if quarantine is required
     */
    private function isQuarantineRequired($reason)
    {
        return in_array($reason, [
            ReturnItem::REASON_EXPIRED,
            ReturnItem::REASON_DAMAGED,
            ReturnItem::REASON_DEFECTIVE,
            ReturnItem::REASON_RECALL
        ]);
    }

    /**
     * Assign quarantine location
     */
    private function assignQuarantineLocation($returnItem)
    {
        // Logic to assign appropriate quarantine location
        $riskLevel = $returnItem->risk_level;
        
        return match($riskLevel) {
            'high' => 'QUARANTINE_ZONE_A_HIGH_RISK',
            'medium' => 'QUARANTINE_ZONE_B_MEDIUM_RISK',
            'low' => 'QUARANTINE_ZONE_C_LOW_RISK',
            default => 'QUARANTINE_ZONE_GENERAL'
        };
    }

    /**
     * Create damage assessment
     */
    private function createDamageAssessment($returnItem, $assessedBy)
    {
        $damageType = $this->determineDamageType($returnItem->return_reason);
        $severity = $this->estimateDamageSeverity($returnItem);

        DamageAssessment::create([
            'return_item_id' => $returnItem->id,
            'assessed_by' => $assessedBy,
            'damage_type' => $damageType,
            'damage_severity' => $severity,
            'damage_percentage' => $this->estimateDamagePercentage($severity),
            'salvage_value' => $this->estimateSalvageValue($returnItem, $severity),
            'recommended_disposition' => $this->recommendDisposition($damageType, $severity),
            'assessed_at' => now()
        ]);
    }

    /**
     * Determine damage type
     */
    private function determineDamageType($reason)
    {
        return match($reason) {
            ReturnItem::REASON_DAMAGED => DamageAssessment::TYPE_PHYSICAL,
            ReturnItem::REASON_EXPIRED => DamageAssessment::TYPE_EXPIRY,
            ReturnItem::REASON_DEFECTIVE => DamageAssessment::TYPE_PHYSICAL,
            default => DamageAssessment::TYPE_PHYSICAL
        };
    }

    /**
     * Estimate damage severity
     */
    private function estimateDamageSeverity($returnItem)
    {
        if ($returnItem->return_reason === ReturnItem::REASON_EXPIRED) {
            return DamageAssessment::SEVERITY_TOTAL;
        }

        // Default estimation - would be replaced with actual inspection
        return DamageAssessment::SEVERITY_MODERATE;
    }

    /**
     * Estimate damage percentage
     */
    private function estimateDamagePercentage($severity)
    {
        return match($severity) {
            DamageAssessment::SEVERITY_MINOR => 15,
            DamageAssessment::SEVERITY_MODERATE => 50,
            DamageAssessment::SEVERITY_MAJOR => 80,
            DamageAssessment::SEVERITY_TOTAL => 100,
            default => 50
        };
    }

    /**
     * Estimate salvage value
     */
    private function estimateSalvageValue($returnItem, $severity)
    {
        $originalValue = $returnItem->estimated_value;
        
        $recoveryRate = match($severity) {
            DamageAssessment::SEVERITY_MINOR => 0.85,
            DamageAssessment::SEVERITY_MODERATE => 0.50,
            DamageAssessment::SEVERITY_MAJOR => 0.20,
            DamageAssessment::SEVERITY_TOTAL => 0.00,
            default => 0.50
        };

        return $originalValue * $recoveryRate;
    }

    /**
     * Recommend disposition
     */
    private function recommendDisposition($damageType, $severity)
    {
        if ($severity === DamageAssessment::SEVERITY_TOTAL) {
            return ReturnItem::DISPOSITION_DESTROY;
        }

        if ($damageType === DamageAssessment::TYPE_EXPIRY) {
            return ReturnItem::DISPOSITION_DESTROY;
        }

        if ($severity === DamageAssessment::SEVERITY_MINOR) {
            return ReturnItem::DISPOSITION_RETURN_TO_STOCK;
        }

        return ReturnItem::DISPOSITION_SELL_AS_DAMAGED;
    }

    /**
     * Calculate disposition value
     */
    private function calculateDispositionValue($returnItem, $disposition)
    {
        $originalValue = $returnItem->estimated_value;
        
        return match($disposition) {
            ReturnItem::DISPOSITION_RETURN_TO_STOCK => $originalValue,
            ReturnItem::DISPOSITION_SELL_AS_DAMAGED => $originalValue * 0.3,
            ReturnItem::DISPOSITION_RETURN_TO_SUPPLIER => $originalValue * 0.8,
            ReturnItem::DISPOSITION_DONATE => 0,
            ReturnItem::DISPOSITION_DESTROY => 0,
            ReturnItem::DISPOSITION_REPAIR => $originalValue * 0.7,
            default => 0
        };
    }

    /**
     * Execute disposition
     */
    private function executeDisposition($returnItem, $disposition)
    {
        switch ($disposition) {
            case ReturnItem::DISPOSITION_RETURN_TO_STOCK:
                $this->returnToStock($returnItem);
                break;
            case ReturnItem::DISPOSITION_DESTROY:
                $this->scheduleDestruction($returnItem);
                break;
            case ReturnItem::DISPOSITION_RETURN_TO_SUPPLIER:
                $this->returnToSupplier($returnItem);
                break;
            case ReturnItem::DISPOSITION_DONATE:
                $this->scheduleDonation($returnItem);
                break;
            case ReturnItem::DISPOSITION_SELL_AS_DAMAGED:
                $this->markForDamagedSale($returnItem);
                break;
            case ReturnItem::DISPOSITION_REPAIR:
                $this->scheduleRepair($returnItem);
                break;
        }

        Log::info('Disposition executed', [
            'return_id' => $returnItem->return_id,
            'disposition' => $disposition
        ]);
    }

    /**
     * Return to stock
     */
    private function returnToStock($returnItem)
    {
        $this->updateAgentStockForReturn(
            $returnItem->agent_id, 
            $returnItem->product_id, 
            $returnItem->quantity, 
            'return_to_stock'
        );
    }

    /**
     * Schedule destruction
     */
    private function scheduleDestruction($returnItem)
    {
        // Schedule for destruction - would integrate with disposal service
        Log::info('Item scheduled for destruction', [
            'return_id' => $returnItem->return_id,
            'quantity' => $returnItem->quantity
        ]);
    }

    /**
     * Return to supplier
     */
    private function returnToSupplier($returnItem)
    {
        // Process return to supplier - would integrate with supplier management
        Log::info('Item returned to supplier', [
            'return_id' => $returnItem->return_id,
            'product_id' => $returnItem->product_id
        ]);
    }

    /**
     * Schedule donation
     */
    private function scheduleDonation($returnItem)
    {
        // Schedule for donation - would integrate with charity organizations
        Log::info('Item scheduled for donation', [
            'return_id' => $returnItem->return_id,
            'estimated_value' => $returnItem->estimated_value
        ]);
    }

    /**
     * Mark for damaged sale
     */
    private function markForDamagedSale($returnItem)
    {
        // Mark for sale at reduced price
        Log::info('Item marked for damaged sale', [
            'return_id' => $returnItem->return_id,
            'reduced_value' => $returnItem->disposition_value
        ]);
    }

    /**
     * Schedule repair
     */
    private function scheduleRepair($returnItem)
    {
        // Schedule for repair - would integrate with repair service
        Log::info('Item scheduled for repair', [
            'return_id' => $returnItem->return_id,
            'estimated_repair_cost' => $returnItem->estimated_value * 0.3
        ]);
    }

    /**
     * Calculate recovered value
     */
    private function calculateRecoveredValue($returns)
    {
        return $returns->where('return_status', ReturnItem::STATUS_COMPLETED)
                      ->sum('disposition_value');
    }

    /**
     * Calculate processing efficiency
     */
    private function calculateProcessingEfficiency($returns)
    {
        $completedReturns = $returns->where('return_status', ReturnItem::STATUS_COMPLETED);
        
        if ($completedReturns->isEmpty()) {
            return 100;
        }

        $onTimeReturns = $completedReturns->filter(function($return) {
            $expectedTime = $return->return_metadata['expected_processing_time'] ?? 72;
            return $return->processing_time <= $expectedTime;
        })->count();

        return round(($onTimeReturns / $completedReturns->count()) * 100, 2);
    }

    /**
     * Get return reasons breakdown
     */
    private function getReturnReasonsBreakdown($returns)
    {
        $breakdown = [];
        
        foreach ($returns as $return) {
            $reason = $return->return_reason;
            if (!isset($breakdown[$reason])) {
                $breakdown[$reason] = [
                    'count' => 0,
                    'value' => 0
                ];
            }
            $breakdown[$reason]['count']++;
            $breakdown[$reason]['value'] += $return->estimated_value;
        }

        return $breakdown;
    }

    /**
     * Get disposition breakdown
     */
    private function getDispositionBreakdown($returns)
    {
        $breakdown = [];
        
        foreach ($returns->where('disposition_decision', '!=', null) as $return) {
            $disposition = $return->disposition_decision;
            if (!isset($breakdown[$disposition])) {
                $breakdown[$disposition] = [
                    'count' => 0,
                    'original_value' => 0,
                    'recovered_value' => 0
                ];
            }
            $breakdown[$disposition]['count']++;
            $breakdown[$disposition]['original_value'] += $return->estimated_value;
            $breakdown[$disposition]['recovered_value'] += $return->disposition_value ?? 0;
        }

        return $breakdown;
    }

    /**
     * Get damage analysis
     */
    private function getDamageAnalysis($assessments)
    {
        return [
            'total_assessments' => $assessments->count(),
            'damage_types' => $assessments->groupBy('damage_type')->map->count(),
            'severity_distribution' => $assessments->groupBy('damage_severity')->map->count(),
            'average_damage_percentage' => $assessments->avg('damage_percentage'),
            'total_salvage_value' => $assessments->sum('salvage_value'),
            'recovery_rate' => $assessments->avg('recovery_rate')
        ];
    }

    /**
     * Get agent return rates
     */
    private function getAgentReturnRates($returns)
    {
        $agentStats = [];
        
        foreach ($returns as $return) {
            $agentId = $return->agent_id;
            
            if (!isset($agentStats[$agentId])) {
                $agentStats[$agentId] = [
                    'agent' => $return->agent,
                    'return_count' => 0,
                    'return_value' => 0,
                    'processing_efficiency' => 0
                ];
            }
            
            $agentStats[$agentId]['return_count']++;
            $agentStats[$agentId]['return_value'] += $return->estimated_value;
        }

        return collect($agentStats)
            ->sortByDesc('return_count')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get product return rates
     */
    private function getProductReturnRates($returns)
    {
        $productStats = [];
        
        foreach ($returns as $return) {
            $productId = $return->product_id;
            
            if (!isset($productStats[$productId])) {
                $productStats[$productId] = [
                    'product' => $return->product,
                    'return_count' => 0,
                    'return_quantity' => 0,
                    'return_value' => 0
                ];
            }
            
            $productStats[$productId]['return_count']++;
            $productStats[$productId]['return_quantity'] += $return->quantity;
            $productStats[$productId]['return_value'] += $return->estimated_value;
        }

        return collect($productStats)
            ->sortByDesc('return_count')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get cost analysis
     */
    private function getCostAnalysis($returns, $assessments)
    {
        return [
            'total_return_value' => $returns->sum('estimated_value'),
            'total_recovered_value' => $returns->sum('disposition_value'),
            'total_loss' => $returns->sum('estimated_value') - $returns->sum('disposition_value'),
            'recovery_percentage' => $returns->sum('estimated_value') > 0 
                ? round(($returns->sum('disposition_value') / $returns->sum('estimated_value')) * 100, 2)
                : 0,
            'processing_costs' => $this->estimateProcessingCosts($returns),
            'disposal_costs' => $assessments->sum('disposal_cost'),
            'repair_costs' => $assessments->sum('repair_cost')
        ];
    }

    /**
     * Estimate processing costs
     */
    private function estimateProcessingCosts($returns)
    {
        // Estimate based on processing time and labor costs
        $totalProcessingHours = $returns->sum('processing_time');
        $laborCostPerHour = 2000; // ₦2,000 per hour
        
        return $totalProcessingHours * $laborCostPerHour;
    }

    /**
     * Update agent stock for return operations
     */
    private function updateAgentStockForReturn($agentId, $productId, $quantity, $operation)
    {
        // Integration with existing stock management system
        Log::info('Stock updated for return operation', [
            'agent_id' => $agentId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'operation' => $operation
        ]);
    }
}

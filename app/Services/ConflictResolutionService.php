<?php

namespace App\Services;

use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\Bin;
use App\Services\ConflictDetectionService;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ConflictResolutionService
{
    private $redis;
    private $conflictDetectionService;
    private $realTimeSyncService;
    private $resolutionStrategies;

    public function __construct(
        ConflictDetectionService $conflictDetectionService,
        RealTimeSyncService $realTimeSyncService
    ) {
        $this->conflictDetectionService = $conflictDetectionService;
        $this->realTimeSyncService = $realTimeSyncService;
        // $this->redis = Redis::connection(); // Temporarily disabled
        
        $this->resolutionStrategies = [
            'missing_role_agent' => 'createMissingRoleAgent',
            'name_mismatch' => 'resolveNameMismatch',
            'performance_mismatch' => 'resolvePerformanceMismatch',
            'zone_mismatch' => 'resolveZoneMismatch',
            'status_mismatch' => 'resolveStatusMismatch',
            'stock_variance' => 'resolveStockVariance',
            'price_mismatch' => 'resolvePriceMismatch',
            'compliance_score_mismatch' => 'resolveComplianceMismatch',
            'enforcement_not_synced' => 'resolveEnforcementSync',
            'stale_sync_data' => 'resolveStaleSync'
        ];
    }

    /**
     * Auto-resolve all resolvable conflicts
     */
    public function autoResolveConflicts(): array
    {
        $conflicts = $this->conflictDetection->detectAllConflicts();
        $resolutionResults = [];

        foreach ($conflicts as $category => $categoryConflicts) {
            foreach ($categoryConflicts as $conflict) {
                if ($conflict['auto_resolvable']) {
                    $result = $this->resolveConflict($conflict);
                    $resolutionResults[] = $result;
                }
            }
        }

        // Update resolution statistics
        $this->updateResolutionStats($resolutionResults);

        Log::info('Auto-resolution completed', [
            'total_conflicts' => count($resolutionResults),
            'successful_resolutions' => count(array_filter($resolutionResults, fn($r) => $r['success']))
        ]);

        return $resolutionResults;
    }

    /**
     * Resolve a specific conflict
     */
    public function resolveConflict(array $conflict): array
    {
        try {
            $conflictType = $conflict['type'];
            
            if (!isset($this->resolutionStrategies[$conflictType])) {
                return [
                    'success' => false,
                    'conflict_type' => $conflictType,
                    'error' => 'No resolution strategy available',
                    'conflict_id' => $conflict['vital_agent_id'] ?? $conflict['bin_id'] ?? 'unknown'
                ];
            }

            $strategy = $this->resolutionStrategies[$conflictType];
            $result = $this->$strategy($conflict);

            // Log resolution attempt
            Log::info('Conflict resolution attempted', [
                'type' => $conflictType,
                'success' => $result['success'],
                'conflict_data' => $conflict
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Conflict resolution failed', [
                'conflict' => $conflict,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'conflict_type' => $conflict['type'],
                'error' => $e->getMessage(),
                'conflict_id' => $conflict['vital_agent_id'] ?? $conflict['bin_id'] ?? 'unknown'
            ];
        }
    }

    /**
     * Create missing Role agent
     */
    private function createMissingRoleAgent(array $conflict): array
    {
        $vitalAgent = VitalVidaDeliveryAgent::find($conflict['vital_agent_id']);
        
        if (!$vitalAgent) {
            return [
                'success' => false,
                'conflict_type' => 'missing_role_agent',
                'error' => 'VitalVida agent not found',
                'vital_agent_id' => $conflict['vital_agent_id']
            ];
        }

        $roleAgent = RoleDeliveryAgent::create([
            'external_id' => $vitalAgent->id,
            'agent_name' => $vitalAgent->name,
            'contact_number' => $vitalAgent->phone,
            'zone' => $this->mapLocationToZone($vitalAgent->location),
            'performance_score' => $vitalAgent->rating,
            'status' => $this->mapVitalVidaStatusToRole($vitalAgent->status),
            'compliance_score' => $vitalAgent->compliance_score ?? 100,
            'sync_timestamp' => now(),
            'created_via_sync' => true,
            'created_via_resolution' => true
        ]);

        return [
            'success' => true,
            'conflict_type' => 'missing_role_agent',
            'action' => 'created_role_agent',
            'vital_agent_id' => $vitalAgent->id,
            'role_agent_id' => $roleAgent->id,
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve name mismatch (VitalVida is source of truth)
     */
    private function resolveNameMismatch(array $conflict): array
    {
        $roleAgent = RoleDeliveryAgent::find($conflict['role_agent_id']);
        
        if (!$roleAgent) {
            return [
                'success' => false,
                'conflict_type' => 'name_mismatch',
                'error' => 'Role agent not found'
            ];
        }

        $oldName = $roleAgent->agent_name;
        $roleAgent->update([
            'agent_name' => $conflict['vital_value'],
            'sync_timestamp' => now()
        ]);

        return [
            'success' => true,
            'conflict_type' => 'name_mismatch',
            'action' => 'updated_role_agent_name',
            'role_agent_id' => $roleAgent->id,
            'old_value' => $oldName,
            'new_value' => $conflict['vital_value'],
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve performance mismatch
     */
    private function resolvePerformanceMismatch(array $conflict): array
    {
        $roleAgent = RoleDeliveryAgent::find($conflict['role_agent_id']);
        
        if (!$roleAgent) {
            return [
                'success' => false,
                'conflict_type' => 'performance_mismatch',
                'error' => 'Role agent not found'
            ];
        }

        $oldScore = $roleAgent->performance_score;
        $roleAgent->update([
            'performance_score' => $conflict['vital_value'],
            'performance_updated_at' => now(),
            'sync_timestamp' => now()
        ]);

        return [
            'success' => true,
            'conflict_type' => 'performance_mismatch',
            'action' => 'updated_performance_score',
            'role_agent_id' => $roleAgent->id,
            'old_value' => $oldScore,
            'new_value' => $conflict['vital_value'],
            'variance_resolved' => $conflict['variance'],
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve zone mismatch
     */
    private function resolveZoneMismatch(array $conflict): array
    {
        $roleAgent = RoleDeliveryAgent::find($conflict['role_agent_id']);
        
        if (!$roleAgent) {
            return [
                'success' => false,
                'conflict_type' => 'zone_mismatch',
                'error' => 'Role agent not found'
            ];
        }

        $oldZone = $roleAgent->zone;
        $roleAgent->update([
            'zone' => $conflict['vital_value'],
            'zone_updated_at' => now(),
            'sync_timestamp' => now()
        ]);

        // Update associated bins
        Bin::where('da_id', $roleAgent->id)->update([
            'zone' => $conflict['vital_value'],
            'zone_updated_at' => now()
        ]);

        return [
            'success' => true,
            'conflict_type' => 'zone_mismatch',
            'action' => 'updated_agent_zone',
            'role_agent_id' => $roleAgent->id,
            'old_value' => $oldZone,
            'new_value' => $conflict['vital_value'],
            'bins_updated' => true,
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve status mismatch
     */
    private function resolveStatusMismatch(array $conflict): array
    {
        $roleAgent = RoleDeliveryAgent::find($conflict['role_agent_id']);
        
        if (!$roleAgent) {
            return [
                'success' => false,
                'conflict_type' => 'status_mismatch',
                'error' => 'Role agent not found'
            ];
        }

        $oldStatus = $roleAgent->status;
        $roleAgent->update([
            'status' => $conflict['vital_value'],
            'status_updated_at' => now(),
            'sync_timestamp' => now()
        ]);

        // Handle status-specific actions
        if ($conflict['vital_value'] === 'suspended') {
            Bin::where('da_id', $roleAgent->id)->update([
                'bin_status' => 'suspended',
                'suspended_at' => now()
            ]);
        } elseif ($oldStatus === 'suspended' && $conflict['vital_value'] === 'active') {
            Bin::where('da_id', $roleAgent->id)->update([
                'bin_status' => 'active',
                'suspended_at' => null
            ]);
        }

        return [
            'success' => true,
            'conflict_type' => 'status_mismatch',
            'action' => 'updated_agent_status',
            'role_agent_id' => $roleAgent->id,
            'old_value' => $oldStatus,
            'new_value' => $conflict['vital_value'],
            'bins_affected' => $conflict['vital_value'] === 'suspended' || $oldStatus === 'suspended',
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve stock variance
     */
    private function resolveStockVariance(array $conflict): array
    {
        // For stock variance, we typically need to investigate the root cause
        // For now, we'll log the variance and mark for manual review
        
        $bin = Bin::find($conflict['bin_id']);
        if (!$bin) {
            return [
                'success' => false,
                'conflict_type' => 'stock_variance',
                'error' => 'Bin not found'
            ];
        }

        // Create a stock adjustment record
        DB::table('stock_adjustments')->insert([
            'bin_id' => $bin->id,
            'agent_id' => $conflict['agent_id'],
            'product_sku' => $conflict['product_sku'],
            'recorded_stock' => $conflict['bin_stock'],
            'expected_stock' => $conflict['expected_stock'],
            'variance' => $conflict['variance'],
            'adjustment_type' => 'conflict_resolution',
            'status' => 'pending_review',
            'created_at' => now(),
            'detected_at' => $conflict['detected_at']
        ]);

        return [
            'success' => true,
            'conflict_type' => 'stock_variance',
            'action' => 'created_adjustment_record',
            'bin_id' => $bin->id,
            'variance' => $conflict['variance'],
            'status' => 'pending_manual_review',
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve price mismatch
     */
    private function resolvePriceMismatch(array $conflict): array
    {
        $bin = Bin::find($conflict['bin_id']);
        if (!$bin) {
            return [
                'success' => false,
                'conflict_type' => 'price_mismatch',
                'error' => 'Bin not found'
            ];
        }

        $oldPrice = $bin->unit_price;
        $bin->update([
            'unit_price' => $conflict['vital_price'],
            'price_updated_at' => now()
        ]);

        return [
            'success' => true,
            'conflict_type' => 'price_mismatch',
            'action' => 'updated_bin_price',
            'bin_id' => $bin->id,
            'old_value' => $oldPrice,
            'new_value' => $conflict['vital_price'],
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve compliance mismatch
     */
    private function resolveComplianceMismatch(array $conflict): array
    {
        $roleAgent = RoleDeliveryAgent::find($conflict['role_agent_id']);
        
        if (!$roleAgent) {
            return [
                'success' => false,
                'conflict_type' => 'compliance_score_mismatch',
                'error' => 'Role agent not found'
            ];
        }

        $oldScore = $roleAgent->compliance_score;
        $roleAgent->update([
            'compliance_score' => $conflict['vital_score'],
            'compliance_updated_at' => now(),
            'sync_timestamp' => now()
        ]);

        return [
            'success' => true,
            'conflict_type' => 'compliance_score_mismatch',
            'action' => 'updated_compliance_score',
            'role_agent_id' => $roleAgent->id,
            'old_value' => $oldScore,
            'new_value' => $conflict['vital_score'],
            'difference_resolved' => $conflict['difference'],
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve enforcement sync
     */
    private function resolveEnforcementSync(array $conflict): array
    {
        $roleAgent = RoleDeliveryAgent::find($conflict['role_agent_id']);
        
        if (!$roleAgent) {
            return [
                'success' => false,
                'conflict_type' => 'enforcement_not_synced',
                'error' => 'Role agent not found'
            ];
        }

        // Apply suspension to Role system
        $roleAgent->update([
            'status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Synced from VitalVida system',
            'sync_timestamp' => now()
        ]);

        // Suspend all bins
        Bin::where('da_id', $roleAgent->id)->update([
            'bin_status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => 'Agent suspended'
        ]);

        return [
            'success' => true,
            'conflict_type' => 'enforcement_not_synced',
            'action' => 'applied_suspension',
            'role_agent_id' => $roleAgent->id,
            'bins_suspended' => true,
            'resolved_at' => now()
        ];
    }

    /**
     * Resolve stale sync data
     */
    private function resolveStaleSync(array $conflict): array
    {
        // Trigger fresh sync for the agent
        $syncResult = $this->realTimeSync->syncAgentRealTime(
            $conflict['vital_agent_id'], 
            'stale_data_refresh'
        );

        return [
            'success' => $syncResult['success'],
            'conflict_type' => 'stale_sync_data',
            'action' => 'triggered_fresh_sync',
            'vital_agent_id' => $conflict['vital_agent_id'],
            'role_agent_id' => $conflict['role_agent_id'],
            'sync_result' => $syncResult,
            'resolved_at' => now()
        ];
    }

    /**
     * Get resolution statistics
     */
    public function getResolutionStats(): array
    {
        $cacheKey = 'resolution_stats';
        $stats = $this->redis->get($cacheKey);
        
        if ($stats) {
            return json_decode($stats, true);
        }

        return [
            'total_resolutions_24h' => 0,
            'successful_resolutions_24h' => 0,
            'failed_resolutions_24h' => 0,
            'by_type' => [],
            'success_rate' => 0,
            'last_updated' => now()->toISOString()
        ];
    }

    /**
     * Manual conflict resolution for complex cases
     */
    public function manualResolve(array $conflict, array $resolutionAction): array
    {
        try {
            DB::beginTransaction();

            // Log manual resolution
            DB::table('manual_conflict_resolutions')->insert([
                'conflict_type' => $conflict['type'],
                'conflict_data' => json_encode($conflict),
                'resolution_action' => json_encode($resolutionAction),
                'resolved_by' => $resolutionAction['resolved_by'] ?? 'system',
                'resolution_notes' => $resolutionAction['notes'] ?? '',
                'resolved_at' => now()
            ]);

            // Apply the resolution based on action type
            $result = $this->applyManualResolution($conflict, $resolutionAction);

            DB::commit();

            return [
                'success' => true,
                'conflict_type' => $conflict['type'],
                'resolution_applied' => $result,
                'resolved_at' => now()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Manual conflict resolution failed', [
                'conflict' => $conflict,
                'resolution_action' => $resolutionAction,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'conflict_type' => $conflict['type']
            ];
        }
    }

    // Helper methods
    private function updateResolutionStats(array $results)
    {
        $successful = count(array_filter($results, fn($r) => $r['success']));
        $failed = count($results) - $successful;

        $stats = [
            'total_resolutions_24h' => count($results),
            'successful_resolutions_24h' => $successful,
            'failed_resolutions_24h' => $failed,
            'success_rate' => count($results) > 0 ? round(($successful / count($results)) * 100, 2) : 0,
            'last_updated' => now()->toISOString()
        ];

        $this->redis->setex('resolution_stats', 86400, json_encode($stats));
    }

    private function applyManualResolution(array $conflict, array $action): array
    {
        // Implementation would depend on specific manual resolution requirements
        return ['status' => 'applied', 'action' => $action['type'] ?? 'custom'];
    }

    private function mapLocationToZone($location): string
    {
        $zoneMapping = [
            'Lagos' => 'Lagos',
            'Victoria Island' => 'Lagos', 
            'Ikeja' => 'Lagos',
            'Lekki' => 'Lagos',
            'Surulere' => 'Lagos',
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
        
        return 'Lagos';
    }

    private function mapVitalVidaStatusToRole($vitalVidaStatus): string
    {
        return match($vitalVidaStatus) {
            'Active' => 'active',
            'Inactive' => 'inactive',
            'On Delivery' => 'on_delivery',
            'Break' => 'on_break',
            'Suspended' => 'suspended',
            'Training Required' => 'training',
            default => 'active'
        };
    }
}

<?php

namespace App\Services;

use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\Bin;
use App\Events\AgentUpdatedEvent;
use App\Events\StockAllocatedEvent;
use App\Events\ComplianceActionEvent;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class RealTimeSyncService
{
    private $redis;

    public function __construct()
    {
        $this->redis = null; // Temporarily disable Redis
    }

    /**
     * Real-time agent synchronization
     */
    public function syncAgentRealTime($agentId, $updateType = 'general')
    {
        try {
            $vitalAgent = VitalVidaDeliveryAgent::find($agentId);
            if (!$vitalAgent) {
                throw new \Exception("VitalVida agent not found: {$agentId}");
            }

            // Get previous data for comparison
            $previousData = $this->getAgentPreviousData($agentId);

            // Update Role system
            $roleAgent = RoleDeliveryAgent::updateOrCreate([
                'external_id' => $agentId
            ], [
                'agent_name' => $vitalAgent->name,
                'contact_number' => $vitalAgent->phone,
                'zone' => $this->mapLocationToZone($vitalAgent->location),
                'performance_score' => $vitalAgent->rating,
                'status' => $this->mapVitalVidaStatusToRole($vitalAgent->status),
                'compliance_score' => $vitalAgent->compliance_score ?? 100,
                'sync_timestamp' => now(),
                'created_via_sync' => true
            ]);

            // Cache sync status (if Redis available)
            if ($this->redis) {
                $this->redis->setex("sync_status_agent_{$agentId}", 300, json_encode([
                    'last_sync' => now(),
                    'status' => 'success',
                    'update_type' => $updateType
                ]));
            }

            // Broadcast real-time update
            broadcast(new AgentUpdatedEvent($vitalAgent, $updateType));

            return [
                'success' => true,
                'agent_id' => $agentId,
                'sync_timestamp' => now(),
                'update_type' => $updateType,
                'role_agent_updated' => $roleAgent ? true : false
            ];

        } catch (\Exception $e) {
            Log::error("Real-time agent sync failed: {$e->getMessage()}", [
                'agent_id' => $agentId,
                'update_type' => $updateType
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'agent_id' => $agentId
            ];
        }
    }

    /**
     * Real-time stock allocation synchronization
     */
    public function syncStockAllocation($allocationData)
    {
        try {
            $agentId = $allocationData['agent_id'];
            $productCode = $allocationData['product_code'];
            $quantity = $allocationData['quantity'];

            // Get VitalVida entities
            $vitalAgent = VitalVidaDeliveryAgent::find($agentId);
            $vitalProduct = VitalVidaProduct::where('code', $productCode)->first();

            if (!$vitalAgent || !$vitalProduct) {
                throw new \Exception("Agent or product not found for allocation");
            }

            // Get or create Role agent
            $roleAgent = RoleDeliveryAgent::where('external_id', $agentId)->first();
            if (!$roleAgent) {
                $roleAgent = RoleDeliveryAgent::create([
                    'external_id' => $agentId,
                    'agent_name' => $vitalAgent->name,
                    'contact_number' => $vitalAgent->phone,
                    'zone' => $this->mapLocationToZone($vitalAgent->location),
                    'created_via_sync' => true
                ]);
            }

            // Update or create bin
            $bin = Bin::updateOrCreate([
                'da_id' => $roleAgent->id,
                'product_sku' => $productCode
            ], [
                'product_name' => $vitalProduct->name,
                'current_stock' => DB::raw("current_stock + {$quantity}"),
                'unit_price' => $vitalProduct->unit_price,
                'supplier_name' => $vitalProduct->supplier->company_name ?? 'Unknown',
                'bin_status' => 'active',
                'last_updated' => now(),
                'allocation_count' => DB::raw('allocation_count + 1')
            ]);

            // Update Redis cache
            $this->updateRedisStockCache($agentId, $productCode, $quantity);

            // Create allocation record
            $allocation = (object) [
                'id' => uniqid('alloc_'),
                'agent_id' => $agentId,
                'product_id' => $vitalProduct->id,
                'quantity' => $quantity,
                'allocated_at' => $allocationData['allocated_at'] ?? now()
            ];

            // Trigger real-time event
            event(new StockAllocatedEvent($allocation, $vitalAgent, $vitalProduct));

            return [
                'success' => true,
                'allocation_id' => $allocation->id,
                'bin_id' => $bin->id,
                'agent_id' => $agentId,
                'product_code' => $productCode,
                'quantity' => $quantity,
                'sync_timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error("Real-time stock allocation sync failed: {$e->getMessage()}", [
                'allocation_data' => $allocationData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'allocation_data' => $allocationData
            ];
        }
    }

    /**
     * Real-time compliance action synchronization
     */
    public function syncComplianceAction($actionData)
    {
        try {
            $agentId = $actionData['agent_id'];
            $actionType = $actionData['action_type'];
            $severity = $actionData['severity'] ?? 'medium';
            $reason = $actionData['reason'] ?? '';

            $vitalAgent = VitalVidaDeliveryAgent::find($agentId);
            if (!$vitalAgent) {
                throw new \Exception("VitalVida agent not found: {$agentId}");
            }

            // Update compliance score in VitalVida
            $complianceScore = $this->calculateComplianceScore($actionType, $severity);
            $vitalAgent->update([
                'compliance_score' => $complianceScore,
                'last_compliance_action' => $actionType,
                'compliance_updated_at' => now()
            ]);

            // Update Role system
            $roleAgent = RoleDeliveryAgent::where('external_id', $agentId)->first();
            if ($roleAgent) {
                $roleAgent->update([
                    'compliance_score' => $complianceScore,
                    'last_compliance_action' => $actionType,
                    'compliance_updated_at' => now()
                ]);

                // Handle specific actions
                $this->handleSpecificComplianceAction($roleAgent, $actionType, $severity, $reason);
            }

            // Update Redis cache
            $this->updateRedisComplianceCache($agentId, $complianceScore, $actionType);

            // Trigger real-time event
            event(new ComplianceActionEvent($vitalAgent, $actionType, $severity, $reason));

            return [
                'success' => true,
                'agent_id' => $agentId,
                'action_type' => $actionType,
                'new_compliance_score' => $complianceScore,
                'sync_timestamp' => now()
            ];

        } catch (\Exception $e) {
            Log::error("Real-time compliance action sync failed: {$e->getMessage()}", [
                'action_data' => $actionData
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
                'action_data' => $actionData
            ];
        }
    }

    /**
     * Get real-time sync status
     */
    public function getSyncStatus()
    {
        $last24Hours = now()->subDay();
        
        return [
            'sync_health' => $this->calculateSyncHealth(),
            'total_syncs' => $this->redis->get('sync_stats:total_syncs:24h') ?? 0,
            'failed_syncs' => $this->redis->get('sync_stats:failed_syncs:24h') ?? 0,
            'agent_syncs' => $this->redis->get('sync_stats:agent_syncs:24h') ?? 0,
            'stock_syncs' => $this->redis->get('sync_stats:stock_syncs:24h') ?? 0,
            'compliance_syncs' => $this->redis->get('sync_stats:compliance_syncs:24h') ?? 0,
            'last_sync_time' => $this->redis->get('sync_stats:last_sync_time'),
            'active_connections' => $this->getActiveConnections(),
            'queue_status' => $this->getQueueStatus()
        ];
    }

    /**
     * Trigger enforcement workflow
     */
    public function triggerEnforcement($agentId, $actionType, $reason)
    {
        try {
            $vitalAgent = VitalVidaDeliveryAgent::find($agentId);
            if (!$vitalAgent) {
                throw new \Exception("Agent not found");
            }

            // Execute enforcement in both systems
            $enforcementResult = $this->executeEnforcementAction($vitalAgent, $actionType, $reason);

            // Log enforcement action
            Log::warning("Enforcement triggered", [
                'agent_id' => $agentId,
                'action_type' => $actionType,
                'reason' => $reason,
                'result' => $enforcementResult
            ]);

            // Create enforcement record
            DB::table('enforcement_actions')->insert([
                'agent_id' => $agentId,
                'action_type' => $actionType,
                'reason' => $reason,
                'severity' => 'critical',
                'triggered_at' => now(),
                'status' => 'executed',
                'result' => json_encode($enforcementResult)
            ]);

            return [
                'success' => true,
                'enforcement_triggered' => true,
                'agent_id' => $agentId,
                'action_type' => $actionType,
                'result' => $enforcementResult
            ];

        } catch (\Exception $e) {
            Log::error("Enforcement trigger failed: {$e->getMessage()}");
            throw $e;
        }
    }

    // Helper methods
    private function getAgentPreviousData($agentId)
    {
        $cacheKey = "agent_data:{$agentId}";
        return json_decode($this->redis->get($cacheKey), true) ?? [];
    }

    private function cacheAgentData($agentId, $data)
    {
        $cacheKey = "agent_data:{$agentId}";
        $this->redis->setex($cacheKey, 3600, json_encode($data));
    }

    private function updateRedisAgentCache($agentId, $vitalAgent, $roleAgent)
    {
        $cacheData = [
            'vitalvida_id' => $vitalAgent->id,
            'role_id' => $roleAgent->id,
            'name' => $vitalAgent->name,
            'status' => $vitalAgent->status,
            'rating' => $vitalAgent->rating,
            'zone' => $this->mapLocationToZone($vitalAgent->location),
            'compliance_score' => $vitalAgent->compliance_score ?? 100,
            'last_updated' => now()->toISOString()
        ];

        $this->redis->setex("realtime_agent:{$agentId}", 3600, json_encode($cacheData));
    }

    private function updateRedisStockCache($agentId, $productCode, $quantity)
    {
        $cacheKey = "realtime_stock:{$agentId}:{$productCode}";
        $currentStock = $this->redis->get($cacheKey) ?? 0;
        $newStock = $currentStock + $quantity;
        
        $this->redis->setex($cacheKey, 3600, $newStock);
        
        // Update allocation stats
        $this->redis->incr('sync_stats:stock_syncs:24h');
        $this->redis->expire('sync_stats:stock_syncs:24h', 86400);
    }

    private function updateRedisComplianceCache($agentId, $complianceScore, $actionType)
    {
        $cacheData = [
            'compliance_score' => $complianceScore,
            'last_action' => $actionType,
            'updated_at' => now()->toISOString()
        ];

        $this->redis->setex("realtime_compliance:{$agentId}", 3600, json_encode($cacheData));
        
        // Update compliance stats
        $this->redis->incr('sync_stats:compliance_syncs:24h');
        $this->redis->expire('sync_stats:compliance_syncs:24h', 86400);
    }

    private function calculateComplianceScore($actionType, $severity): int
    {
        $baseScore = 100;
        
        $penalties = [
            'warning' => ['low' => 5, 'medium' => 10, 'high' => 15, 'critical' => 20],
            'suspend' => ['low' => 20, 'medium' => 25, 'high' => 30, 'critical' => 40],
            'reduce_allocation' => ['low' => 10, 'medium' => 15, 'high' => 20, 'critical' => 25],
            'mandatory_training' => ['low' => 5, 'medium' => 10, 'high' => 15, 'critical' => 20]
        ];

        $penalty = $penalties[$actionType][$severity] ?? 10;
        return max(0, $baseScore - $penalty);
    }

    private function handleSpecificComplianceAction($roleAgent, $actionType, $severity, $reason)
    {
        switch ($actionType) {
            case 'suspend':
                $roleAgent->update(['status' => 'suspended', 'suspended_at' => now()]);
                break;
                
            case 'reduce_allocation':
                $roleAgent->update(['allocation_restricted' => true, 'restricted_at' => now()]);
                break;
                
            case 'warning':
                $roleAgent->increment('violation_count');
                break;
                
            case 'mandatory_training':
                $roleAgent->update(['training_required' => true, 'training_assigned_at' => now()]);
                break;
        }
    }

    private function executeEnforcementAction($agent, $actionType, $reason)
    {
        // Implementation would depend on specific enforcement requirements
        return [
            'action_executed' => $actionType,
            'agent_affected' => $agent->id,
            'timestamp' => now()
        ];
    }

    private function calculateSyncHealth(): float
    {
        $totalSyncs = $this->redis->get('sync_stats:total_syncs:24h') ?? 1;
        $failedSyncs = $this->redis->get('sync_stats:failed_syncs:24h') ?? 0;
        
        return $totalSyncs > 0 ? round((($totalSyncs - $failedSyncs) / $totalSyncs) * 100, 2) : 100;
    }

    private function getActiveConnections(): int
    {
        return (int) $this->redis->get('active_sync_connections') ?? 0;
    }

    private function getQueueStatus(): array
    {
        return [
            'high_priority' => \Queue::size('high-priority-sync'),
            'normal' => \Queue::size('normal-sync'),
            'compliance' => \Queue::size('compliance-sync')
        ];
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

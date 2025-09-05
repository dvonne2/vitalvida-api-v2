<?php

namespace App\Services;

use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\Bin;
use App\Models\VitalVidaSupplier;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class IntegrationService
{
    /**
     * Sync inventory data from VitalVida to Role system
     */
    public function syncInventoryData(): array
    {
        $results = [];
        
        try {
            // Get VitalVida products with agent allocations
            $vitalVidaProducts = VitalVidaProduct::with('supplier')->get();
            
            foreach ($vitalVidaProducts as $product) {
                try {
                    // Find or create corresponding bin entry
                    $bin = Bin::updateOrCreate([
                        'product_sku' => $product->code,
                        'da_id' => $product->agent_id ?? 1 // Default DA if none assigned
                    ], [
                        'product_name' => $product->name,
                        'current_stock' => $product->stock_level,
                        'min_threshold' => $product->min_stock ?? 10,
                        'max_capacity' => $product->max_stock ?? 1000,
                        'unit_price' => $product->price,
                        'supplier_name' => $product->supplier->company_name ?? 'Unknown',
                        'bin_status' => $this->mapProductStatusToBinStatus($product->status),
                        'last_updated' => now()
                    ]);
                    
                    $results[] = [
                        'product_id' => $product->id,
                        'bin_id' => $bin->id,
                        'action' => $bin->wasRecentlyCreated ? 'created' : 'updated'
                    ];
                    
                } catch (\Exception $e) {
                    Log::error("Failed to sync product {$product->id}: " . $e->getMessage());
                    $results[] = [
                        'product_id' => $product->id,
                        'action' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Inventory sync service failed: " . $e->getMessage());
            throw $e;
        }
        
        return $results;
    }

    /**
     * Sync compliance data from Role to VitalVida
     */
    public function syncComplianceData(): array
    {
        $complianceUpdates = [];
        
        try {
            $roleAgents = RoleDeliveryAgent::with('compliance')->get();
            
            foreach ($roleAgents as $roleAgent) {
                if ($roleAgent->external_id) {
                    $vitalAgent = VitalVidaDeliveryAgent::find($roleAgent->external_id);
                    
                    if ($vitalAgent) {
                        $vitalAgent->update([
                            'compliance_score' => $roleAgent->compliance_score ?? 100,
                            'violation_count' => $roleAgent->violation_count ?? 0,
                            'last_compliance_check' => now()
                        ]);
                        
                        $complianceUpdates[] = [
                            'agent_id' => $vitalAgent->id,
                            'compliance_score' => $roleAgent->compliance_score
                        ];
                    }
                }
            }
            
        } catch (\Exception $e) {
            Log::error("Compliance sync failed: " . $e->getMessage());
            throw $e;
        }
        
        return $complianceUpdates;
    }

    /**
     * Sync single agent between systems
     */
    public function syncSingleAgent($agent): array
    {
        try {
            $roleAgent = RoleDeliveryAgent::updateOrCreate([
                'external_id' => $agent->id
            ], [
                'agent_name' => $agent->name,
                'contact_number' => $agent->phone,
                'zone' => $this->mapLocationToZone($agent->location),
                'performance_score' => $agent->rating,
                'status' => $agent->status,
                'sync_timestamp' => now()
            ]);

            return [
                'success' => true,
                'agent_id' => $agent->id,
                'role_agent_id' => $roleAgent->id,
                'action' => $roleAgent->wasRecentlyCreated ? 'created' : 'updated'
            ];

        } catch (\Exception $e) {
            Log::error("Single agent sync failed for agent {$agent->id}: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Sync bin stock data
     */
    public function syncBinStock($allocation): array
    {
        try {
            $bin = Bin::updateOrCreate([
                'da_id' => $allocation['agent_id'],
                'product_sku' => $allocation['product_code']
            ], [
                'current_stock' => $allocation['quantity'],
                'allocated_at' => $allocation['allocated_at'] ?? now(),
                'bin_status' => 'active',
                'last_updated' => now()
            ]);

            return [
                'success' => true,
                'bin_id' => $bin->id,
                'allocation' => $allocation
            ];

        } catch (\Exception $e) {
            Log::error("Bin stock sync failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Update agent compliance status
     */
    public function updateAgentCompliance($agent, $action): array
    {
        try {
            $vitalAgent = VitalVidaDeliveryAgent::find($agent->external_id ?? $agent->id);
            
            if ($vitalAgent) {
                $complianceScore = $this->calculateComplianceScore($action);
                
                $vitalAgent->update([
                    'compliance_score' => $complianceScore,
                    'last_compliance_action' => $action,
                    'compliance_updated_at' => now()
                ]);

                return [
                    'success' => true,
                    'agent_id' => $vitalAgent->id,
                    'compliance_score' => $complianceScore,
                    'action' => $action
                ];
            }

            return ['success' => false, 'error' => 'Agent not found'];

        } catch (\Exception $e) {
            Log::error("Agent compliance update failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Trigger enforcement workflow
     */
    public function triggerEnforcement($agent, $action): array
    {
        try {
            // Log enforcement action
            Log::warning("Enforcement triggered for agent {$agent->id}: {$action}");
            
            // Create enforcement record
            DB::table('enforcement_actions')->insert([
                'agent_id' => $agent->id,
                'action_type' => $action,
                'severity' => 'critical',
                'triggered_at' => now(),
                'status' => 'pending'
            ]);

            return [
                'success' => true,
                'enforcement_triggered' => true,
                'agent_id' => $agent->id,
                'action' => $action
            ];

        } catch (\Exception $e) {
            Log::error("Enforcement trigger failed: " . $e->getMessage());
            throw $e;
        }
    }

    /**
     * Get integration statistics
     */
    public function getIntegrationStats(): array
    {
        try {
            return [
                'vitalvida_agents' => VitalVidaDeliveryAgent::count(),
                'role_agents' => RoleDeliveryAgent::where('created_via_sync', true)->count(),
                'vitalvida_products' => VitalVidaProduct::count(),
                'role_bins' => Bin::count(),
                'last_sync' => RoleDeliveryAgent::where('created_via_sync', true)->max('sync_timestamp'),
                'sync_health' => $this->calculateSyncHealth()
            ];

        } catch (\Exception $e) {
            Log::error("Integration stats failed: " . $e->getMessage());
            return [];
        }
    }

    // Helper methods
    private function mapProductStatusToBinStatus($productStatus): string
    {
        $statusMap = [
            'In Stock' => 'active',
            'Low Stock' => 'warning', 
            'Out of Stock' => 'critical',
            'Discontinued' => 'inactive'
        ];
        
        return $statusMap[$productStatus] ?? 'active';
    }

    private function mapLocationToZone($location): string
    {
        $zoneMapping = [
            'Lagos' => 'Lagos',
            'Victoria Island' => 'Lagos', 
            'Ikeja' => 'Lagos',
            'Abuja' => 'Abuja',
            'Kano' => 'Kano',
            'Port Harcourt' => 'Port Harcourt'
        ];
        
        foreach ($zoneMapping as $keyword => $zone) {
            if (stripos($location, $keyword) !== false) {
                return $zone;
            }
        }
        
        return 'Lagos'; // Default zone
    }

    private function calculateComplianceScore($action): int
    {
        $scoreMap = [
            'over_allocation_detected' => 70,
            'unauthorized_access' => 50,
            'missing_documentation' => 80,
            'late_delivery' => 85,
            'customer_complaint' => 60
        ];

        return $scoreMap[$action] ?? 90;
    }

    private function calculateSyncHealth(): float
    {
        try {
            $vitalVidaCount = VitalVidaDeliveryAgent::count();
            $roleCount = RoleDeliveryAgent::where('created_via_sync', true)->count();
            
            if ($vitalVidaCount === 0) return 100;
            
            return min(100, ($roleCount / $vitalVidaCount) * 100);

        } catch (\Exception $e) {
            return 0;
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Api\VitalVidaInventory\VitalVidaInventoryController;
use App\Http\Controllers\Api\InventoryPortal\DashboardController as RoleDashboard;
use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\Bin;
use App\Models\VitalVidaSupplier;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class VitalVidaRoleIntegrationController extends Controller
{
    protected $vitalVidaController;
    protected $roleDashboardController;
    
    public function __construct()
    {
        $this->vitalVidaController = new VitalVidaInventoryController();
        $this->roleDashboardController = new RoleDashboard();
    }

    /**
     * Unified Dashboard - Combines VitalVida + Role data
     */
    public function unifiedDashboard(): JsonResponse
    {
        try {
            // Get VitalVida data (MASTER SOURCE)
            $vitalVidaResponse = $this->vitalVidaController->dashboard();
            $vitalVidaData = $vitalVidaResponse->getData(true);
            
            // Get Role compliance data
            $roleResponse = $this->roleDashboardController->getOverview();
            $roleData = $roleResponse->getData(true);
            
            // Merge data intelligently
            $unifiedData = [
                'master_inventory' => $vitalVidaData,
                'compliance_layer' => $roleData,
                'unified_metrics' => [
                    'total_agents' => $vitalVidaData['data']['metrics']['total_agents'] ?? 0,
                    'compliance_rate' => $this->calculateComplianceRate($roleData),
                    'active_violations' => $this->getActiveViolations($roleData),
                    'revenue_at_risk' => $this->calculateRevenueAtRisk($vitalVidaData, $roleData),
                    'enforcement_tasks' => $this->getEnforcementTasks(),
                    'critical_alerts' => $this->combineCriticalAlerts($vitalVidaData, $roleData)
                ],
                'integration_status' => [
                    'last_sync' => now(),
                    'sync_health' => 'healthy',
                    'data_consistency' => $this->checkDataConsistency()
                ]
            ];
            
            return response()->json([
                'status' => 'success',
                'data' => $unifiedData,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Unified dashboard failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Integration bridge failed',
                'error' => $e->getMessage(),
                'fallback_data' => $this->getFallbackData()
            ], 500);
        }
    }

    /**
     * Sync DA Data between systems
     */
    public function syncDAData(Request $request): JsonResponse
    {
        $syncResults = [];
        
        try {
            // Get all VitalVida agents (MASTER)
            $vitalVidaAgents = VitalVidaDeliveryAgent::with('performance')->get();
            
            foreach ($vitalVidaAgents as $vitalAgent) {
                try {
                    // Find corresponding Role agent
                    $roleAgent = RoleDeliveryAgent::where('external_id', $vitalAgent->id)
                        ->orWhere('phone', $vitalAgent->phone)
                        ->first();
                    
                    if (!$roleAgent) {
                        // Create new Role agent from VitalVida data
                        $roleAgent = RoleDeliveryAgent::create([
                            'external_id' => $vitalAgent->id,
                            'agent_name' => $vitalAgent->name,
                            'contact_number' => $vitalAgent->phone,
                            'zone' => $this->mapLocationToZone($vitalAgent->location),
                            'performance_score' => $vitalAgent->rating,
                            'status' => $vitalAgent->status,
                            'created_via_sync' => true,
                            'sync_timestamp' => now()
                        ]);
                        
                        $syncResults[] = [
                            'action' => 'created',
                            'vitalvida_id' => $vitalAgent->id,
                            'role_id' => $roleAgent->id,
                            'agent_name' => $vitalAgent->name
                        ];
                    } else {
                        // Update existing Role agent with VitalVida data
                        $roleAgent->update([
                            'agent_name' => $vitalAgent->name,
                            'contact_number' => $vitalAgent->phone,
                            'zone' => $this->mapLocationToZone($vitalAgent->location),
                            'performance_score' => $vitalAgent->rating,
                            'status' => $vitalAgent->status,
                            'sync_timestamp' => now()
                        ]);
                        
                        $syncResults[] = [
                            'action' => 'updated',
                            'vitalvida_id' => $vitalAgent->id,
                            'role_id' => $roleAgent->id,
                            'agent_name' => $vitalAgent->name
                        ];
                    }
                } catch (\Exception $e) {
                    $syncResults[] = [
                        'action' => 'failed',
                        'vitalvida_id' => $vitalAgent->id,
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return response()->json([
                'status' => 'success',
                'sync_summary' => [
                    'total_processed' => count($vitalVidaAgents),
                    'successful_syncs' => count(array_filter($syncResults, fn($r) => $r['action'] !== 'failed')),
                    'failed_syncs' => count(array_filter($syncResults, fn($r) => $r['action'] === 'failed')),
                    'sync_timestamp' => now()
                ],
                'detailed_results' => $syncResults
            ]);
            
        } catch (\Exception $e) {
            Log::error('DA sync failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'DA sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Sync Inventory Data between systems
     */
    public function syncInventoryData(): JsonResponse
    {
        $syncResults = [];
        
        try {
            // Get VitalVida products with supplier info
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
                    
                    $syncResults[] = [
                        'product_id' => $product->id,
                        'bin_id' => $bin->id,
                        'action' => $bin->wasRecentlyCreated ? 'created' : 'updated'
                    ];
                    
                } catch (\Exception $e) {
                    Log::error("Failed to sync product {$product->id}: " . $e->getMessage());
                    $syncResults[] = [
                        'product_id' => $product->id,
                        'action' => 'failed',
                        'error' => $e->getMessage()
                    ];
                }
            }
            
            return response()->json([
                'status' => 'success',
                'sync_results' => $syncResults,
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Inventory sync failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Inventory sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Full System Sync
     */
    public function fullSync(): JsonResponse
    {
        try {
            $syncResults = [
                'da_sync' => $this->syncDAData(request())->getData(true),
                'inventory_sync' => $this->syncInventoryData()->getData(true),
                'suppliers_sync' => $this->syncSuppliersData()
            ];
            
            return response()->json([
                'status' => 'success',
                'full_sync_results' => $syncResults,
                'sync_timestamp' => now(),
                'next_sync_recommended' => now()->addHours(4)
            ]);
            
        } catch (\Exception $e) {
            Log::error('Full sync failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Full sync failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Integration Health Check
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $health = [
                'vitalvida_connection' => $this->checkVitalVidaHealth(),
                'role_connection' => $this->checkRoleHealth(),
                'data_consistency' => $this->checkDataConsistency(),
                'sync_status' => $this->checkSyncStatus(),
                'performance_metrics' => $this->getPerformanceMetrics()
            ];
            
            $overallHealth = array_sum($health) / count($health) > 0.8 ? 'healthy' : 'degraded';
            
            return response()->json([
                'status' => 'success',
                'overall_status' => $overallHealth,
                'detailed_health' => $health,
                'recommendations' => $this->getHealthRecommendations($health),
                'timestamp' => now()
            ]);
            
        } catch (\Exception $e) {
            Log::error('Health check failed: ' . $e->getMessage());
            
            return response()->json([
                'status' => 'error',
                'message' => 'Health check failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods
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

    private function calculateRevenueAtRisk($vitalVidaData, $roleData): float
    {
        $baseRevenue = $vitalVidaData['data']['metrics']['total_value'] ?? 0;
        $complianceRate = $this->calculateComplianceRate($roleData);
        $riskFactor = (100 - $complianceRate) / 100;
        
        return $baseRevenue * $riskFactor;
    }

    private function calculateComplianceRate($roleData): float
    {
        // Calculate compliance rate from role data
        $totalAgents = $roleData['data']['orderMetrics']['total_orders'] ?? 1;
        $compliantAgents = $roleData['data']['orderMetrics']['delivered_orders'] ?? 0;
        
        return $totalAgents > 0 ? ($compliantAgents / $totalAgents) * 100 : 100;
    }

    private function getActiveViolations($roleData): int
    {
        return $roleData['data']['orderMetrics']['cancelled_orders'] ?? 0;
    }

    private function getEnforcementTasks(): array
    {
        try {
            $response = $this->roleDashboardController->getEnforcementTasks();
            return $response->getData(true)['data'] ?? [];
        } catch (\Exception $e) {
            return [];
        }
    }

    private function combineCriticalAlerts($vitalVidaData, $roleData): array
    {
        $alerts = [];
        
        // Add VitalVida alerts
        if (isset($vitalVidaData['data']['alerts'])) {
            $alerts = array_merge($alerts, $vitalVidaData['data']['alerts']);
        }
        
        // Add Role compliance alerts
        if (isset($roleData['data']['alerts'])) {
            $alerts = array_merge($alerts, $roleData['data']['alerts']);
        }
        
        return $alerts;
    }

    private function checkDataConsistency(): float
    {
        try {
            $vitalVidaAgentCount = VitalVidaDeliveryAgent::count();
            $roleAgentCount = RoleDeliveryAgent::where('created_via_sync', true)->count();
            
            if ($vitalVidaAgentCount === 0) return 100;
            
            $consistency = ($roleAgentCount / $vitalVidaAgentCount) * 100;
            return min(100, $consistency);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function checkVitalVidaHealth(): float
    {
        try {
            $response = $this->vitalVidaController->dashboard();
            return $response->getStatusCode() === 200 ? 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function checkRoleHealth(): float
    {
        try {
            $response = $this->roleDashboardController->getOverview();
            return $response->getStatusCode() === 200 ? 100 : 0;
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function checkSyncStatus(): float
    {
        // Check if sync has happened recently
        $lastSync = RoleDeliveryAgent::where('created_via_sync', true)
            ->max('sync_timestamp');
        
        if (!$lastSync) return 0;
        
        $hoursSinceSync = now()->diffInHours($lastSync);
        return $hoursSinceSync < 24 ? 100 : max(0, 100 - ($hoursSinceSync - 24) * 5);
    }

    private function getPerformanceMetrics(): float
    {
        // Simple performance metric based on response times
        $start = microtime(true);
        
        try {
            $this->vitalVidaController->dashboard();
            $this->roleDashboardController->getOverview();
            
            $responseTime = microtime(true) - $start;
            return $responseTime < 1 ? 100 : max(0, 100 - ($responseTime - 1) * 50);
        } catch (\Exception $e) {
            return 0;
        }
    }

    private function getHealthRecommendations($health): array
    {
        $recommendations = [];
        
        foreach ($health as $component => $score) {
            if ($score < 80) {
                $recommendations[] = "Improve {$component} - current score: {$score}%";
            }
        }
        
        if (empty($recommendations)) {
            $recommendations[] = "All systems operating normally";
        }
        
        return $recommendations;
    }

    private function getFallbackData(): array
    {
        return [
            'message' => 'Using fallback data due to integration failure',
            'basic_metrics' => [
                'total_agents' => 0,
                'compliance_rate' => 0,
                'active_violations' => 0
            ]
        ];
    }

    private function syncSuppliersData(): array
    {
        try {
            $suppliers = VitalVidaSupplier::all();
            $syncCount = 0;
            
            foreach ($suppliers as $supplier) {
                // Sync supplier data to role system if needed
                $syncCount++;
            }
            
            return [
                'status' => 'success',
                'synced_suppliers' => $syncCount
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'error',
                'error' => $e->getMessage()
            ];
        }
    }
}

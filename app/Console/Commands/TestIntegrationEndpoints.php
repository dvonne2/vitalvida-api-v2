<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Http\Controllers\Api\VitalVidaRoleIntegrationController;
use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\VitalVidaSupplier;
use Illuminate\Support\Facades\DB;

class TestIntegrationEndpoints extends Command
{
    protected $signature = 'test:integration-endpoints';
    protected $description = 'Test VitalVida Role Integration endpoints';

    public function handle()
    {
        $this->info('🚀 Testing VitalVida Role Integration Endpoints...');
        
        try {
            // Create test data first
            $this->createTestData();
            
            $controller = new VitalVidaRoleIntegrationController();
            
            // Test 1: Health Check
            $this->info('📊 Testing Health Check...');
            $healthResponse = $controller->healthCheck();
            $healthData = json_decode($healthResponse->getContent(), true);
            $this->info('Health Status: ' . ($healthData['overall_status'] ?? $healthData['status'] ?? 'unknown'));
            
            // Test 2: Unified Dashboard
            $this->info('📈 Testing Unified Dashboard...');
            $dashboardResponse = $controller->unifiedDashboard();
            $dashboardData = json_decode($dashboardResponse->getContent(), true);
            $this->info('Dashboard Status: ' . $dashboardData['status']);
            
            if (isset($dashboardData['data']['unified_metrics'])) {
                $metrics = $dashboardData['data']['unified_metrics'];
                $this->info('Total Agents: ' . ($metrics['total_agents'] ?? 0));
                $this->info('Compliance Rate: ' . ($metrics['compliance_rate'] ?? 0) . '%');
            }
            
            // Test 3: DA Sync
            $this->info('👥 Testing DA Sync...');
            $syncResponse = $controller->syncDAData(request());
            $syncData = json_decode($syncResponse->getContent(), true);
            $this->info('Sync Status: ' . $syncData['status']);
            
            if (isset($syncData['sync_summary'])) {
                $summary = $syncData['sync_summary'];
                $this->info('Processed: ' . $summary['total_processed']);
                $this->info('Successful: ' . $summary['successful_syncs']);
                $this->info('Failed: ' . $summary['failed_syncs']);
            }
            
            // Test 4: Inventory Sync
            $this->info('📦 Testing Inventory Sync...');
            $inventoryResponse = $controller->syncInventoryData();
            $inventoryData = json_decode($inventoryResponse->getContent(), true);
            $this->info('Inventory Sync Status: ' . $inventoryData['status']);
            
            // Test 5: Full Sync
            $this->info('🔄 Testing Full Sync...');
            $fullSyncResponse = $controller->fullSync();
            $fullSyncData = json_decode($fullSyncResponse->getContent(), true);
            $this->info('Full Sync Status: ' . $fullSyncData['status']);
            
            $this->info('✅ All integration endpoints tested successfully!');
            
            // Display integration statistics
            $this->displayIntegrationStats();
            
        } catch (\Exception $e) {
            $this->error('❌ Integration test failed: ' . $e->getMessage());
            $this->error('Trace: ' . $e->getTraceAsString());
        }
    }
    
    private function createTestData()
    {
        $this->info('🔧 Creating test data...');
        
        // Create VitalVida test agents if they don't exist
        if (VitalVidaDeliveryAgent::count() === 0) {
            VitalVidaDeliveryAgent::create([
                'name' => 'Test Agent Lagos',
                'phone' => '+2348012345678',
                'location' => 'Lagos, Nigeria',
                'rating' => 4.5,
                'status' => 'Active',
                'zone' => 'Lagos'
            ]);
            
            VitalVidaDeliveryAgent::create([
                'name' => 'Test Agent Abuja',
                'phone' => '+2348087654321',
                'location' => 'Abuja, Nigeria',
                'rating' => 4.2,
                'status' => 'Active',
                'zone' => 'Abuja'
            ]);
        }
        
        // Create VitalVida test products if they don't exist
        if (VitalVidaProduct::count() === 0) {
            $supplier = VitalVidaSupplier::first();
            
            VitalVidaProduct::create([
                'name' => 'Test Product A',
                'code' => 'TEST-001',
                'stock_level' => 100,
                'min_stock' => 10,
                'max_stock' => 500,
                'unit_price' => 25.50,
                'status' => 'In Stock',
                'supplier_id' => $supplier ? $supplier->id : null
            ]);
            
            VitalVidaProduct::create([
                'name' => 'Test Product B',
                'code' => 'TEST-002',
                'stock_level' => 5,
                'min_stock' => 10,
                'max_stock' => 200,
                'unit_price' => 15.75,
                'status' => 'Low Stock',
                'supplier_id' => $supplier ? $supplier->id : null
            ]);
        }
        
        $this->info('✅ Test data created successfully');
    }
    
    private function displayIntegrationStats()
    {
        $this->info('📊 Integration Statistics:');
        
        try {
            $vitalVidaAgents = VitalVidaDeliveryAgent::count();
            $vitalVidaProducts = VitalVidaProduct::count();
            $suppliers = VitalVidaSupplier::count();
            $bins = DB::table('bins')->count();
            
            $this->table([
                'System', 'Agents', 'Products', 'Suppliers', 'Bins'
            ], [
                ['VitalVida (Master)', $vitalVidaAgents, $vitalVidaProducts, $suppliers, '-'],
                ['Role System', '-', '-', '-', $bins]
            ]);
            
        } catch (\Exception $e) {
            $this->error('Failed to get stats: ' . $e->getMessage());
        }
    }
}

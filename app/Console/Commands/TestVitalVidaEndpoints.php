<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\VitalVidaInventory\Product;
use App\Models\VitalVidaInventory\DeliveryAgent;
use App\Models\VitalVidaInventory\StockTransfer;
use App\Models\VitalVidaSupplier;

class TestVitalVidaEndpoints extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'test:vitalvida-endpoints';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test VitalVida Inventory endpoints and create sample data';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Testing VitalVida Inventory System...');
        
        // Create test user
        $user = User::firstOrCreate(
            ['email' => 'test@vitalvida.com'],
            [
                'name' => 'VitalVida Test User',
                'password' => bcrypt('password123'),
                'email_verified_at' => now()
            ]
        );
        
        // Create test token
        $token = $user->createToken('test-token')->plainTextToken;
        $this->info("Test user created. Token: {$token}");
        
        // Create sample data
        $this->createSampleData();
        
        // Test endpoints
        $this->testEndpoints($token);
        
        $this->info('VitalVida Inventory testing completed!');
    }
    
    private function createSampleData()
    {
        $this->info('Creating sample data...');
        
        // Create suppliers
        $supplier = VitalVidaSupplier::firstOrCreate(
            ['company_name' => 'Test Supplier Ltd'],
            [
                'supplier_code' => 'TEST001',
                'contact_person' => 'John Doe',
                'email' => 'john@testsupplier.com',
                'phone' => '+234-123-456-7890',
                'business_address' => '123 Business District, Lagos, Nigeria',
                'status' => 'Active',
                'rating' => 4.5,
                'total_orders' => 10,
                'total_purchase_value' => 500000,
                'payment_terms' => '30 Days',
                'delivery_time' => '2-3 Days',
                'products_supplied' => ['Test Products']
            ]
        );
        
        // Create products
        $product = Product::firstOrCreate(
            ['name' => 'Test Product A'],
            [
                'code' => 'TPA-001',
                'description' => 'High-quality test product for VitalVida inventory',
                'category' => 'Electronics',
                'unit_price' => 25000.00,
                'cost_price' => 20000.00,
                'stock_level' => 150,
                'min_stock' => 20,
                'supplier_id' => $supplier->id,
                'status' => 'In Stock'
            ]
        );
        
        // Create delivery agents
        $agent = DeliveryAgent::firstOrCreate(
            ['agent_id' => 'DA001'],
            [
                'name' => 'Ahmed Musa',
                'phone' => '+234-801-234-5678',
                'email' => 'ahmed.musa@vitalvida.com',
                'location' => 'Lagos Island',
                'address' => '15 Marina Street, Lagos Island, Lagos State',
                'vehicle_type' => 'motorcycle',
                'status' => 'Available',
                'rating' => 4.5,
                'total_deliveries' => 45,
                'completed_deliveries' => 42
            ]
        );
        
        // Create stock transfer
        StockTransfer::firstOrCreate(
            ['transfer_id' => 'ST-001'],
            [
                'product_id' => $product->id,
                'to_agent_id' => $agent->id,
                'quantity' => 50,
                'unit_price' => 25000.00,
                'total_value' => 1250000.00,
                'status' => 'Pending',
                'reason' => 'Monthly stock redistribution'
            ]
        );
        
        $this->info('Sample data created successfully!');
    }
    
    private function testEndpoints($token)
    {
        $this->info('Testing API endpoints...');
        
        $baseUrl = 'http://localhost:8000/api/vitalvida-inventory';
        $headers = [
            'Accept: application/json',
            'Authorization: Bearer ' . $token
        ];
        
        $endpoints = [
            'Dashboard' => '/dashboard',
            'Items' => '/items',
            'Items Summary' => '/items/summary',
            'Inventory Overview' => '/inventory/overview',
            'Analytics Overview' => '/analytics/overview',
            'Delivery Agents' => '/delivery-agents',
            'Stock Transfers' => '/stock-transfers',
            'Abdul Audit Metrics' => '/abdul/audit-metrics',
            'Abdul Flags' => '/abdul/flags',
            'Abdul Agent Scorecard' => '/abdul/agent-scorecard',
            'Company Settings' => '/settings/company',
            'Security Settings' => '/settings/security',
            'System Settings' => '/settings/system'
        ];
        
        foreach ($endpoints as $name => $endpoint) {
            $url = $baseUrl . $endpoint;
            $this->info("Testing {$name}: {$endpoint}");
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($httpCode === 200) {
                $data = json_decode($response, true);
                if (isset($data['status']) && $data['status'] === 'success') {
                    $this->info("✅ {$name}: SUCCESS");
                } else {
                    $this->warn("⚠️ {$name}: Response format issue");
                }
            } else {
                $this->error("❌ {$name}: HTTP {$httpCode}");
            }
        }
    }
}

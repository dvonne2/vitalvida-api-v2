<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use App\Models\StockMovement;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class SimulateInventoryFlowSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🚀 Starting Complete Inventory Flow Simulation...');
        
        DB::beginTransaction();
        
        try {
            // Step 1: Create Product
            $product = Product::firstOrCreate(
                ['sku' => 'FHG-SHAMPOO-001'],
                [
                    'name' => 'Fulani Hair Gro Shampoo',
                    'description' => 'Premium hair growth shampoo for healthy scalp',
                    'category' => 'Hair Care',
                    'unit_price' => 2500.00,
                    'cost_price' => 1800.00,
                    'available_quantity' => 200,
                    'minimum_stock_level' => 50,
                    'maximum_stock_level' => 1000,
                    'status' => 'active',
                ]
            );
            
            $this->command->info("✅ Created Product: {$product->name} (SKU: {$product->sku})");
            
            // Step 2: Create Warehouse
            $warehouse = Warehouse::firstOrCreate(
                ['code' => 'OYG-WH-001'],
                [
                    'name' => 'Oyingbo Main Warehouse',
                    'address' => 'Oyingbo Market, Lagos Island, Lagos State',
                    'manager' => 'Warehouse Manager - Kemi',
                    'phone' => '+234-802-WAREHOUSE',
                    'capacity' => 10000,
                    'status' => 'active',
                ]
            );
            
            $this->command->info("✅ Created Warehouse: {$warehouse->name}");
            
            // Step 3: Create Delivery Agent (use 'DA' role for now)
            $deliveryAgent = User::firstOrCreate(
                ['email' => 'john.lagos@vitalvida.com'],
                [
                    'name' => 'John - Lagos',
                    'role' => 'DA',
                    'phone' => '+234-803-DELIVER',
                    'password' => bcrypt('password123'),
                ]
            );
            
            $this->command->info("✅ Created Delivery Agent: {$deliveryAgent->name}");
            
            // Step 4: Create Stock Movement
            StockMovement::create([
                'product_id' => $product->id,
                'movement_type' => 'inbound',
                'source_type' => 'factory',
                'source_id' => null,
                'destination_type' => 'warehouse',
                'destination_id' => $warehouse->id,
                'quantity' => 200,
                'reference_type' => 'purchase_order',
                'reference_id' => 1,
                'performed_by' => 'System Seeder',
                'notes' => 'Initial stock from factory - Fulani Hair Gro Shampoo',
            ]);
            
            $this->command->info("✅ Created stock movement: 200 units to warehouse");
            
            DB::commit();
            
            $this->command->info("\n" . str_repeat('=', 80));
            $this->command->info("🎯 INVENTORY FLOW SIMULATION COMPLETE!");
            $this->command->info(str_repeat('=', 80));
            $this->command->info("📊 Summary:");
            $this->command->info("   • Product: {$product->name}");
            $this->command->info("   • SKU: {$product->sku}");
            $this->command->info("   • Available Stock: {$product->available_quantity} units");
            $this->command->info("   • Unit Price: ₦" . number_format($product->unit_price, 2));
            $this->command->info("   • Warehouse: {$warehouse->name}");
            $this->command->info("   • DA: {$deliveryAgent->name}");
            $this->command->info("   • Stock Movement: Logged ✅");
            $this->command->info(str_repeat('=', 80));
            $this->command->info("✅ Ready for API testing!");
            
        } catch (\Exception $e) {
            DB::rollBack();
            $this->command->error("❌ Error: " . $e->getMessage());
            throw $e;
        }
    }
}

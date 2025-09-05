<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class WorkingTestSeeder extends Seeder
{
    public function run(): void
    {
        // Warehouses - using actual column names
        DB::table('warehouses')->insert([
            'name' => 'Lagos Main Warehouse',
            'code' => 'LG01',
            'address' => 'Lagos, Nigeria',
            'manager' => 'John Doe',
            'phone' => '+234-800-000-0001',
            'capacity' => 1000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('warehouses')->insert([
            'name' => 'Abuja Distribution Center',
            'code' => 'AB01', 
            'address' => 'Abuja, Nigeria',
            'manager' => 'Jane Smith',
            'phone' => '+234-800-000-0002',
            'capacity' => 800,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Products - using actual column names
        DB::table('products')->insert([
            'name' => 'Test Product A',
            'sku' => 'TEST-001',
            'description' => 'A test product for inventory management',
            'category' => 'test',
            'unit_price' => 100.00,
            'cost_price' => 80.00,
            'available_quantity' => 0,
            'minimum_stock_level' => 10,
            'maximum_stock_level' => 1000,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        DB::table('products')->insert([
            'name' => 'Test Product B',
            'sku' => 'TEST-002',
            'description' => 'Another test product',
            'category' => 'test',
            'unit_price' => 250.50,
            'cost_price' => 200.00,
            'available_quantity' => 0,
            'minimum_stock_level' => 5,
            'maximum_stock_level' => 500,
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        // Delivery agent user
        DB::table('users')->insert([
            'name' => 'Delivery Agent',
            'email' => 'agent@vitalvida.com',
            'phone' => '1234567890',
            'password' => Hash::make('password123'),
            'role' => 'delivery_agent',
            'kyc_status' => 'approved',
            'is_active' => 1,
            'created_at' => now(),
            'updated_at' => now()
        ]);

        echo "✅ Working test data inserted successfully!\n";
    }
}

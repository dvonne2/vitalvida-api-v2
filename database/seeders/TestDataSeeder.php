<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Product;
use App\Models\Warehouse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class TestDataSeeder extends Seeder
{
    public function run(): void
    {
        // Create warehouses
        Warehouse::updateOrCreate(
            ['code' => 'LG01'],
            [
                'name' => 'Lagos Main Warehouse',
                'address' => 'Lagos, Nigeria',
                'contact_person' => 'John Doe',
                'phone' => '+234-800-000-0001',
                'is_active' => true
            ]
        );

        Warehouse::updateOrCreate(
            ['code' => 'AB01'],
            [
                'name' => 'Abuja Distribution Center',
                'address' => 'Abuja, Nigeria',
                'contact_person' => 'Jane Smith',
                'phone' => '+234-800-000-0002',
                'is_active' => true
            ]
        );

        // Create products
        Product::updateOrCreate(
            ['sku' => 'TEST-001'],
            [
                'name' => 'Test Product A',
                'description' => 'A test product',
                'price' => 100.00,
                'stock_quantity' => 0,
                'unit' => 'pieces',
                'is_active' => true
            ]
        );

        Product::updateOrCreate(
            ['sku' => 'TEST-002'],
            [
                'name' => 'Test Product B',
                'description' => 'Another test product',
                'price' => 250.50,
                'stock_quantity' => 0,
                'unit' => 'kg',
                'is_active' => true
            ]
        );

        // Create delivery agent
        User::updateOrCreate(
            ['email' => 'agent@vitalvida.com'],
            [
                'name' => 'Delivery Agent',
                'phone' => '1234567890',
                'password' => Hash::make('password123'),
                'role' => 'delivery_agent',
                'kyc_status' => 'approved',
                'is_active' => 1
            ]
        );

        $this->command->info('✅ Test data seeded successfully!');
    }
}

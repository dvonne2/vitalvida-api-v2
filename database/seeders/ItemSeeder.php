<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Item;
use App\Models\Category;
use App\Models\Supplier;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get categories and suppliers
        $vitaminsCategory = Category::where('name', 'Vitamins')->first();
        $supplementsCategory = Category::where('name', 'Supplements')->first();
        $herbalCategory = Category::where('name', 'Herbal Supplements')->first();
        $hairCareCategory = Category::where('name', 'Hair Care')->first();
        
        $supplier1 = Supplier::where('name', 'VitalVida Health Solutions')->first();
        $supplier2 = Supplier::where('name', 'Natural Health Products Ltd')->first();
        $supplier3 = Supplier::where('name', 'Premium Vitamins Co.')->first();

        $items = [
            // Vitamins
            [
                'name' => 'Vitamin C 1000mg',
                'category_id' => $vitaminsCategory->id,
                'supplier_id' => $supplier1->id,
                'sku' => 'VIT-C-1000',
                'description' => 'High potency Vitamin C supplement for immune support',
                'unit_price' => 2500.00,
                'cost_price' => 1500.00,
                'selling_price' => 3000.00,
                'stock_quantity' => 150,
                'reorder_level' => 20,
                'max_stock' => 200,
                'min_stock' => 5,
                'unit_of_measure' => 'tablets',
                'brand' => 'VitalVida',
                'barcode' => '1234567890123',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'A1-01',
                'expiry_date' => now()->addMonths(18),
                'tax_rate' => 7.5,
                'margin_percentage' => 40.0
            ],
            [
                'name' => 'Vitamin D3 2000IU',
                'category_id' => $vitaminsCategory->id,
                'supplier_id' => $supplier3->id,
                'sku' => 'VIT-D3-2000',
                'description' => 'Vitamin D3 supplement for bone health and immunity',
                'unit_price' => 3000.00,
                'cost_price' => 1800.00,
                'selling_price' => 3500.00,
                'stock_quantity' => 100,
                'reorder_level' => 15,
                'max_stock' => 150,
                'min_stock' => 5,
                'unit_of_measure' => 'capsules',
                'brand' => 'PremiumVit',
                'barcode' => '1234567890124',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'A1-02',
                'expiry_date' => now()->addMonths(24),
                'tax_rate' => 7.5,
                'margin_percentage' => 35.0
            ],
            [
                'name' => 'B-Complex Vitamins',
                'category_id' => $vitaminsCategory->id,
                'supplier_id' => $supplier1->id,
                'sku' => 'VIT-B-COMPLEX',
                'description' => 'Complete B-vitamin complex for energy and metabolism',
                'unit_price' => 4000.00,
                'cost_price' => 2400.00,
                'selling_price' => 4500.00,
                'stock_quantity' => 80,
                'reorder_level' => 10,
                'max_stock' => 120,
                'min_stock' => 5,
                'unit_of_measure' => 'tablets',
                'brand' => 'VitalVida',
                'barcode' => '1234567890125',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'A1-03',
                'expiry_date' => now()->addMonths(20),
                'tax_rate' => 7.5,
                'margin_percentage' => 37.5
            ],
            // Supplements
            [
                'name' => 'Omega-3 Fish Oil',
                'category_id' => $supplementsCategory->id,
                'supplier_id' => $supplier3->id,
                'sku' => 'SUP-OMEGA3',
                'description' => 'High quality fish oil supplement for heart health',
                'unit_price' => 5000.00,
                'cost_price' => 3000.00,
                'selling_price' => 5500.00,
                'stock_quantity' => 60,
                'reorder_level' => 8,
                'max_stock' => 100,
                'min_stock' => 3,
                'unit_of_measure' => 'capsules',
                'brand' => 'PremiumVit',
                'barcode' => '1234567890126',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'B1-01',
                'expiry_date' => now()->addMonths(15),
                'tax_rate' => 7.5,
                'margin_percentage' => 33.3
            ],
            [
                'name' => 'Calcium + Magnesium',
                'category_id' => $supplementsCategory->id,
                'supplier_id' => $supplier1->id,
                'sku' => 'SUP-CAL-MAG',
                'description' => 'Calcium and magnesium supplement for bone health',
                'unit_price' => 3500.00,
                'cost_price' => 2100.00,
                'selling_price' => 4000.00,
                'stock_quantity' => 120,
                'reorder_level' => 15,
                'max_stock' => 180,
                'min_stock' => 5,
                'unit_of_measure' => 'tablets',
                'brand' => 'VitalVida',
                'barcode' => '1234567890127',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'B1-02',
                'expiry_date' => now()->addMonths(22),
                'tax_rate' => 7.5,
                'margin_percentage' => 35.0
            ],
            // Herbal Supplements
            [
                'name' => 'Ginger Root Extract',
                'category_id' => $herbalCategory->id,
                'supplier_id' => $supplier2->id,
                'sku' => 'HERB-GINGER',
                'description' => 'Natural ginger root extract for digestive health',
                'unit_price' => 2000.00,
                'cost_price' => 1200.00,
                'selling_price' => 2500.00,
                'stock_quantity' => 200,
                'reorder_level' => 25,
                'max_stock' => 300,
                'min_stock' => 10,
                'unit_of_measure' => 'capsules',
                'brand' => 'NaturalHealth',
                'barcode' => '1234567890128',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'C1-01',
                'expiry_date' => now()->addMonths(16),
                'tax_rate' => 7.5,
                'margin_percentage' => 40.0
            ],
            [
                'name' => 'Turmeric Curcumin',
                'category_id' => $herbalCategory->id,
                'supplier_id' => $supplier2->id,
                'sku' => 'HERB-TURMERIC',
                'description' => 'Turmeric curcumin supplement for inflammation',
                'unit_price' => 4500.00,
                'cost_price' => 2700.00,
                'selling_price' => 5000.00,
                'stock_quantity' => 75,
                'reorder_level' => 10,
                'max_stock' => 120,
                'min_stock' => 5,
                'unit_of_measure' => 'capsules',
                'brand' => 'NaturalHealth',
                'barcode' => '1234567890129',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'C1-02',
                'expiry_date' => now()->addMonths(18),
                'tax_rate' => 7.5,
                'margin_percentage' => 35.0
            ],
            // Hair Care
            [
                'name' => 'Biotin Hair Growth',
                'category_id' => $hairCareCategory->id,
                'supplier_id' => $supplier1->id,
                'sku' => 'HAIR-BIOTIN',
                'description' => 'Biotin supplement for hair growth and strength',
                'unit_price' => 6000.00,
                'cost_price' => 3600.00,
                'selling_price' => 6500.00,
                'stock_quantity' => 50,
                'reorder_level' => 8,
                'max_stock' => 80,
                'min_stock' => 3,
                'unit_of_measure' => 'tablets',
                'brand' => 'VitalVida',
                'barcode' => '1234567890130',
                'is_active' => true,
                'is_tracked' => true,
                'location' => 'Main Warehouse',
                'shelf_location' => 'D1-01',
                'expiry_date' => now()->addMonths(20),
                'tax_rate' => 7.5,
                'margin_percentage' => 33.3
            ]
        ];

        foreach ($items as $itemData) {
            Item::create($itemData);
        }

        $this->command->info('Items seeded successfully!');
    }
} 
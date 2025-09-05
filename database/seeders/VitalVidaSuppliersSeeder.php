<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\VitalVidaSupplier;
use App\Models\VitalVidaSupplierPerformance;

class VitalVidaSuppliersSeeder extends Seeder
{
    /**
     * Run the database seeder.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'supplier_code' => 'SUP001',
                'company_name' => 'Lagos Pharma Distributors',
                'contact_person' => 'Mr. Adebayo Fashola',
                'phone' => '+234 801 345 0001',
                'email' => 'orders@lagospharma.ng',
                'business_address' => '18 Oshodi Way, Ikeja, Lagos',
                'website' => 'https://lagospharma.ng',
                'products_supplied' => ['Medications', 'Vitamins', 'Antibiotics'],
                'rating' => 4.9,
                'total_orders' => 156,
                'total_purchase_value' => 4600000,
                'payment_terms' => '30 Days',
                'delivery_time' => '1-2 Days',
                'status' => 'Active'
            ],
            [
                'supplier_code' => 'SUP002',
                'company_name' => 'West Africa Drug Company',
                'contact_person' => 'Mrs. Fatima Abdullahi',
                'phone' => '+234 802 345 0002',
                'email' => 'supply@wadc.ng',
                'business_address' => 'Plot 45, Admiralty Way, Lekki, Lagos',
                'website' => 'https://wadc.ng',
                'products_supplied' => ['Medical Devices', 'Surgical Supplies'],
                'rating' => 4.8,
                'total_orders' => 98,
                'total_purchase_value' => 2800000,
                'payment_terms' => '45 Days',
                'delivery_time' => '2-3 Days',
                'status' => 'Active'
            ],
            [
                'supplier_code' => 'SUP003',
                'company_name' => 'Emzor Pharmaceutical Industries',
                'contact_person' => 'Dr. Chinedu Okoro',
                'phone' => '+234 803 345 0003',
                'email' => 'procurement@emzor.ng',
                'business_address' => '7 Stella Ogunlami Way, Ikeja, Lagos',
                'website' => 'https://emzor.ng',
                'products_supplied' => ['OTC Medications', 'Surgical Supplies'],
                'rating' => 4.7,
                'total_orders' => 89,
                'total_purchase_value' => 1890000,
                'payment_terms' => '60 Days',
                'delivery_time' => '3-5 Days',
                'status' => 'Active'
            ],
            [
                'supplier_code' => 'SUP004',
                'company_name' => 'Juhel Pharmaceuticals',
                'contact_person' => 'Mr. Kemi Johnson',
                'phone' => '+234 804 345 0004',
                'email' => 'orders@juhel.ng',
                'business_address' => '46 Allen Avenue, Ikeja, Lagos',
                'website' => 'https://juhel.ng',
                'products_supplied' => ['Laboratory Equipment', 'Diagnostic Kits', 'Safety Equipment'],
                'rating' => 4.5,
                'total_orders' => 67,
                'total_purchase_value' => 1980000,
                'payment_terms' => '15 Days',
                'delivery_time' => '5-7 Days',
                'status' => 'Inactive'
            ]
        ];

        foreach ($suppliers as $supplier) {
            $createdSupplier = VitalVidaSupplier::create($supplier);
            
            // Create sample performance data
            VitalVidaSupplierPerformance::create([
                'supplier_id' => $createdSupplier->id,
                'performance_date' => now()->subDays(30),
                'delivery_rating' => $supplier['rating'],
                'quality_rating' => $supplier['rating'] - 0.1,
                'service_rating' => $supplier['rating'] + 0.1,
                'orders_completed' => rand(10, 25),
                'orders_delayed' => rand(0, 3),
                'order_value' => $supplier['total_purchase_value'] / 10,
                'notes' => 'Monthly performance review'
            ]);
        }
    }
}

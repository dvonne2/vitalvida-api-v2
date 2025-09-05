<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PayoutTestDataSeeder extends Seeder
{
    public function run()
    {
        // Clear existing test data
        DB::table('payouts')->where('order_id', '<=', 3)->delete();
        DB::table('orders')->where('id', '<=', 3)->delete();

        // Create test orders with all required fields
        $testOrders = [
            [
                'id' => 1,
                'order_number' => 'TEST-001',
                'customer_name' => 'Test Customer 1',
                'customer_phone' => '+2348012345671',
                'customer_email' => 'test1@example.com',
                'delivery_address' => '123 Test Street, Lagos',
                'items' => json_encode([['name' => 'Test Product', 'qty' => 1]]),
                'total_amount' => 25000,
                'assigned_da_id' => 1,
                'delivery_otp' => '1234',
                'otp_verified' => false,
                'payment_reference' => null, // No payment
                'status' => 'pending',
                'payment_status' => 'pending',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 2,
                'order_number' => 'TEST-002',
                'customer_name' => 'Test Customer 2',
                'customer_phone' => '+2348012345672',
                'customer_email' => 'test2@example.com',
                'delivery_address' => '456 Test Avenue, Lagos',
                'items' => json_encode([['name' => 'Test Product 2', 'qty' => 2]]),
                'total_amount' => 35000,
                'assigned_da_id' => 2,
                'delivery_otp' => '5678',
                'otp_verified' => true,
                'payment_reference' => 'MP987654321',
                'status' => 'delivered',
                'payment_status' => 'paid',
                'created_at' => now(),
                'updated_at' => now()
            ],
            [
                'id' => 3,
                'order_number' => 'TEST-003',
                'customer_name' => 'Test Customer 3',
                'customer_phone' => '+2348012345673',
                'customer_email' => 'test3@example.com',
                'delivery_address' => '789 Test Road, Lagos',
                'items' => json_encode([['name' => 'Test Product 3', 'qty' => 1]]),
                'total_amount' => 15000,
                'assigned_da_id' => 1,
                'delivery_otp' => '9999',
                'otp_verified' => false,
                'payment_reference' => 'MP111222333',
                'status' => 'cancelled',
                'payment_status' => 'refunded',
                'created_at' => now()->subDays(3),
                'updated_at' => now()
            ]
        ];

        DB::table('orders')->insert($testOrders);

        // Create test payouts
        $testPayouts = [
            [
                'order_id' => 1,
                'amount' => 25000,
                'status' => 'intent_marked',
                'created_at' => now()->subHours(72), // Stale
                'updated_at' => now()->subHours(72)
            ],
            [
                'order_id' => 2,
                'amount' => 35000,
                'status' => 'receipt_confirmed',
                'created_at' => now()->subHours(12),
                'updated_at' => now()->subHours(12)
            ],
            [
                'order_id' => 3,
                'amount' => 15000,
                'status' => 'auto_reverted',
                'created_at' => now()->subDays(3),
                'updated_at' => now()->subHours(1)
            ]
        ];

        DB::table('payouts')->insert($testPayouts);

        // Add system logs for Order 2 (the eligible one)
        \App\Helpers\SystemLogger::logAction('payment_verified', 1, '127.0.0.1', [
            'order_id' => 2,
            'amount' => 35000,
            'payment_reference' => 'MP987654321'
        ]);

        \App\Helpers\SystemLogger::logAction('photo_approved', 1, '127.0.0.1', [
            'da_id' => 2,
            'approved_by' => 'IM_TestUser',
            'week_date' => now()->startOfWeek()->toDateString()
        ]);

        $this->command->info('Test data seeded: 3 orders with different payout states');
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Order;
use App\Models\User;
use App\Models\Customer;

class OrderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $sources = ['meta_ad', 'instagram', 'whatsapp', 'repeat_buyer', 'manual', 'referral', 'organic'];
        $states = ['Lagos', 'Kano', 'Abuja', 'Port Harcourt', 'Benin'];
        $statuses = ['pending', 'confirmed', 'processing', 'ready_for_delivery', 'assigned', 'in_transit', 'delivered'];
        $paymentStatuses = ['pending', 'confirmed', 'failed'];

        // Get some users for assignment
        $telesalesUsers = User::whereIn('role', ['telesales_rep'])->take(5)->get();
        $deliveryUsers = User::whereIn('role', ['delivery_agent'])->take(5)->get();

        // If no specific role users found, use any available users
        if ($telesalesUsers->isEmpty()) {
            $telesalesUsers = User::take(3)->get();
        }
        if ($deliveryUsers->isEmpty()) {
            $deliveryUsers = User::take(3)->get();
        }

        for ($i = 1; $i <= 50; $i++) {
            $order = Order::create([
                'order_number' => 'ORD' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'zoho_order_id' => 'ZOHO' . str_pad($i, 6, '0', STR_PAD_LEFT),
                'customer_id' => Customer::inRandomOrder()->first()->id ?? 1,
                'customer_name' => fake()->name(),
                'customer_phone' => fake()->phoneNumber(),
                'customer_email' => fake()->email(),
                'source' => $sources[array_rand($sources)],
                'delivery_address' => fake()->address(),
                'items' => [
                    [
                        'product' => 'Vitalvida Shampoo',
                        'quantity' => rand(1, 3),
                        'price' => 2500
                    ],
                    [
                        'product' => 'Vitalvida Pomade',
                        'quantity' => rand(1, 2),
                        'price' => 3000
                    ]
                ],
                'total_amount' => rand(5000, 15000),
                'status' => $statuses[array_rand($statuses)],
                'payment_status' => $paymentStatuses[array_rand($paymentStatuses)],
                'payment_reference' => 'REF' . str_pad($i, 8, '0', STR_PAD_LEFT),
                'assigned_telesales_id' => $telesalesUsers->random()->id ?? null,
                'assigned_da_id' => $deliveryUsers->random()->id ?? null,
                'state' => $states[array_rand($states)],
                'assigned_at' => fake()->dateTimeBetween('-30 days', 'now'),
                'delivery_date' => fake()->dateTimeBetween('now', '+7 days'),
                'delivery_otp' => str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'otp_code' => str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT),
                'otp_verified' => rand(0, 1),
                'otp_verified_at' => fake()->dateTimeBetween('-7 days', 'now'),
                'delivery_photo_path' => rand(0, 1) ? 'photos/delivery_' . $i . '.jpg' : null,
                'delivery_notes' => rand(0, 1) ? fake()->sentence() : null,
                'delivered_at' => rand(0, 1) ? fake()->dateTimeBetween('-7 days', 'now') : null,
                'fraud_flags' => rand(0, 1) ? ['payment_mismatch' => ['amount' => 5000]] : null,
                'is_ghosted' => rand(0, 10) === 0, // 10% chance of being ghosted
                'ghosted_at' => null,
                'ghost_reason' => null,
                'created_at' => fake()->dateTimeBetween('-60 days', 'now'),
                'updated_at' => fake()->dateTimeBetween('-30 days', 'now'),
            ]);

            // Mark some orders as ghosted
            if ($order->is_ghosted) {
                $order->update([
                    'ghosted_at' => fake()->dateTimeBetween($order->created_at, 'now'),
                    'ghost_reason' => fake()->sentence()
                ]);
            }
        }

        $this->command->info('Orders seeded successfully!');
    }
}

<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DeliveryAgent;
use App\Models\Delivery;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class QuickTestSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Creating quick test data...');

        // Create admin user
        $admin = User::firstOrCreate(
            ['email' => 'admin@test.com'],
            [
                'name' => 'Test Admin',
                'phone' => '08012345678',
                'password' => Hash::make('password'),
                'role' => 'admin',
                'kyc_status' => 'approved',
                'is_active' => true,
            ]
        );

        // Create delivery agent users and agents
        for ($i = 1; $i <= 5; $i++) {
            $user = User::firstOrCreate(
                ['email' => "agent{$i}@test.com"],
                [
                    'name' => "Test Agent {$i}",
                    'phone' => '0801234567' . $i,
                    'password' => Hash::make('password'),
                    'role' => 'delivery_agent',
                    'kyc_status' => 'approved',
                    'is_active' => true,
                    'state' => 'Lagos',
                    'city' => 'Ikeja',
                ]
            );

            $agent = DeliveryAgent::firstOrCreate(
                ['user_id' => $user->id],
                [
                    'da_code' => "DA00{$i}",
                    'vehicle_number' => "ABC-123-{$i}",
                    'vehicle_type' => 'motorcycle',
                    'status' => 'active',
                    'current_location' => 'Lagos, Nigeria',
                    'state' => 'Lagos',
                    'city' => 'Ikeja',
                    'total_deliveries' => rand(20, 100),
                    'successful_deliveries' => rand(15, 95),
                    'rating' => rand(350, 500) / 100,
                    'total_earnings' => rand(10000, 50000),
                    'vehicle_status' => 'available',
                ]
            );

            // Create some deliveries for this agent
            for ($j = 1; $j <= rand(3, 8); $j++) {
                Delivery::firstOrCreate(
                    ['delivery_code' => "DEL-{$i}-{$j}"],
                    [
                        'order_id' => rand(1000, 9999),
                        'delivery_agent_id' => $agent->id,
                        'assigned_by' => $admin->id,
                        'status' => collect(['assigned', 'delivered', 'delivered', 'delivered'])->random(),
                        'pickup_location' => 'Warehouse Lagos',
                        'delivery_location' => 'Customer Location ' . $j,
                        'recipient_name' => "Customer {$j}",
                        'recipient_phone' => '080123456' . str_pad($j, 2, '0', STR_PAD_LEFT),
                        'assigned_at' => now()->subDays(rand(0, 7)),
                        'delivered_at' => rand(0, 1) ? now()->subDays(rand(0, 5)) : null,
                        'customer_rating' => rand(0, 1) ? rand(3, 5) : null,
                    ]
                );
            }
        }

        $this->command->info('✅ Quick test data created!');
        $this->command->info('   - 1 admin user: admin@test.com / password');
        $this->command->info('   - 5 delivery agents: agent1@test.com to agent5@test.com / password');
        $this->command->info('   - Multiple deliveries for testing');
    }
}

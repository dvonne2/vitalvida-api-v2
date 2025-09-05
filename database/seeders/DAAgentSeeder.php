<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\DeliveryAgent;
use Carbon\Carbon;
use Illuminate\Support\Facades\Hash;

class DAAgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the DA user
        $daUser = User::where('email', 'da@vitalvida.com')->first();
        
        if ($daUser) {
            // Create DeliveryAgent record for DA user
            DeliveryAgent::firstOrCreate(
                ['user_id' => $daUser->id],
                [
                    'da_code' => 'DA001',
                    'vehicle_number' => 'LAG-123-ABC',
                    'vehicle_type' => 'Motorcycle',
                    'status' => 'active',
                    'current_location' => 'Lagos Central Zone',
                    'total_deliveries' => 0,
                    'successful_deliveries' => 0,
                    'rating' => 5.0,
                    'total_earnings' => 0,
                    'working_hours' => 0,
                    'service_areas' => json_encode(['Lagos Central Zone']),
                    'state' => 'Lagos',
                    'city' => 'Lagos',
                    'commission_rate' => 15.0,
                    'strikes_count' => 0,
                    'last_active_at' => Carbon::now(),
                    'delivery_zones' => json_encode(['Zone A', 'Zone B']),
                    'vehicle_status' => 'available',
                    'current_capacity_used' => 0,
                    'max_capacity' => 10,
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );

            $this->command->info('Delivery Agent created for DA user: ' . $daUser->email);
        } else {
            $this->command->error('DA user not found');
        }

        // Create test users for additional delivery agents
        $testUsers = [
            [
                'name' => 'Sarah Mohammed',
                'email' => 'sarah@vitalvida.com',
                'role' => 'DA'
            ],
            [
                'name' => 'Omar Al-Rashid',
                'email' => 'omar@vitalvida.com',
                'role' => 'DA'
            ],
            [
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed@vitalvida.com',
                'role' => 'DA'
            ],
            [
                'name' => 'Fatima Al-Zahra',
                'email' => 'fatima@vitalvida.com',
                'role' => 'DA'
            ]
        ];

        $testAgents = [
            [
                'da_code' => 'DA002',
                'vehicle_number' => 'LAG-456-DEF',
                'vehicle_type' => 'Motorcycle',
                'status' => 'active',
                'current_location' => 'Lagos Zone A',
                'total_deliveries' => 25,
                'successful_deliveries' => 23,
                'rating' => 4.8,
                'total_earnings' => 45000,
                'working_hours' => 160,
                'service_areas' => json_encode(['Lagos Zone A']),
                'state' => 'Lagos',
                'city' => 'Lagos',
                'commission_rate' => 15.0,
                'strikes_count' => 0,
                'last_active_at' => Carbon::now(),
                'delivery_zones' => json_encode(['Zone A']),
                'vehicle_status' => 'available',
                'current_capacity_used' => 2,
                'max_capacity' => 10
            ],
            [
                'da_code' => 'DA003',
                'vehicle_number' => 'LAG-789-GHI',
                'vehicle_type' => 'Motorcycle',
                'status' => 'active',
                'current_location' => 'Lagos Zone B',
                'total_deliveries' => 30,
                'successful_deliveries' => 28,
                'rating' => 4.9,
                'total_earnings' => 52000,
                'working_hours' => 180,
                'service_areas' => json_encode(['Lagos Zone B']),
                'state' => 'Lagos',
                'city' => 'Lagos',
                'commission_rate' => 15.0,
                'strikes_count' => 0,
                'last_active_at' => Carbon::now(),
                'delivery_zones' => json_encode(['Zone B']),
                'vehicle_status' => 'available',
                'current_capacity_used' => 1,
                'max_capacity' => 10
            ],
            [
                'da_code' => 'DA004',
                'vehicle_number' => 'LAG-012-JKL',
                'vehicle_type' => 'Motorcycle',
                'status' => 'active',
                'current_location' => 'Lagos Zone C',
                'total_deliveries' => 20,
                'successful_deliveries' => 18,
                'rating' => 4.7,
                'total_earnings' => 35000,
                'working_hours' => 140,
                'service_areas' => json_encode(['Lagos Zone C']),
                'state' => 'Lagos',
                'city' => 'Lagos',
                'commission_rate' => 15.0,
                'strikes_count' => 1,
                'last_active_at' => Carbon::now(),
                'delivery_zones' => json_encode(['Zone C']),
                'vehicle_status' => 'available',
                'current_capacity_used' => 0,
                'max_capacity' => 10
            ],
            [
                'da_code' => 'DA005',
                'vehicle_number' => 'LAG-345-MNO',
                'vehicle_type' => 'Motorcycle',
                'status' => 'active',
                'current_location' => 'Lagos Zone D',
                'total_deliveries' => 35,
                'successful_deliveries' => 33,
                'rating' => 4.9,
                'total_earnings' => 58000,
                'working_hours' => 200,
                'service_areas' => json_encode(['Lagos Zone D']),
                'state' => 'Lagos',
                'city' => 'Lagos',
                'commission_rate' => 15.0,
                'strikes_count' => 0,
                'last_active_at' => Carbon::now(),
                'delivery_zones' => json_encode(['Zone D']),
                'vehicle_status' => 'available',
                'current_capacity_used' => 3,
                'max_capacity' => 10
            ]
        ];

        foreach ($testUsers as $index => $userData) {
            $user = User::firstOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'email' => $userData['email'],
                    'password' => Hash::make('password123'),
                    'role' => $userData['role'],
                    'is_active' => true,
                    'email_verified_at' => Carbon::now(),
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );

            // Create delivery agent for this user
            DeliveryAgent::firstOrCreate(
                ['user_id' => $user->id],
                array_merge($testAgents[$index], [
                    'created_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ])
            );
        }

        $this->command->info('Test delivery agents created successfully');
    }
}

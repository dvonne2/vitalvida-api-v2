<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\DeliveryAgent;
use Illuminate\Support\Facades\Hash;

class AgentPerformanceSeeder extends Seeder
{
    public function run(): void
    {
        echo "Creating delivery agents...\n";
        
        // Check if we already have delivery agents
        $existingAgents = DeliveryAgent::count();
        if ($existingAgents > 0) {
            echo "Found {$existingAgents} existing delivery agents. Skipping creation.\n";
            return;
        }
        
        // Create delivery agent users
        $agents = [
            [
                'name' => 'Sarah Mohammed',
                'email' => 'sarah.da@vitalvida.com',
                'da_code' => 'DA-001',
                'total_deliveries' => 30,
                'successful_deliveries' => 30,
            ],
            [
                'name' => 'Omar Al-Rashid', 
                'email' => 'omar.da@vitalvida.com',
                'da_code' => 'DA-010',
                'total_deliveries' => 28,
                'successful_deliveries' => 27,
            ],
            [
                'name' => 'Ahmed Hassan',
                'email' => 'ahmed.da@vitalvida.com',
                'da_code' => 'DA-004',
                'total_deliveries' => 35,
                'successful_deliveries' => 33,
            ],
        ];

        foreach ($agents as $agentData) {
            // Check if user already exists
            $existingUser = User::where('email', $agentData['email'])->first();
            
            if ($existingUser) {
                $user = $existingUser;
                echo "Using existing user: " . $user->name . "\n";
            } else {
                // Create user with production role
                $user = User::create([
                    'name' => $agentData['name'],
                    'email' => $agentData['email'], 
                    'password' => Hash::make('password123'),
                    'role' => 'production', // Valid role in your system
                    'phone' => '+234' . rand(7000000000, 9999999999),
                ]);
                echo "Created new user: " . $user->name . "\n";
            }

            // Create delivery agent profile if doesn't exist
            if (!DeliveryAgent::where('user_id', $user->id)->exists()) {
                DeliveryAgent::create([
                    'user_id' => $user->id,
                    'da_code' => $agentData['da_code'],
                    'vehicle_type' => 'motorcycle',
                    'status' => 'active',
                    'total_deliveries' => $agentData['total_deliveries'],
                    'successful_deliveries' => $agentData['successful_deliveries'],
                    'rating' => rand(35, 50) / 10,
                    'total_earnings' => rand(50000, 200000),
                ]);
                echo "Created delivery agent profile for: " . $user->name . "\n";
            } else {
                echo "Delivery agent profile already exists for: " . $user->name . "\n";
            }
        }
        
        echo "Seeding completed!\n";
    }
}

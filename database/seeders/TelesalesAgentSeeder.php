<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\TelesalesAgent;
use Carbon\Carbon;

class TelesalesAgentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agents = [
            [
                'name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@vitalvida.com',
                'phone' => '+2348012345678',
                'employment_start' => '2024-01-15',
                'status' => 'active',
                'accumulated_bonus' => 45000,
                'bonus_unlocked' => true,
                'weekly_performance' => [
                    '2025-07-21' => [
                        'orders_assigned' => 25,
                        'orders_delivered' => 20,
                        'delivery_rate' => 80.0,
                        'qualified' => true,
                        'bonus_earned' => 3000,
                        'avg_response_time' => 2.5
                    ]
                ]
            ],
            [
                'name' => 'Michael Adebayo',
                'email' => 'michael.adebayo@vitalvida.com',
                'phone' => '+2348023456789',
                'employment_start' => '2024-02-20',
                'status' => 'active',
                'accumulated_bonus' => 32000,
                'bonus_unlocked' => true,
                'weekly_performance' => [
                    '2025-07-21' => [
                        'orders_assigned' => 22,
                        'orders_delivered' => 18,
                        'delivery_rate' => 81.8,
                        'qualified' => true,
                        'bonus_earned' => 2700,
                        'avg_response_time' => 3.1
                    ]
                ]
            ],
            [
                'name' => 'Fatima Hassan',
                'email' => 'fatima.hassan@vitalvida.com',
                'phone' => '+2348034567890',
                'employment_start' => '2024-03-10',
                'status' => 'active',
                'accumulated_bonus' => 28000,
                'bonus_unlocked' => true,
                'weekly_performance' => [
                    '2025-07-21' => [
                        'orders_assigned' => 20,
                        'orders_delivered' => 16,
                        'delivery_rate' => 80.0,
                        'qualified' => true,
                        'bonus_earned' => 2400,
                        'avg_response_time' => 2.8
                    ]
                ]
            ],
            [
                'name' => 'David Okonkwo',
                'email' => 'david.okonkwo@vitalvida.com',
                'phone' => '+2348045678901',
                'employment_start' => '2024-04-05',
                'status' => 'active',
                'accumulated_bonus' => 15000,
                'bonus_unlocked' => false,
                'weekly_performance' => [
                    '2025-07-21' => [
                        'orders_assigned' => 18,
                        'orders_delivered' => 12,
                        'delivery_rate' => 66.7,
                        'qualified' => false,
                        'bonus_earned' => 0,
                        'avg_response_time' => 4.2
                    ]
                ]
            ],
            [
                'name' => 'Grace Eze',
                'email' => 'grace.eze@vitalvida.com',
                'phone' => '+2348056789012',
                'employment_start' => '2024-05-12',
                'status' => 'active',
                'accumulated_bonus' => 8000,
                'bonus_unlocked' => false,
                'weekly_performance' => [
                    '2025-07-21' => [
                        'orders_assigned' => 15,
                        'orders_delivered' => 10,
                        'delivery_rate' => 66.7,
                        'qualified' => false,
                        'bonus_earned' => 0,
                        'avg_response_time' => 5.1
                    ]
                ]
            ]
        ];

        foreach ($agents as $agent) {
            TelesalesAgent::create($agent);
        }
    }
}

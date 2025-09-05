<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\WeeklyPerformance;
use App\Models\TelesalesAgent;
use Carbon\Carbon;

class WeeklyPerformanceSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $agents = TelesalesAgent::all();
        
        if ($agents->isEmpty()) {
            $this->command->info('No telesales agents found. Please run TelesalesAgentSeeder first.');
            return;
        }

        // Create performance data for the last 4 weeks
        for ($week = 0; $week < 4; $week++) {
            $weekStart = now()->subWeeks($week)->startOfWeek();
            $weekEnd = $weekStart->copy()->endOfWeek();
            
            foreach ($agents as $agent) {
                $ordersAssigned = rand(15, 30);
                $ordersDelivered = rand(10, $ordersAssigned);
                $deliveryRate = $ordersAssigned > 0 ? ($ordersDelivered / $ordersAssigned) * 100 : 0;
                $qualified = $deliveryRate >= 70 && $ordersAssigned >= 20;
                $bonusEarned = $qualified ? $ordersDelivered * 150 : 0;
                $avgResponseTime = rand(20, 60) / 10; // 2.0 to 6.0 minutes
                
                WeeklyPerformance::create([
                    'telesales_agent_id' => $agent->id,
                    'week_start' => $weekStart->format('Y-m-d'),
                    'week_end' => $weekEnd->format('Y-m-d'),
                    'orders_assigned' => $ordersAssigned,
                    'orders_delivered' => $ordersDelivered,
                    'delivery_rate' => $deliveryRate,
                    'qualified' => $qualified,
                    'bonus_earned' => $bonusEarned,
                    'avg_response_time' => $avgResponseTime
                ]);
            }
        }
    }
}

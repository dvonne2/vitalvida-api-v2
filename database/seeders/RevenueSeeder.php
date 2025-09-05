<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Revenue;
use App\Models\Department;
use Carbon\Carbon;

class RevenueSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = Department::all();
        
        // Generate revenue data for the last 30 days
        for ($i = 30; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            
            foreach ($departments as $department) {
                // Generate realistic daily revenue with some variation
                $baseRevenue = $department->current_revenue / 30; // Daily average
                $variation = rand(-20, 20) / 100; // ±20% variation
                $dailyRevenue = $baseRevenue * (1 + $variation);
                
                // Break down into different revenue types
                $orderRevenue = $dailyRevenue * 0.6; // 60% from orders
                $deliveryRevenue = $dailyRevenue * 0.25; // 25% from delivery
                $serviceRevenue = $dailyRevenue * 0.1; // 10% from services
                $otherRevenue = $dailyRevenue * 0.05; // 5% from other sources
                
                Revenue::create([
                    'date' => $date->toDateString(),
                    'total_revenue' => $dailyRevenue,
                    'order_revenue' => $orderRevenue,
                    'delivery_revenue' => $deliveryRevenue,
                    'service_revenue' => $serviceRevenue,
                    'other_revenue' => $otherRevenue,
                    'department_id' => $department->id,
                    'source' => 'system',
                    'currency' => 'NGN',
                    'exchange_rate' => 1,
                    'notes' => 'Generated revenue data for ' . $date->format('Y-m-d'),
                    'created_by' => 1,
                ]);
            }
        }

        $this->command->info('Revenue data seeded successfully!');
    }
}

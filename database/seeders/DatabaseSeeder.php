<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\DeliveryAgent;
use App\Models\Delivery;
use App\Models\AgentPerformanceMetric;
use App\Models\StrikeLog;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('🌱 Starting comprehensive database seeding...');

        // Create admin users
        $this->command->info('👑 Creating admin users...');
        $admin = User::factory()->admin()->create([
            'name' => 'System Administrator',
            'email' => 'admin@vitalvida.com',
            'phone' => '08012345678'
        ]);

        $inventoryManager = User::factory()->inventoryManager()->create([
            'name' => 'Inventory Manager',
            'email' => 'inventory@vitalvida.com',
            'phone' => '08012345679'
        ]);

        // Create delivery agents with realistic distribution
        $this->command->info('🚚 Creating delivery agents...');
        
        // Top performers (20%)
        $topPerformers = DeliveryAgent::factory()
            ->count(10)
            ->topPerformer()
            ->create();

        // Average performers (60%)
        $averagePerformers = DeliveryAgent::factory()
            ->count(30)
            ->create();

        // Struggling agents (20%)
        $strugglingAgents = DeliveryAgent::factory()
            ->count(10)
            ->struggling()
            ->create();

        $allAgents = $topPerformers->concat($averagePerformers)->concat($strugglingAgents);

        $this->command->info('📦 Creating deliveries...');
        
        // Create deliveries for each agent
        foreach ($allAgents as $agent) {
            // Completed deliveries (70%)
            Delivery::factory()
                ->count(rand(15, 40))
                ->completed()
                ->create(['delivery_agent_id' => $agent->id]);

            // Failed deliveries (15%)
            Delivery::factory()
                ->count(rand(2, 8))
                ->failed()
                ->create(['delivery_agent_id' => $agent->id]);

            // Pending deliveries (15%)
            Delivery::factory()
                ->count(rand(1, 5))
                ->create([
                    'delivery_agent_id' => $agent->id,
                    'status' => 'assigned'
                ]);
        }

        $this->command->info('📊 Creating performance metrics...');
        
        // Create performance metrics for last 30 days
        foreach ($allAgents as $agent) {
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i)->toDateString();
                $deliveries = $agent->deliveries()
                    ->whereDate('assigned_at', $date)
                    ->get();

                if ($deliveries->count() > 0) {
                    AgentPerformanceMetric::create([
                        'delivery_agent_id' => $agent->id,
                        'metric_date' => $date,
                        'deliveries_assigned' => $deliveries->count(),
                        'deliveries_completed' => $deliveries->where('status', 'delivered')->count(),
                        'deliveries_failed' => $deliveries->where('status', 'failed')->count(),
                        'success_rate' => $agent->success_rate,
                        'average_delivery_time' => $agent->average_delivery_time,
                        'total_distance_km' => rand(20, 100),
                        'average_rating' => $agent->rating,
                        'total_earnings' => $deliveries->count() * rand(500, 1500),
                        'active_hours' => rand(6, 12),
                    ]);
                }
            }
        }

        $this->command->info('⚠️ Creating strikes for struggling agents...');
        
        // Add strikes to struggling agents
        foreach ($strugglingAgents as $agent) {
            $strikeCount = rand(1, 3);
            for ($i = 0; $i < $strikeCount; $i++) {
                StrikeLog::create([
                    'delivery_agent_id' => $agent->id,
                    'reason' => collect([
                        'Late delivery',
                        'Customer complaint',
                        'Missed pickup',
                        'Poor customer service',
                        'Package damage'
                    ])->random(),
                    'severity' => collect(['low', 'medium', 'high'])->random(),
                    'issued_by' => $admin->id,
                    'notes' => 'Auto-generated strike for testing',
                    'source' => 'system'
                ]);
            }
        }

        $this->command->info('✅ Database seeding completed successfully!');
        $this->command->info("📈 Summary:");
        $this->command->info("   - " . User::count() . " users created");
        $this->command->info("   - " . DeliveryAgent::count() . " delivery agents created");
        $this->command->info("   - " . Delivery::count() . " deliveries created");
        $this->command->info("   - " . AgentPerformanceMetric::count() . " performance records created");
        $this->command->info("   - " . StrikeLog::count() . " strikes created");
    }
}

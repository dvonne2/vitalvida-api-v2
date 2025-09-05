<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Roadmap;
use Carbon\Carbon;

class RoadmapSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roadmapData = [
            [
                'initiative' => 'Launch Fulani Hair Gro Kids',
                'owner' => 'CEO',
                'completion_percentage' => 67,
                'quarter' => 'Q2',
                'status' => 'monitor',
                'milestones' => [
                    ['task' => 'Product formulation', 'completed' => true],
                    ['task' => 'Packaging design', 'completed' => true],
                    ['task' => 'Marketing campaign', 'completed' => false],
                    ['task' => 'Launch event', 'completed' => false]
                ],
                'current_value' => 67,
                'target_value' => 100,
                'value_unit' => 'percentage',
                'start_date' => Carbon::now()->subMonths(2),
                'target_date' => Carbon::now()->addMonth(),
                'description' => 'Launch new kids hair care product line'
            ],
            [
                'initiative' => 'Expand to 100 DAs nationwide',
                'owner' => 'Ops Manager',
                'completion_percentage' => 85,
                'quarter' => 'Q2',
                'status' => 'good',
                'milestones' => [
                    ['task' => 'Recruit 50 new DAs', 'completed' => true],
                    ['task' => 'Train new DAs', 'completed' => true],
                    ['task' => 'Deploy to 5 new states', 'completed' => true],
                    ['task' => 'Reach 100 DAs', 'completed' => false]
                ],
                'current_value' => 127,
                'target_value' => 150,
                'value_unit' => 'DAs',
                'start_date' => Carbon::now()->subMonths(3),
                'target_date' => Carbon::now()->addMonth(),
                'description' => 'Expand delivery network nationwide'
            ],
            [
                'initiative' => 'Achieve ₦50M revenue',
                'owner' => 'Sales Head',
                'completion_percentage' => 45,
                'quarter' => 'Q2',
                'status' => 'fix',
                'milestones' => [
                    ['task' => 'Increase marketing spend', 'completed' => true],
                    ['task' => 'Launch new products', 'completed' => true],
                    ['task' => 'Expand to new markets', 'completed' => false],
                    ['task' => 'Optimize conversion funnel', 'completed' => false]
                ],
                'current_value' => 22500000,
                'target_value' => 50000000,
                'value_unit' => 'revenue',
                'start_date' => Carbon::now()->subMonths(2),
                'target_date' => Carbon::now()->addMonth(),
                'description' => 'Achieve ₦50M monthly revenue target'
            ],
            [
                'initiative' => 'Build customer referral system',
                'owner' => 'CTO',
                'completion_percentage' => 23,
                'quarter' => 'Q3',
                'status' => 'good',
                'milestones' => [
                    ['task' => 'Design system architecture', 'completed' => true],
                    ['task' => 'Develop MVP', 'completed' => false],
                    ['task' => 'Test with beta users', 'completed' => false],
                    ['task' => 'Launch to all customers', 'completed' => false]
                ],
                'current_value' => 23,
                'target_value' => 100,
                'value_unit' => 'percentage',
                'start_date' => Carbon::now()->subMonth(),
                'target_date' => Carbon::now()->addMonths(2),
                'description' => 'Develop customer referral and loyalty system'
            ],
            [
                'initiative' => 'Optimize supply chain',
                'owner' => 'Inventory Manager',
                'completion_percentage' => 78,
                'quarter' => 'Q2',
                'status' => 'good',
                'milestones' => [
                    ['task' => 'Audit current suppliers', 'completed' => true],
                    ['task' => 'Negotiate better terms', 'completed' => true],
                    ['task' => 'Implement new processes', 'completed' => true],
                    ['task' => 'Monitor performance', 'completed' => false]
                ],
                'current_value' => 78,
                'target_value' => 100,
                'value_unit' => 'percentage',
                'start_date' => Carbon::now()->subMonths(2),
                'target_date' => Carbon::now()->addWeek(),
                'description' => 'Optimize supply chain for cost efficiency'
            ],
            [
                'initiative' => 'Launch mobile app',
                'owner' => 'CTO',
                'completion_percentage' => 12,
                'quarter' => 'Q3',
                'status' => 'monitor',
                'milestones' => [
                    ['task' => 'Design app wireframes', 'completed' => true],
                    ['task' => 'Develop core features', 'completed' => false],
                    ['task' => 'Beta testing', 'completed' => false],
                    ['task' => 'App store submission', 'completed' => false]
                ],
                'current_value' => 12,
                'target_value' => 100,
                'value_unit' => 'percentage',
                'start_date' => Carbon::now()->subMonth(),
                'target_date' => Carbon::now()->addMonths(3),
                'description' => 'Launch mobile app for customers'
            ]
        ];

        foreach ($roadmapData as $roadmap) {
            Roadmap::create($roadmap);
        }

        $this->command->info('Roadmap data seeded successfully!');
    }
}

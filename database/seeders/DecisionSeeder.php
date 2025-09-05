<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Decision;
use Carbon\Carbon;

class DecisionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $decisionsData = [
            [
                'decision_date' => '2024-01-10',
                'decision_title' => 'Switched courier in North',
                'context' => 'High delays + cost',
                'outcome' => 'Reduced SLA breaches by 40%',
                'lesson_learned' => 'Vet regional partners deeply',
                'impact_score' => 8,
                'department' => 'Logistics',
                'decision_maker' => 'Ops Manager',
                'category' => 'operational',
                'tags' => ['logistics', 'partnership', 'cost-reduction']
            ],
            [
                'decision_date' => '2024-01-06',
                'decision_title' => 'Stopped ₦1000 discount promo',
                'context' => 'High fraud rate',
                'outcome' => 'Fraud reduced by 60%',
                'lesson_learned' => 'Test small before scaling promos',
                'impact_score' => 7,
                'department' => 'Marketing',
                'decision_maker' => 'Marketing Head',
                'category' => 'tactical',
                'tags' => ['promotion', 'fraud-prevention', 'testing']
            ],
            [
                'decision_date' => '2023-12-20',
                'decision_title' => 'Added OTP for delivery confirmation',
                'context' => 'DA payment mismatches',
                'outcome' => '99% payment accuracy',
                'lesson_learned' => 'Tech solutions beat manual processes',
                'impact_score' => 9,
                'department' => 'Technology',
                'decision_maker' => 'CTO',
                'category' => 'strategic',
                'tags' => ['technology', 'automation', 'payment-security']
            ],
            [
                'decision_date' => '2024-01-08',
                'decision_title' => 'Increased ad spend on Facebook',
                'context' => 'Low ROAS on TikTok',
                'outcome' => 'ROAS improved by 25%',
                'lesson_learned' => 'Focus on proven channels',
                'impact_score' => 6,
                'department' => 'Media',
                'decision_maker' => 'Media Head',
                'category' => 'tactical',
                'tags' => ['advertising', 'roas', 'channel-optimization']
            ],
            [
                'decision_date' => '2024-01-05',
                'decision_title' => 'Hired 10 new DAs',
                'context' => 'High delivery delays',
                'outcome' => 'Delivery time reduced by 30%',
                'lesson_learned' => 'Invest in capacity before it\'s needed',
                'impact_score' => 8,
                'department' => 'Operations',
                'decision_maker' => 'Ops Manager',
                'category' => 'operational',
                'tags' => ['hiring', 'capacity-planning', 'delivery']
            ],
            [
                'decision_date' => '2023-12-15',
                'decision_title' => 'Switched to bulk inventory purchases',
                'context' => 'High unit costs',
                'outcome' => 'Cost reduced by 15%',
                'lesson_learned' => 'Economies of scale matter',
                'impact_score' => 7,
                'department' => 'Inventory',
                'decision_maker' => 'Inventory Manager',
                'category' => 'strategic',
                'tags' => ['inventory', 'cost-reduction', 'bulk-purchasing']
            ],
            [
                'decision_date' => '2024-01-12',
                'decision_title' => 'Implemented customer feedback system',
                'context' => 'Poor customer satisfaction',
                'outcome' => 'Satisfaction score increased to 4.3/5',
                'lesson_learned' => 'Listen to customers actively',
                'impact_score' => 6,
                'department' => 'Customer Service',
                'decision_maker' => 'Customer Service Head',
                'category' => 'operational',
                'tags' => ['customer-service', 'feedback', 'satisfaction']
            ],
            [
                'decision_date' => '2024-01-03',
                'decision_title' => 'Launched referral program',
                'context' => 'High customer acquisition cost',
                'outcome' => 'CAC reduced by 20%',
                'lesson_learned' => 'Word-of-mouth is powerful',
                'impact_score' => 8,
                'department' => 'Marketing',
                'decision_maker' => 'Marketing Head',
                'category' => 'strategic',
                'tags' => ['referral', 'cac-reduction', 'word-of-mouth']
            ]
        ];

        foreach ($decisionsData as $decision) {
            Decision::create($decision);
        }

        $this->command->info('Decision log data seeded successfully!');
    }
}

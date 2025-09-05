<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Risk;
use Carbon\Carbon;

class RiskSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $risksData = [
            [
                'risk_title' => 'Low ROAS on TikTok ads',
                'severity' => 'medium',
                'probability' => 'high',
                'impact_description' => '₦50K spent, 1 order',
                'mitigation_plan' => 'Pause campaign, analyze creatives',
                'owner' => 'Media Head',
                'status' => 'active',
                'identified_date' => Carbon::now()->subDays(3),
                'target_resolution_date' => Carbon::now()->addDays(7),
                'financial_impact' => 50000,
                'notes' => 'TikTok campaign underperforming compared to Facebook'
            ],
            [
                'risk_title' => 'DA JD-022 not responding',
                'severity' => 'high',
                'probability' => 'confirmed',
                'impact_description' => '2 pending orders in Lagos',
                'mitigation_plan' => 'Reassign orders, contact DA',
                'owner' => 'Ops Lead',
                'status' => 'escalated',
                'identified_date' => Carbon::now()->subDays(1),
                'target_resolution_date' => Carbon::now()->addDays(1),
                'financial_impact' => 15000,
                'notes' => 'Delivery agent not responding to calls and messages'
            ],
            [
                'risk_title' => 'Fulani Conditioner stocked in Abuja',
                'severity' => 'low',
                'probability' => 'confirmed',
                'impact_description' => 'Overstock in one location',
                'mitigation_plan' => 'Redistribute inventory',
                'owner' => 'Inventory Lead',
                'status' => 'planned',
                'identified_date' => Carbon::now()->subDays(2),
                'target_resolution_date' => Carbon::now()->addDays(5),
                'financial_impact' => 25000,
                'notes' => 'Inventory imbalance detected'
            ],
            [
                'risk_title' => 'Payment gateway downtime',
                'severity' => 'high',
                'probability' => 'low',
                'impact_description' => 'Potential loss of orders during peak hours',
                'mitigation_plan' => 'Implement backup payment system',
                'owner' => 'CTO',
                'status' => 'active',
                'identified_date' => Carbon::now()->subDays(5),
                'target_resolution_date' => Carbon::now()->addDays(14),
                'financial_impact' => 100000,
                'notes' => 'Payment gateway experiencing intermittent issues'
            ],
            [
                'risk_title' => 'Key supplier price increase',
                'severity' => 'medium',
                'probability' => 'medium',
                'impact_description' => '15% cost increase on raw materials',
                'mitigation_plan' => 'Negotiate terms, find alternatives',
                'owner' => 'Inventory Manager',
                'status' => 'active',
                'identified_date' => Carbon::now()->subDays(7),
                'target_resolution_date' => Carbon::now()->addDays(21),
                'financial_impact' => 500000,
                'notes' => 'Supplier announced price increase effective next month'
            ],
            [
                'risk_title' => 'Customer data breach',
                'severity' => 'critical',
                'probability' => 'low',
                'impact_description' => 'Potential legal and reputational damage',
                'mitigation_plan' => 'Implement enhanced security measures',
                'owner' => 'CTO',
                'status' => 'active',
                'identified_date' => Carbon::now()->subDays(10),
                'target_resolution_date' => Carbon::now()->addDays(30),
                'financial_impact' => 1000000,
                'notes' => 'Security audit revealed potential vulnerabilities'
            ],
            [
                'risk_title' => 'Delivery vehicle breakdown',
                'severity' => 'medium',
                'probability' => 'medium',
                'impact_description' => 'Delayed deliveries in Lagos zone',
                'mitigation_plan' => 'Arrange backup vehicles',
                'owner' => 'Ops Lead',
                'status' => 'resolved',
                'identified_date' => Carbon::now()->subDays(2),
                'resolved_date' => Carbon::now()->subDay(),
                'financial_impact' => 20000,
                'notes' => 'Vehicle breakdown resolved with backup vehicle'
            ],
            [
                'risk_title' => 'Inventory stockout risk',
                'severity' => 'high',
                'probability' => 'medium',
                'impact_description' => 'Popular products running low',
                'mitigation_plan' => 'Expedite restocking orders',
                'owner' => 'Inventory Manager',
                'status' => 'active',
                'identified_date' => Carbon::now()->subDays(1),
                'target_resolution_date' => Carbon::now()->addDays(3),
                'financial_impact' => 75000,
                'notes' => 'Fulani Shampoo stock below safety level'
            ]
        ];

        foreach ($risksData as $risk) {
            Risk::create($risk);
        }

        $this->command->info('Risk assessment data seeded successfully!');
    }
}

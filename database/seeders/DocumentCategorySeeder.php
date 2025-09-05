<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DocumentCategory;
use App\Models\Investor;

class DocumentCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            [
                'name' => 'Financials',
                'description' => 'Financial statements, P&L, balance sheets, and cash flow reports',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_TOMI_GOVERNANCE,
                    Investor::ROLE_OTUNBA_CONTROL,
                    Investor::ROLE_DANGOTE_COST_CONTROL
                ],
                'display_order' => 1,
                'icon' => 'fas fa-chart-line',
                'color' => '#28a745'
            ],
            [
                'name' => 'Operations',
                'description' => 'Operational metrics, processes, and efficiency reports',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_RON_SCALE,
                    Investor::ROLE_ANDY_TECH
                ],
                'display_order' => 2,
                'icon' => 'fas fa-cogs',
                'color' => '#17a2b8'
            ],
            [
                'name' => 'Governance',
                'description' => 'Board resolutions, compliance documents, and governance policies',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_TOMI_GOVERNANCE
                ],
                'display_order' => 3,
                'icon' => 'fas fa-shield-alt',
                'color' => '#6f42c1'
            ],
            [
                'name' => 'Vision & Strategy',
                'description' => 'Strategic plans, market analysis, and vision documents',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_THIEL_STRATEGY,
                    Investor::ROLE_NEIL_GROWTH
                ],
                'display_order' => 4,
                'icon' => 'fas fa-eye',
                'color' => '#fd7e14'
            ],
            [
                'name' => 'Technology',
                'description' => 'Technical architecture, automation metrics, and tech stack',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_ANDY_TECH
                ],
                'display_order' => 5,
                'icon' => 'fas fa-microchip',
                'color' => '#e83e8c'
            ],
            [
                'name' => 'Growth & Marketing',
                'description' => 'Growth metrics, marketing performance, and customer acquisition',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_NEIL_GROWTH,
                    Investor::ROLE_RON_SCALE
                ],
                'display_order' => 6,
                'icon' => 'fas fa-chart-bar',
                'color' => '#20c997'
            ],
            [
                'name' => 'Legal & Compliance',
                'description' => 'Legal documents, contracts, and regulatory compliance',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_TOMI_GOVERNANCE
                ],
                'display_order' => 7,
                'icon' => 'fas fa-gavel',
                'color' => '#dc3545'
            ],
            [
                'name' => 'Risk Management',
                'description' => 'Risk assessments, mitigation strategies, and insurance',
                'required_for_investor_type' => [
                    Investor::ROLE_MASTER_READINESS,
                    Investor::ROLE_OTUNBA_CONTROL
                ],
                'display_order' => 8,
                'icon' => 'fas fa-exclamation-triangle',
                'color' => '#ffc107'
            ]
        ];

        foreach ($categories as $categoryData) {
            DocumentCategory::updateOrCreate(
                ['name' => $categoryData['name']],
                $categoryData
            );
        }

        $this->command->info('Document categories seeded successfully!');
    }
}

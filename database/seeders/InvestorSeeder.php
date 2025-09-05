<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Investor;
use Illuminate\Support\Facades\Hash;

class InvestorSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $investors = [
            [
                'name' => 'Master Readiness Investor',
                'email' => 'master@vitalvida.com',
                'phone' => '+2348012345678',
                'role' => Investor::ROLE_MASTER_READINESS,
                'access_level' => Investor::ACCESS_FULL,
                'permissions' => ['view_all', 'download_all', 'edit_all'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'overview',
                        'refresh_interval' => 300,
                        'show_alerts' => true,
                        'show_metrics' => true,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => true,
                        'valuation_updates' => true
                    ]
                ],
                'company_name' => 'Master Readiness Fund',
                'position' => 'Managing Partner',
                'bio' => 'Experienced investor with full access to all VitalVida data and operations.',
                'is_active' => true
            ],
            [
                'name' => 'Tomi Governance',
                'email' => 'tomi@vitalvida.com',
                'phone' => '+2348023456789',
                'role' => Investor::ROLE_TOMI_GOVERNANCE,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_governance', 'view_financials', 'download_governance'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'governance',
                        'refresh_interval' => 600,
                        'show_alerts' => true,
                        'show_metrics' => false,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => true,
                        'valuation_updates' => false
                    ]
                ],
                'company_name' => 'Governance Partners',
                'position' => 'Board Member',
                'bio' => 'Focus on governance, compliance, and board-level oversight.',
                'is_active' => true
            ],
            [
                'name' => 'Ron Scale',
                'email' => 'ron@vitalvida.com',
                'phone' => '+2348034567890',
                'role' => Investor::ROLE_RON_SCALE,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_operations', 'view_growth', 'download_operations'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'operations',
                        'refresh_interval' => 300,
                        'show_alerts' => true,
                        'show_metrics' => true,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => false,
                        'valuation_updates' => false
                    ]
                ],
                'company_name' => 'Scale Ventures',
                'position' => 'Operations Partner',
                'bio' => 'Specialized in scaling operations and operational efficiency.',
                'is_active' => true
            ],
            [
                'name' => 'Thiel Strategy',
                'email' => 'thiel@vitalvida.com',
                'phone' => '+2348045678901',
                'role' => Investor::ROLE_THIEL_STRATEGY,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_strategy', 'view_growth', 'download_strategy'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'strategy',
                        'refresh_interval' => 600,
                        'show_alerts' => false,
                        'show_metrics' => true,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => true,
                        'valuation_updates' => true
                    ]
                ],
                'company_name' => 'Strategic Growth Fund',
                'position' => 'Strategic Advisor',
                'bio' => 'Strategic advisor focused on long-term growth and market positioning.',
                'is_active' => true
            ],
            [
                'name' => 'Andy Tech',
                'email' => 'andy@vitalvida.com',
                'phone' => '+2348056789012',
                'role' => Investor::ROLE_ANDY_TECH,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_tech', 'view_operations', 'download_tech'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'tech',
                        'refresh_interval' => 300,
                        'show_alerts' => true,
                        'show_metrics' => true,
                        'show_documents' => false
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => false,
                        'financial_reports' => false,
                        'valuation_updates' => false
                    ]
                ],
                'company_name' => 'Tech Ventures',
                'position' => 'Technology Partner',
                'bio' => 'Technology-focused investor with expertise in automation and efficiency.',
                'is_active' => true
            ],
            [
                'name' => 'Otunba Control',
                'email' => 'otunba@vitalvida.com',
                'phone' => '+2348067890123',
                'role' => Investor::ROLE_OTUNBA_CONTROL,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_financials', 'view_control', 'download_financials'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'financials',
                        'refresh_interval' => 300,
                        'show_alerts' => true,
                        'show_metrics' => true,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => true,
                        'valuation_updates' => true
                    ]
                ],
                'company_name' => 'Financial Control Partners',
                'position' => 'Financial Controller',
                'bio' => 'Financial oversight and control specialist.',
                'is_active' => true
            ],
            [
                'name' => 'Dangote Cost Control',
                'email' => 'dangote@vitalvida.com',
                'phone' => '+2348078901234',
                'role' => Investor::ROLE_DANGOTE_COST_CONTROL,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_costs', 'view_financials', 'download_costs'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'costs',
                        'refresh_interval' => 300,
                        'show_alerts' => true,
                        'show_metrics' => true,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => true,
                        'valuation_updates' => false
                    ]
                ],
                'company_name' => 'Cost Control Ventures',
                'position' => 'Cost Control Specialist',
                'bio' => 'Specialized in cost optimization and efficiency management.',
                'is_active' => true
            ],
            [
                'name' => 'Neil Growth',
                'email' => 'neil@vitalvida.com',
                'phone' => '+2348089012345',
                'role' => Investor::ROLE_NEIL_GROWTH,
                'access_level' => Investor::ACCESS_LIMITED,
                'permissions' => ['view_growth', 'view_strategy', 'download_growth'],
                'preferences' => [
                    'dashboard' => [
                        'default_view' => 'growth',
                        'refresh_interval' => 300,
                        'show_alerts' => true,
                        'show_metrics' => true,
                        'show_documents' => true
                    ],
                    'notifications' => [
                        'email_notifications' => true,
                        'document_updates' => true,
                        'financial_reports' => true,
                        'valuation_updates' => true
                    ]
                ],
                'company_name' => 'Growth Capital Partners',
                'position' => 'Growth Partner',
                'bio' => 'Growth-focused investor with expertise in scaling businesses.',
                'is_active' => true
            ]
        ];

        foreach ($investors as $investorData) {
            Investor::updateOrCreate(
                ['email' => $investorData['email']],
                array_merge($investorData, [
                    'password' => Hash::make('password123'),
                    'email_verified_at' => now(),
                    'phone_verified_at' => now()
                ])
            );
        }

        $this->command->info('Investor data seeded successfully!');
    }
}

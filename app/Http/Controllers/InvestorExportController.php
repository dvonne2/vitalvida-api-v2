<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\InvestorDocument;
use App\Models\FinancialStatement;
use App\Models\Order;
use App\Models\Revenue;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class InvestorExportController extends Controller
{
    /**
     * Get export options
     */
    public function getExportOptions(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $exportOptions = [
                [
                    'type' => 'investor_deck',
                    'format' => 'pdf',
                    'description' => 'Clear, visual pitch (10-12 slides)',
                    'endpoint' => '/api/investor/export/pitch-deck',
                    'estimated_size' => '3.2MB',
                    'sections' => [
                        'Executive Summary',
                        'Market Opportunity',
                        'Product Portfolio',
                        'Financial Performance',
                        'Growth Strategy',
                        'Investment Ask'
                    ]
                ],
                [
                    'type' => 'financial_statements',
                    'format' => 'excel',
                    'description' => 'P&L, Balance Sheet, Cash Flow',
                    'endpoint' => '/api/investor/export/financial-package',
                    'estimated_size' => '1.8MB',
                    'sections' => [
                        'Profit & Loss Statement',
                        'Balance Sheet',
                        'Cash Flow Statement',
                        'Financial Ratios',
                        'Revenue Breakdown'
                    ]
                ],
                [
                    'type' => 'operational_metrics',
                    'format' => 'pdf',
                    'description' => 'KPIs, efficiency scores, automation metrics',
                    'endpoint' => '/api/investor/export/operations-report',
                    'estimated_size' => '2.1MB',
                    'sections' => [
                        'Key Performance Indicators',
                        'Operational Efficiency',
                        'Automation Metrics',
                        'Quality Control',
                        'Process Optimization'
                    ]
                ],
                [
                    'type' => 'investor_update',
                    'format' => 'pdf',
                    'description' => 'Monthly progress report for all investors',
                    'endpoint' => '/api/investor/export/monthly-update',
                    'estimated_size' => '2.8MB',
                    'sections' => [
                        'Monthly Highlights',
                        'Financial Summary',
                        'Operational Updates',
                        'Growth Metrics',
                        'Strategic Initiatives'
                    ]
                ],
                [
                    'type' => 'due_diligence_package',
                    'format' => 'zip',
                    'description' => 'Complete folder with all 30 documents',
                    'endpoint' => '/api/investor/export/full-package',
                    'estimated_size' => '15.6MB',
                    'sections' => [
                        'Financial Documents',
                        'Legal Documents',
                        'Operational Documents',
                        'Marketing Materials',
                        'Strategic Plans'
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'export_options' => $exportOptions,
                    'available_formats' => ['pdf', 'excel', 'powerpoint', 'zip'],
                    'role_specific_reports' => $this->getRoleSpecificReports($investor)
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load export options',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate custom report
     */
    public function generateReport(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $request->validate([
                'investor_type' => 'required|in:all,specific_role',
                'date_range.start' => 'required|date',
                'date_range.end' => 'required|date|after:date_range.start',
                'include_sections' => 'required|array',
                'format' => 'required|in:pdf,excel,powerpoint'
            ]);

            $investorType = $request->get('investor_type');
            $dateRange = $request->get('date_range');
            $includeSections = $request->get('include_sections');
            $format = $request->get('format');

            $reportData = $this->generateReportData($investor, $investorType, $dateRange, $includeSections);

            $exportData = [
                'report_id' => 'REP-' . strtoupper(uniqid()),
                'report_type' => 'Custom Investor Report',
                'investor_type' => $investorType,
                'date_range' => $dateRange,
                'format' => $format,
                'sections_included' => $includeSections,
                'download_url' => '/api/investor/export/download/' . $format,
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => $this->getEstimatedFileSize($format, count($includeSections)),
                'generated_at' => now()->toISOString(),
                'generated_by' => $investor->name
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export pitch deck
     */
    public function exportPitchDeck(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $pitchDeckData = [
                'title' => 'VitalVida - Investor Pitch Deck',
                'subtitle' => 'Revolutionizing Natural Health in Nigeria',
                'slides' => [
                    [
                        'slide_number' => 1,
                        'title' => 'Executive Summary',
                        'content' => [
                            'company_name' => 'VitalVida',
                            'founded' => '2023',
                            'headquarters' => 'Lagos, Nigeria',
                            'mission' => 'Making natural health accessible to every Nigerian',
                            'current_stage' => 'Series A Ready',
                            'valuation' => '₦7.8M'
                        ]
                    ],
                    [
                        'slide_number' => 2,
                        'title' => 'Market Opportunity',
                        'content' => [
                            'market_size' => '₦2.3B Nigerian natural health market',
                            'growth_rate' => '15% CAGR',
                            'target_segment' => 'Health-conscious Nigerians aged 25-45',
                            'market_gap' => 'Lack of quality, locally-sourced natural products'
                        ]
                    ],
                    [
                        'slide_number' => 3,
                        'title' => 'Product Portfolio',
                        'content' => [
                            'moringa_capsules' => '60ct - ₦15,000',
                            'ginger_complex' => '30ct - ₦12,500',
                            'turmeric_boost' => '45ct - ₦13,500',
                            'unique_selling_points' => [
                                'Cold-press extraction preserves 40% more active compounds',
                                'Direct sourcing from Nigerian farmers',
                                '98.7% purity verification'
                            ]
                        ]
                    ],
                    [
                        'slide_number' => 4,
                        'title' => 'Financial Performance',
                        'content' => [
                            'monthly_revenue' => '₦4.85M',
                            'growth_rate' => '+18% week-over-week',
                            'gross_margin' => '94.3%',
                            'net_margin' => '51.2%',
                            'roi_annualized' => '156%'
                        ]
                    ],
                    [
                        'slide_number' => 5,
                        'title' => 'Growth Strategy',
                        'content' => [
                            'geographic_expansion' => 'Enter 5 new states in 2025',
                            'product_development' => 'Launch 3 new products',
                            'automation' => 'Achieve 90% automation by Q1 2025',
                            'partnerships' => 'Strategic partnerships with major retailers'
                        ]
                    ],
                    [
                        'slide_number' => 6,
                        'title' => 'Investment Ask',
                        'content' => [
                            'amount' => '₦50M Series A',
                            'use_of_funds' => [
                                '40% - Product development',
                                '30% - Market expansion',
                                '20% - Technology automation',
                                '10% - Working capital'
                            ],
                            'expected_roi' => '3x return in 24 months'
                        ]
                    ]
                ]
            ];

            $exportData = [
                'report_type' => 'Investor Pitch Deck',
                'format' => 'pdf',
                'download_url' => '/api/investor/export/download/pitch-deck',
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => '3.2MB',
                'slides_count' => 6,
                'generated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export pitch deck',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export financial package
     */
    public function exportFinancialPackage(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $financialData = [
                'profit_loss_statement' => [
                    'period' => 'December 2024',
                    'revenue' => 4850000,
                    'cost_of_goods_sold' => 2425000,
                    'gross_profit' => 2425000,
                    'operating_expenses' => 970000,
                    'operating_income' => 1455000,
                    'net_income' => 1455000,
                    'gross_margin' => '50%',
                    'operating_margin' => '30%',
                    'net_margin' => '30%'
                ],
                'balance_sheet' => [
                    'assets' => [
                        'current_assets' => 3500000,
                        'fixed_assets' => 1200000,
                        'total_assets' => 4700000
                    ],
                    'liabilities' => [
                        'current_liabilities' => 800000,
                        'long_term_liabilities' => 0,
                        'total_liabilities' => 800000
                    ],
                    'equity' => [
                        'shareholders_equity' => 3900000,
                        'total_liabilities_equity' => 4700000
                    ]
                ],
                'cash_flow_statement' => [
                    'operating_cash_flow' => 1455000,
                    'investing_cash_flow' => -300000,
                    'financing_cash_flow' => 0,
                    'net_cash_flow' => 1155000,
                    'cash_balance' => 2495000
                ],
                'financial_ratios' => [
                    'current_ratio' => 4.38,
                    'debt_to_equity' => 0.21,
                    'return_on_equity' => 37.3,
                    'asset_turnover' => 1.03
                ]
            ];

            $exportData = [
                'report_type' => 'Financial Package',
                'format' => 'excel',
                'download_url' => '/api/investor/export/download/financial-package',
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => '1.8MB',
                'sheets_included' => [
                    'Profit & Loss Statement',
                    'Balance Sheet',
                    'Cash Flow Statement',
                    'Financial Ratios',
                    'Revenue Breakdown'
                ],
                'generated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export financial package',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export operations report
     */
    public function exportOperationsReport(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $operationsData = [
                'key_performance_indicators' => [
                    'order_fulfillment_rate' => '98.5%',
                    'customer_satisfaction' => '94.5%',
                    'delivery_time' => '2.8 days average',
                    'return_rate' => '2.1%',
                    'inventory_turnover' => '12.5x'
                ],
                'operational_efficiency' => [
                    'automation_score' => '84%',
                    'process_efficiency' => '79.8%',
                    'quality_control_score' => '98.9%',
                    'fraud_prevention_score' => '99.2%'
                ],
                'automation_metrics' => [
                    'financial_operations' => '87% automated',
                    'inventory_management' => '73% automated',
                    'delivery_coordination' => '91% automated',
                    'quality_control' => '85% automated'
                ],
                'quality_control' => [
                    'ingredient_purity' => '98.7%',
                    'package_integrity' => '99.2%',
                    'customer_verification' => '97.2%',
                    'overall_quality_score' => '98.9'
                ],
                'process_optimization' => [
                    'order_processing_time' => '3.2 min avg',
                    'inventory_pick_time' => '8.7 min avg',
                    'package_seal_time' => '4.1 min avg',
                    'da_assignment_time' => '2.8 min avg',
                    'total_processing_time' => '18.8 min avg'
                ]
            ];

            $exportData = [
                'report_type' => 'Operations Report',
                'format' => 'pdf',
                'download_url' => '/api/investor/export/download/operations-report',
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => '2.1MB',
                'sections_included' => [
                    'Key Performance Indicators',
                    'Operational Efficiency',
                    'Automation Metrics',
                    'Quality Control',
                    'Process Optimization'
                ],
                'generated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export operations report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export monthly update
     */
    public function exportMonthlyUpdate(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $monthlyData = [
                'monthly_highlights' => [
                    'revenue_growth' => '+18% vs last month',
                    'new_customers' => '156 new customers acquired',
                    'market_expansion' => 'Entered 2 new states',
                    'product_launches' => '1 new product launched',
                    'automation_improvements' => '15% efficiency gain'
                ],
                'financial_summary' => [
                    'monthly_revenue' => '₦4.85M',
                    'gross_margin' => '50%',
                    'operating_margin' => '30%',
                    'cash_position' => '₦2.495M',
                    'equity_value' => '₦5.85M'
                ],
                'operational_updates' => [
                    'order_fulfillment' => '98.5% success rate',
                    'customer_satisfaction' => '94.5%',
                    'delivery_efficiency' => '2.8 days average',
                    'quality_control' => '98.9% score'
                ],
                'growth_metrics' => [
                    'customer_acquisition' => '156 new customers',
                    'revenue_per_customer' => '₦31,089',
                    'repeat_customer_rate' => '67%',
                    'market_penetration' => '12.5%'
                ],
                'strategic_initiatives' => [
                    'automation_roadmap' => '90% automation target by Q1 2025',
                    'geographic_expansion' => '5 new states in 2025',
                    'product_development' => '3 new products planned',
                    'partnership_development' => 'Strategic retail partnerships'
                ]
            ];

            $exportData = [
                'report_type' => 'Monthly Investor Update',
                'format' => 'pdf',
                'download_url' => '/api/investor/export/download/monthly-update',
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => '2.8MB',
                'sections_included' => [
                    'Monthly Highlights',
                    'Financial Summary',
                    'Operational Updates',
                    'Growth Metrics',
                    'Strategic Initiatives'
                ],
                'generated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export monthly update',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export full due diligence package
     */
    public function exportFullPackage(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Investor access required.'
                ], 403);
            }

            $fullPackageData = [
                'financial_documents' => [
                    'profit_loss_statements' => '3 years of P&L statements',
                    'balance_sheets' => '3 years of balance sheets',
                    'cash_flow_statements' => '3 years of cash flow statements',
                    'financial_projections' => '5-year financial projections',
                    'audit_reports' => 'Annual audit reports'
                ],
                'legal_documents' => [
                    'articles_of_incorporation' => 'Company registration documents',
                    'shareholder_agreements' => 'Current shareholder agreements',
                    'intellectual_property' => 'Patent applications and trademarks',
                    'contracts' => 'Key vendor and customer contracts',
                    'compliance_documents' => 'Regulatory compliance certificates'
                ],
                'operational_documents' => [
                    'business_plan' => 'Detailed 5-year business plan',
                    'operational_manual' => 'Standard operating procedures',
                    'quality_control' => 'Quality control protocols',
                    'supply_chain' => 'Supply chain documentation',
                    'automation_roadmap' => 'Technology automation roadmap'
                ],
                'marketing_materials' => [
                    'brand_guidelines' => 'Brand identity and guidelines',
                    'marketing_strategy' => 'Comprehensive marketing strategy',
                    'campaign_performance' => 'Historical campaign data',
                    'customer_research' => 'Market research and customer insights',
                    'competitive_analysis' => 'Competitive landscape analysis'
                ],
                'strategic_plans' => [
                    'growth_strategy' => 'Detailed growth strategy',
                    'expansion_plans' => 'Geographic expansion plans',
                    'product_roadmap' => 'Product development roadmap',
                    'technology_strategy' => 'Technology and automation strategy',
                    'risk_management' => 'Risk assessment and mitigation plans'
                ]
            ];

            $exportData = [
                'report_type' => 'Complete Due Diligence Package',
                'format' => 'zip',
                'download_url' => '/api/investor/export/download/full-package',
                'expires_at' => now()->addHours(24)->toISOString(),
                'file_size' => '15.6MB',
                'documents_included' => 30,
                'categories' => [
                    'Financial Documents' => 5,
                    'Legal Documents' => 5,
                    'Operational Documents' => 5,
                    'Marketing Materials' => 5,
                    'Strategic Plans' => 5
                ],
                'generated_at' => now()->toISOString()
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export full package',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get role-specific reports
     */
    private function getRoleSpecificReports($investor)
    {
        $roleReports = [];

        switch ($investor->role) {
            case Investor::ROLE_MASTER_READINESS:
                $roleReports = [
                    'document_checklist' => 'Complete document readiness status',
                    'compliance_report' => 'Regulatory compliance assessment',
                    'investment_readiness' => 'Investment readiness evaluation'
                ];
                break;
            case Investor::ROLE_TOMI_GOVERNANCE:
                $roleReports = [
                    'financial_governance' => 'Financial governance framework',
                    'board_report' => 'Board governance metrics',
                    'compliance_audit' => 'Compliance audit report'
                ];
                break;
            case Investor::ROLE_ANDY_TECH:
                $roleReports = [
                    'technical_audit' => 'Technical infrastructure audit',
                    'automation_report' => 'Automation efficiency report',
                    'innovation_roadmap' => 'Technology innovation roadmap'
                ];
                break;
            case Investor::ROLE_OTUNBA_CONTROL:
                $roleReports = [
                    'financial_control' => 'Financial control framework',
                    'cash_management' => 'Cash management report',
                    'equity_tracking' => 'Equity value tracking report'
                ];
                break;
            case Investor::ROLE_DANGOTE_COST_CONTROL:
                $roleReports = [
                    'cost_analysis' => 'Detailed cost analysis report',
                    'efficiency_metrics' => 'Operational efficiency metrics',
                    'vendor_management' => 'Vendor performance report'
                ];
                break;
            case Investor::ROLE_NEIL_GROWTH:
                $roleReports = [
                    'growth_analytics' => 'Comprehensive growth analytics',
                    'marketing_performance' => 'Marketing performance report',
                    'customer_acquisition' => 'Customer acquisition analysis'
                ];
                break;
        }

        return $roleReports;
    }

    /**
     * Generate report data
     */
    private function generateReportData($investor, $investorType, $dateRange, $includeSections)
    {
        $reportData = [];

        foreach ($includeSections as $section) {
            switch ($section) {
                case 'financial_overview':
                    $reportData['financial_overview'] = $this->getFinancialOverview($dateRange);
                    break;
                case 'operational_metrics':
                    $reportData['operational_metrics'] = $this->getOperationalMetrics($dateRange);
                    break;
                case 'growth_analytics':
                    $reportData['growth_analytics'] = $this->getGrowthAnalytics($dateRange);
                    break;
                case 'risk_assessment':
                    $reportData['risk_assessment'] = $this->getRiskAssessment();
                    break;
                case 'strategic_insights':
                    $reportData['strategic_insights'] = $this->getStrategicInsights();
                    break;
            }
        }

        return $reportData;
    }

    /**
     * Get financial overview
     */
    private function getFinancialOverview($dateRange)
    {
        return [
            'revenue' => 4850000,
            'growth_rate' => '+18%',
            'gross_margin' => '50%',
            'operating_margin' => '30%',
            'net_margin' => '30%',
            'cash_position' => 2495000,
            'equity_value' => 5850000
        ];
    }

    /**
     * Get operational metrics
     */
    private function getOperationalMetrics($dateRange)
    {
        return [
            'order_fulfillment_rate' => '98.5%',
            'customer_satisfaction' => '94.5%',
            'delivery_time' => '2.8 days',
            'automation_score' => '84%',
            'quality_control_score' => '98.9%'
        ];
    }

    /**
     * Get growth analytics
     */
    private function getGrowthAnalytics($dateRange)
    {
        return [
            'customer_acquisition' => 156,
            'revenue_growth' => '+18%',
            'market_expansion' => '2 new states',
            'product_performance' => '+23% growth'
        ];
    }

    /**
     * Get risk assessment
     */
    private function getRiskAssessment()
    {
        return [
            'market_risks' => 'Low',
            'operational_risks' => 'Medium',
            'financial_risks' => 'Low',
            'regulatory_risks' => 'Low',
            'mitigation_strategies' => 'Comprehensive risk management framework'
        ];
    }

    /**
     * Get strategic insights
     */
    private function getStrategicInsights()
    {
        return [
            'market_opportunity' => '₦2.3B Nigerian natural health market',
            'competitive_advantage' => 'Cold-press extraction technology',
            'growth_potential' => '15% CAGR market growth',
            'scaling_strategy' => 'Geographic expansion and automation'
        ];
    }

    /**
     * Get estimated file size
     */
    private function getEstimatedFileSize($format, $sectionCount)
    {
        $baseSize = 1.5; // MB
        $sectionMultiplier = 0.3; // MB per section
        $formatMultiplier = $format === 'pdf' ? 1.2 : ($format === 'excel' ? 0.8 : 1.0);
        
        return round(($baseSize + ($sectionCount * $sectionMultiplier)) * $formatMultiplier, 1) . 'MB';
    }
}

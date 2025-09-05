<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\FinancialStatement;
use App\Models\Revenue;
use Carbon\Carbon;

class TomiGovernanceController extends Controller
{
    /**
     * Get Tomi Governance dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_TOMI_GOVERNANCE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Tomi Governance access required.'
                ], 403);
            }

            $data = [
                'financial_overview' => $this->getFinancialOverview(),
                'red_flag_triggers' => $this->getRedFlagTriggers(),
                'board_governance' => $this->getBoardGovernance(),
                'monthly_digest' => $this->getMonthlyDigest(),
                'compliance_status' => $this->getComplianceStatus(),
                'recent_resolutions' => $this->getRecentResolutions()
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'investor_role' => $investor->role,
                    'access_level' => $investor->access_level
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load Tomi Governance dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial overview
     */
    private function getFinancialOverview()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $revenueMTD = Revenue::getMonthlyRevenue($currentMonth->year, $currentMonth->month);
        
        return [
            'pnl_statement' => [
                'revenue_mtd' => $revenueMTD,
                'revenue_mtd_formatted' => '₦' . number_format($revenueMTD / 1000000, 2) . 'M',
                'cogs' => $revenueMTD * 0.5,
                'cogs_formatted' => '₦' . number_format(($revenueMTD * 0.5) / 1000000, 2) . 'M',
                'operating_expenses' => $revenueMTD * 0.25,
                'operating_expenses_formatted' => '₦' . number_format(($revenueMTD * 0.25) / 1000000, 2) . 'M',
                'net_profit' => $revenueMTD * 0.25,
                'net_profit_formatted' => '₦' . number_format(($revenueMTD * 0.25) / 1000000, 2) . 'M'
            ],
            'balance_sheet' => [
                'total_assets' => 8450000,
                'total_assets_formatted' => '₦8.45M',
                'current_liabilities' => 650000,
                'current_liabilities_formatted' => '₦650K',
                'equity' => 7800000,
                'equity_formatted' => '₦7.8M',
                'debt_to_equity' => 0.08
            ],
            'cash_flow' => [
                'operating_cf' => 1180000,
                'operating_cf_formatted' => '₦1.18M',
                'investing_cf' => -320000,
                'investing_cf_formatted' => '-₦320K',
                'financing_cf' => 0,
                'financing_cf_formatted' => '₦0',
                'net_cf' => 860000,
                'net_cf_formatted' => '₦860K'
            ]
        ];
    }

    /**
     * Get red flag triggers
     */
    private function getRedFlagTriggers()
    {
        return [
            'unapproved_refund' => [
                'status' => 'active',
                'severity' => 'high',
                'description' => 'Unapproved refund request pending review',
                'amount' => 45000,
                'amount_formatted' => '₦45K',
                'days_outstanding' => 3
            ],
            'da_exposure' => [
                'status' => 'warning',
                'value' => 0,
                'description' => 'No significant DA exposure detected',
                'threshold' => 100000,
                'threshold_formatted' => '₦100K'
            ],
            'policy_compliance' => [
                'status' => 'clean',
                'description' => 'All policies compliant',
                'last_audit' => '2024-12-01',
                'next_audit' => '2025-01-01'
            ],
            'financial_discrepancies' => [
                'status' => 'monitoring',
                'description' => 'Minor discrepancies in expense reporting',
                'count' => 2,
                'severity' => 'low'
            ]
        ];
    }

    /**
     * Get board governance
     */
    private function getBoardGovernance()
    {
        return [
            'next_meeting' => 'December 15, 2024',
            'meeting_type' => 'Quarterly Board Meeting',
            'agenda_items' => [
                'Q4 Financial Review',
                '2025 Budget Approval',
                'Strategic Initiatives Update',
                'Governance Policy Review'
            ],
            'board_members' => [
                [
                    'name' => 'Tomi Governance',
                    'role' => 'Board Chair',
                    'attendance_rate' => 100
                ],
                [
                    'name' => 'Master Readiness',
                    'role' => 'Board Member',
                    'attendance_rate' => 95
                ],
                [
                    'name' => 'Otunba Control',
                    'role' => 'Board Member',
                    'attendance_rate' => 90
                ]
            ],
            'governance_score' => 92,
            'compliance_rate' => 98
        ];
    }

    /**
     * Get monthly digest
     */
    private function getMonthlyDigest()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $revenueMTD = Revenue::getMonthlyRevenue($currentMonth->year, $currentMonth->month);
        $netProfitMTD = $revenueMTD * 0.25;
        
        return [
            'net_profit_mtd' => $netProfitMTD,
            'net_profit_mtd_formatted' => '₦' . number_format($netProfitMTD / 1000000, 2) . 'M',
            'cash_flow' => 860000,
            'cash_flow_formatted' => '₦860K',
            'days_runway' => 156,
            'active_flags' => 2,
            'compliance_score' => 98,
            'governance_issues' => 0,
            'pending_approvals' => 3
        ];
    }

    /**
     * Get compliance status
     */
    private function getComplianceStatus()
    {
        return [
            'overall_compliance' => 98,
            'financial_compliance' => 100,
            'operational_compliance' => 95,
            'regulatory_compliance' => 100,
            'policy_compliance' => 98,
            'risk_management' => 92,
            'audit_status' => [
                'last_audit_date' => '2024-11-15',
                'next_audit_date' => '2025-02-15',
                'audit_score' => 95,
                'findings' => 2,
                'resolved_findings' => 1
            ]
        ];
    }

    /**
     * Get recent resolutions
     */
    private function getRecentResolutions()
    {
        return [
            [
                'title' => 'Q4 Budget Approval',
                'status' => 'passed',
                'date' => '2024-12-01',
                'votes_for' => 3,
                'votes_against' => 0,
                'abstentions' => 0
            ],
            [
                'title' => 'Marketing Investment',
                'status' => 'passed',
                'date' => '2024-11-15',
                'votes_for' => 2,
                'votes_against' => 1,
                'abstentions' => 0
            ],
            [
                'title' => 'DA Performance Policy',
                'status' => 'pending',
                'date' => '2024-12-15',
                'votes_for' => 0,
                'votes_against' => 0,
                'abstentions' => 0
            ],
            [
                'title' => 'Technology Investment',
                'status' => 'passed',
                'date' => '2024-10-30',
                'votes_for' => 3,
                'votes_against' => 0,
                'abstentions' => 0
            ]
        ];
    }

    /**
     * Get governance documents
     */
    public function getGovernanceDocuments(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_TOMI_GOVERNANCE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Tomi Governance access required.'
                ], 403);
            }

            $documents = [
                'board_resolutions' => [
                    [
                        'title' => 'Q4 Budget Approval Resolution',
                        'date' => '2024-12-01',
                        'status' => 'approved',
                        'document_url' => '/documents/board-resolutions/q4-budget-2024.pdf'
                    ],
                    [
                        'title' => 'Marketing Investment Resolution',
                        'date' => '2024-11-15',
                        'status' => 'approved',
                        'document_url' => '/documents/board-resolutions/marketing-investment-2024.pdf'
                    ]
                ],
                'governance_policies' => [
                    [
                        'title' => 'Financial Control Policy',
                        'version' => '2.1',
                        'last_updated' => '2024-11-01',
                        'status' => 'active'
                    ],
                    [
                        'title' => 'Risk Management Policy',
                        'version' => '1.5',
                        'last_updated' => '2024-10-15',
                        'status' => 'active'
                    ]
                ],
                'compliance_reports' => [
                    [
                        'title' => 'Q4 Compliance Report',
                        'period' => 'October - December 2024',
                        'status' => 'completed',
                        'score' => 98
                    ],
                    [
                        'title' => 'Annual Governance Review',
                        'period' => '2024',
                        'status' => 'in_progress',
                        'score' => null
                    ]
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $documents
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load governance documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get compliance alerts
     */
    public function getComplianceAlerts(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_TOMI_GOVERNANCE) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Tomi Governance access required.'
                ], 403);
            }

            $alerts = [
                [
                    'id' => 1,
                    'type' => 'financial_discrepancy',
                    'severity' => 'medium',
                    'title' => 'Expense Report Discrepancy',
                    'description' => 'Minor discrepancy found in November expense report',
                    'date' => '2024-12-08',
                    'status' => 'pending_review'
                ],
                [
                    'id' => 2,
                    'type' => 'policy_violation',
                    'severity' => 'low',
                    'title' => 'DA Payment Policy',
                    'description' => 'One DA payment processed outside standard policy',
                    'date' => '2024-12-07',
                    'status' => 'resolved'
                ],
                [
                    'id' => 3,
                    'type' => 'compliance_deadline',
                    'severity' => 'high',
                    'title' => 'Annual Audit Deadline',
                    'description' => 'Annual audit report due by December 31, 2024',
                    'date' => '2024-12-10',
                    'status' => 'pending'
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'alerts' => $alerts,
                    'summary' => [
                        'total_alerts' => count($alerts),
                        'high_priority' => 1,
                        'medium_priority' => 1,
                        'low_priority' => 1,
                        'resolved' => 1,
                        'pending' => 2
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load compliance alerts',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\InvestorDocument;
use App\Models\DocumentCategory;
use App\Models\FinancialStatement;
use App\Models\CompanyValuation;
use App\Models\Revenue;
use App\Models\Order;
use App\Models\Department;
use Carbon\Carbon;

class InvestorDashboardController extends Controller
{
    /**
     * Get role-specific dashboard data
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized access'
                ], 401);
            }

            $data = [
                'investor_info' => $this->getInvestorInfo($investor),
                'document_progress' => $this->getDocumentProgress($investor),
                'financial_summary' => $this->getFinancialSummary($investor),
                'performance_metrics' => $this->getPerformanceMetrics($investor),
                'recent_activity' => $this->getRecentActivity($investor),
                'access_permissions' => $this->getAccessPermissions($investor)
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
                'message' => 'Failed to load dashboard data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get document checklist
     */
    public function getDocumentChecklist(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            $categories = DocumentCategory::active()
                ->ordered()
                ->with(['documents' => function ($query) use ($investor) {
                    $query->where(function ($q) use ($investor) {
                        $q->whereNull('access_permissions')
                          ->orWhereJsonContains('access_permissions', $investor->role);
                    });
                }])
                ->get();

            $checklist = [];
            $totalDocuments = 0;
            $completedDocuments = 0;

            foreach ($categories as $category) {
                $categoryData = [
                    'id' => $category->id,
                    'name' => $category->name,
                    'description' => $category->description,
                    'icon' => $category->getIconClass(),
                    'color' => $category->getColor(),
                    'documents' => [],
                    'progress' => $category->getProgressData()
                ];

                foreach ($category->documents as $document) {
                    $categoryData['documents'][] = [
                        'id' => $document->id,
                        'title' => $document->title,
                        'status' => $document->status,
                        'completion_status' => $document->completion_status,
                        'priority' => $document->priority,
                        'due_date' => $document->due_date?->format('Y-m-d'),
                        'is_overdue' => $document->isOverdue(),
                        'can_view' => $document->canBeViewedBy($investor),
                        'can_download' => $document->canBeDownloadedBy($investor)
                    ];
                }

                $checklist[] = $categoryData;
                $totalDocuments += $categoryData['progress']['total'];
                $completedDocuments += $categoryData['progress']['completed'];
            }

            $overallProgress = $totalDocuments > 0 ? round(($completedDocuments / $totalDocuments) * 100, 1) : 0;

            return response()->json([
                'success' => true,
                'data' => [
                    'checklist' => $checklist,
                    'summary' => [
                        'total_documents' => $totalDocuments,
                        'completed_documents' => $completedDocuments,
                        'in_progress_documents' => $this->getInProgressCount($categories),
                        'not_ready_documents' => $this->getNotReadyCount($categories),
                        'overall_progress' => $overallProgress
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load document checklist',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial statements
     */
    public function getFinancialStatements(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor->canAccessFinancials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Insufficient permissions.'
                ], 403);
            }

            $statements = FinancialStatement::published()
                ->latest()
                ->limit(12)
                ->get()
                ->map(function ($statement) {
                    return [
                        'id' => $statement->id,
                        'type' => $statement->type,
                        'type_display' => $statement->getTypeDisplayName(),
                        'period_name' => $statement->period_name,
                        'period_start' => $statement->period_start->format('Y-m-d'),
                        'period_end' => $statement->period_end->format('Y-m-d'),
                        'summary' => $statement->getSummaryData(),
                        'published_at' => $statement->published_at?->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $statements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load financial statements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get company valuations
     */
    public function getCompanyValuations(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor->canAccessFinancials()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Insufficient permissions.'
                ], 403);
            }

            $valuations = CompanyValuation::published()
                ->latest()
                ->limit(10)
                ->get()
                ->map(function ($valuation) {
                    return [
                        'id' => $valuation->id,
                        'valuation_date' => $valuation->valuation_date->format('Y-m-d'),
                        'summary' => $valuation->getSummaryData(),
                        'equity_distribution' => $valuation->getEquityDistributionSummary(),
                        'valuation_methods' => $valuation->getValuationMethodsSummary(),
                        'published_at' => $valuation->published_at?->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => $valuations
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load company valuations',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get React-optimized initial load data for specific role
     */
    public function getReactInitialLoadData(Request $request, string $role): JsonResponse
    {
        try {
            $investor = $request->user();

            if (!$investor instanceof Investor || $investor->role !== $role) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Role mismatch.'
                ], 403);
            }

            // Get role-specific dashboard data
            $dashboardData = $this->getRoleSpecificDashboardData($role);
            
            // Get WebSocket channels for this role
            $websocketChannels = [
                "investor-dashboard-{$role}",
                'document-updates',
                'financial-metrics',
                'operational-alerts'
            ];

            // Get permissions for this role
            $permissions = $this->getRolePermissions($role);

            $data = [
                'dashboard_data' => $dashboardData,
                'meta' => [
                    'last_updated' => now()->toISOString(),
                    'websocket_channels' => $websocketChannels,
                    'permissions' => $permissions,
                    'role' => $role,
                    'user_id' => $investor->id
                ]
            ];

            return response()->json([
                'success' => true,
                'data' => $data
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load initial data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods
    private function getInvestorInfo($investor)
    {
        return [
            'name' => $investor->name,
            'role' => $investor->role,
            'role_display' => $investor->getRoleDisplayName(),
            'access_level' => $investor->access_level,
            'access_level_display' => $investor->getAccessLevelDisplayName(),
            'company_name' => $investor->company_name,
            'position' => $investor->position,
            'last_login' => $investor->last_login_at?->format('M j, Y g:i A'),
            'profile_image' => $investor->profile_image
        ];
    }

    private function getDocumentProgress($investor)
    {
        $categories = DocumentCategory::active()
            ->with(['documents' => function ($query) use ($investor) {
                $query->where(function ($q) use ($investor) {
                    $q->whereNull('access_permissions')
                      ->orWhereJsonContains('access_permissions', $investor->role);
                });
            }])
            ->get();

        $totalDocuments = 0;
        $completedDocuments = 0;
        $inProgressDocuments = 0;
        $notReadyDocuments = 0;

        foreach ($categories as $category) {
            $totalDocuments += $category->getDocumentCount();
            $completedDocuments += $category->getCompletedDocumentCount();
            $inProgressDocuments += $category->getInProgressDocumentCount();
            $notReadyDocuments += $category->getNotReadyDocumentCount();
        }

        return [
            'total' => $totalDocuments,
            'completed' => $completedDocuments,
            'in_progress' => $inProgressDocuments,
            'not_ready' => $notReadyDocuments,
            'overall_progress' => $totalDocuments > 0 ? round(($completedDocuments / $totalDocuments) * 100, 1) : 0
        ];
    }

    private function getFinancialSummary($investor)
    {
        if (!$investor->canAccessFinancials()) {
            return null;
        }

        $latestStatement = FinancialStatement::published()
            ->latest()
            ->first();

        if (!$latestStatement) {
            return null;
        }

        return [
            'revenue' => $latestStatement->getFormattedAmount($latestStatement->revenue),
            'net_income' => $latestStatement->getFormattedAmount($latestStatement->net_income),
            'gross_profit_margin' => $latestStatement->getGrossProfitMargin() . '%',
            'operating_margin' => $latestStatement->getOperatingMargin() . '%',
            'net_profit_margin' => $latestStatement->getNetProfitMargin() . '%',
            'period' => $latestStatement->period_name
        ];
    }

    private function getPerformanceMetrics($investor)
    {
        $metrics = [];

        // Revenue metrics
        if ($investor->canAccessFinancials()) {
            $currentMonth = Carbon::now()->startOfMonth();
            $revenueMTD = Revenue::getMonthlyRevenue($currentMonth->year, $currentMonth->month);
            
            $metrics['revenue'] = [
                'current' => '₦' . number_format($revenueMTD / 1000000, 1) . 'M',
                'period' => 'MTD'
            ];
        }

        // Order metrics
        if ($investor->canAccessOperations()) {
            $today = Carbon::today();
            $ordersToday = Order::whereDate('created_at', $today)->count();
            $monthlyOrders = Order::whereMonth('created_at', $currentMonth->month)
                ->whereYear('created_at', $currentMonth->year)
                ->count();

            $metrics['orders'] = [
                'today' => $ordersToday,
                'monthly' => $monthlyOrders
            ];
        }

        // Growth metrics
        if ($investor->canAccessGrowthMetrics()) {
            $lastMonth = Carbon::now()->subMonth()->startOfMonth();
            $revenueLastMonth = Revenue::getMonthlyRevenue($lastMonth->year, $lastMonth->month);
            $revenueMTD = Revenue::getMonthlyRevenue($currentMonth->year, $currentMonth->month);
            
            $growthRate = $revenueLastMonth > 0 ? 
                (($revenueMTD - $revenueLastMonth) / $revenueLastMonth) * 100 : 0;

            $metrics['growth'] = [
                'rate' => round($growthRate, 1) . '%',
                'trend' => $growthRate >= 0 ? 'up' : 'down'
            ];
        }

        return $metrics;
    }

    private function getRecentActivity($investor)
    {
        $activity = [];

        // Recent document updates
        $recentDocuments = InvestorDocument::where(function ($query) use ($investor) {
            $query->whereNull('access_permissions')
                  ->orWhereJsonContains('access_permissions', $investor->role);
        })
        ->where('updated_at', '>=', Carbon::now()->subDays(7))
        ->orderBy('updated_at', 'desc')
        ->limit(5)
        ->get();

        foreach ($recentDocuments as $document) {
            $activity[] = [
                'type' => 'document_update',
                'title' => $document->title,
                'status' => $document->getStatusDisplayName(),
                'timestamp' => $document->updated_at->format('M j, Y g:i A')
            ];
        }

        // Recent financial statements
        if ($investor->canAccessFinancials()) {
            $recentStatements = FinancialStatement::published()
                ->where('published_at', '>=', Carbon::now()->subDays(30))
                ->orderBy('published_at', 'desc')
                ->limit(3)
                ->get();

            foreach ($recentStatements as $statement) {
                $activity[] = [
                    'type' => 'financial_statement',
                    'title' => $statement->getTypeDisplayName() . ' - ' . $statement->period_name,
                    'status' => 'Published',
                    'timestamp' => $statement->published_at->format('M j, Y g:i A')
                ];
            }
        }

        return $activity;
    }

    private function getAccessPermissions($investor)
    {
        return [
            'financials' => $investor->canAccessFinancials(),
            'operations' => $investor->canAccessOperations(),
            'governance' => $investor->canAccessGovernance(),
            'strategy' => $investor->canAccessStrategy(),
            'tech_metrics' => $investor->canAccessTechMetrics(),
            'growth_metrics' => $investor->canAccessGrowthMetrics()
        ];
    }

    private function getRoleSpecificDashboardData(string $role): array
    {
        switch ($role) {
            case 'master_readiness':
                return [
                    'document_readiness' => [
                        'total_documents' => 30,
                        'completed_documents' => 28,
                        'in_progress_documents' => 2,
                        'missing_documents' => 0,
                        'completion_percentage' => 93.3
                    ],
                    'recent_updates' => [
                        ['document' => 'P&L Statement', 'status' => 'updated', 'time' => '2 hours ago'],
                        ['document' => 'Cash Flow Statement', 'status' => 'in_progress', 'time' => '4 hours ago'],
                        ['document' => 'Balance Sheet', 'status' => 'completed', 'time' => '1 day ago']
                    ],
                    'export_options' => [
                        'pitch_deck' => true,
                        'financial_package' => true,
                        'full_package' => true,
                        'monthly_update' => true
                    ]
                ];

            case 'tomi_governance':
                return [
                    'financial_oversight' => [
                        'cash_position' => 2495000,
                        'monthly_burn_rate' => 500000,
                        'runway_days' => 150,
                        'compliance_score' => 95
                    ],
                    'governance_metrics' => [
                        'board_meetings' => 4,
                        'compliance_reports' => 12,
                        'audit_status' => 'clean',
                        'risk_score' => 'low'
                    ]
                ];

            case 'ron_scale':
                return [
                    'scaling_metrics' => [
                        'growth_rate' => 18.5,
                        'market_expansion' => 2,
                        'operational_efficiency' => 92,
                        'scaling_readiness' => 85
                    ],
                    'expansion_plans' => [
                        'new_markets' => ['Lagos', 'Port Harcourt'],
                        'product_lines' => 3,
                        'team_growth' => 25
                    ]
                ];

            case 'thiel_strategy':
                return [
                    'strategic_metrics' => [
                        'market_position' => 'leader',
                        'competitive_moat' => 'strong',
                        'long_term_vision' => 'clear',
                        'strategic_alignment' => 90
                    ],
                    'vision_components' => [
                        'market_dominance' => 'achievable',
                        'barriers_to_entry' => 'high',
                        'scalability' => 'excellent'
                    ]
                ];

            case 'andy_tech':
                return [
                    'technical_metrics' => [
                        'automation_score' => 84,
                        'system_uptime' => 99.8,
                        'process_efficiency' => 92,
                        'innovation_index' => 78
                    ],
                    'tech_stack' => [
                        'frontend' => 'React/Vue.js',
                        'backend' => 'Laravel/PHP',
                        'database' => 'MySQL/Redis',
                        'infrastructure' => 'AWS/Docker'
                    ]
                ];

            case 'otunba_control':
                return [
                    'financial_controls' => [
                        'cash_position' => 2495000,
                        'daily_cash_flow' => 75000,
                        'outstanding_balances' => 0,
                        'financial_health' => 'excellent'
                    ],
                    'control_metrics' => [
                        'cost_variance' => 8.2,
                        'budget_adherence' => 95.8,
                        'financial_transparency' => 100
                    ]
                ];

            case 'dangote_cost_control':
                return [
                    'cost_metrics' => [
                        'unit_economics' => 'profitable',
                        'cost_efficiency' => 87,
                        'wastage_rate' => 2.3,
                        'optimization_opportunities' => 3
                    ],
                    'efficiency_metrics' => [
                        'process_automation' => 84,
                        'resource_utilization' => 92,
                        'cost_per_unit' => 1250
                    ]
                ];

            case 'neil_growth':
                return [
                    'growth_metrics' => [
                        'neil_score' => 8.7,
                        'customer_acquisition' => 120,
                        'revenue_growth' => 18.5,
                        'marketing_efficiency' => 85
                    ],
                    'growth_channels' => [
                        'paid_ads' => ['roas' => 3.5, 'cac' => 1500],
                        'organic' => ['growth' => 25, 'cost' => 0],
                        'referrals' => ['rate' => 15, 'ltv' => 5250]
                    ]
                ];

            default:
                return [
                    'general_metrics' => [
                        'overall_performance' => 'good',
                        'completion_rate' => 85,
                        'last_updated' => now()->toISOString()
                    ]
                ];
        }
    }

    /**
     * Get role-specific permissions
     */
    private function getRolePermissions(string $role): array
    {
        $basePermissions = [
            'can_view_dashboard' => true,
            'can_view_documents' => true,
            'can_download_documents' => true,
            'can_view_financials' => false,
            'can_export_reports' => false,
            'can_upload_documents' => false,
            'can_manage_users' => false
        ];

        switch ($role) {
            case 'master_readiness':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true,
                    'can_upload_documents' => true,
                    'can_manage_users' => true
                ]);

            case 'tomi_governance':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true,
                    'can_upload_documents' => true
                ]);

            case 'ron_scale':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true
                ]);

            case 'thiel_strategy':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true
                ]);

            case 'andy_tech':
                return array_merge($basePermissions, [
                    'can_export_reports' => true,
                    'can_upload_documents' => true
                ]);

            case 'otunba_control':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true
                ]);

            case 'dangote_cost_control':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true
                ]);

            case 'neil_growth':
                return array_merge($basePermissions, [
                    'can_view_financials' => true,
                    'can_export_reports' => true
                ]);

            default:
                return $basePermissions;
        }
    }

    private function getInProgressCount($categories)
    {
        $count = 0;
        foreach ($categories as $category) {
            $count += $category->getInProgressDocumentCount();
        }
        return $count;
    }

    private function getNotReadyCount($categories)
    {
        $count = 0;
        foreach ($categories as $category) {
            $count += $category->getNotReadyDocumentCount();
        }
        return $count;
    }
}

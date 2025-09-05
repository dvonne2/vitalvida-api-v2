<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Investor;
use App\Models\InvestorDocument;
use App\Models\DocumentCategory;
use App\Models\FinancialStatement;
use App\Models\Revenue;
use App\Models\Order;
use Carbon\Carbon;

class MasterReadinessController extends Controller
{
    /**
     * Get Master Readiness dashboard
     */
    public function getDashboard(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_MASTER_READINESS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Master Readiness access required.'
                ], 403);
            }

            $data = [
                'readiness_summary' => $this->getReadinessSummary(),
                'document_categories' => $this->getDocumentCategories(),
                'print_export_options' => $this->getExportOptions(),
                'financial_overview' => $this->getFinancialOverview(),
                'performance_metrics' => $this->getPerformanceMetrics(),
                'recent_activity' => $this->getRecentActivity()
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
                'message' => 'Failed to load Master Readiness dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get readiness summary
     */
    private function getReadinessSummary()
    {
        $totalDocuments = 30;
        $completeDocuments = 23;
        $inProgressDocuments = 5;
        $notReadyDocuments = 2;
        $completionPercentage = round(($completeDocuments / $totalDocuments) * 100);

        return [
            'total_documents' => $totalDocuments,
            'complete' => $completeDocuments,
            'in_progress' => $inProgressDocuments,
            'not_ready' => $notReadyDocuments,
            'completion_percentage' => $completionPercentage
        ];
    }

    /**
     * Get document categories with status
     */
    private function getDocumentCategories()
    {
        return [
            'financials' => [
                'total' => 7,
                'complete' => 6,
                'status' => 'mostly_complete',
                'description' => 'Financial statements, P&L, balance sheets'
            ],
            'operations_systems' => [
                'total' => 5,
                'complete' => 4,
                'status' => 'in_progress',
                'description' => 'Operational processes and systems'
            ],
            'governance_legal' => [
                'total' => 6,
                'complete' => 6,
                'status' => 'complete',
                'description' => 'Board governance and legal compliance'
            ],
            'vision_strategy' => [
                'total' => 6,
                'complete' => 4,
                'status' => 'in_progress',
                'description' => 'Strategic vision and market analysis'
            ],
            'owner_oversight' => [
                'total' => 6,
                'complete' => 3,
                'status' => 'needs_attention',
                'description' => 'Owner oversight and control mechanisms'
            ]
        ];
    }

    /**
     * Get export options
     */
    private function getExportOptions()
    {
        return [
            'full_folder' => true,
            'zip_download' => true,
            'individual_documents' => true,
            'print_checklist' => true,
            'pdf_summary' => true
        ];
    }

    /**
     * Get financial overview
     */
    private function getFinancialOverview()
    {
        $currentMonth = Carbon::now()->startOfMonth();
        $revenueMTD = Revenue::getMonthlyRevenue($currentMonth->year, $currentMonth->month);
        
        return [
            'revenue_mtd' => $revenueMTD,
            'revenue_mtd_formatted' => '₦' . number_format($revenueMTD / 1000000, 2) . 'M',
            'net_profit_mtd' => $revenueMTD * 0.25,
            'net_profit_mtd_formatted' => '₦' . number_format(($revenueMTD * 0.25) / 1000000, 2) . 'M',
            'cash_balance' => 2495000,
            'cash_balance_formatted' => '₦2.495M',
            'days_runway' => 156,
            'completion_percentage' => 77
        ];
    }

    /**
     * Get performance metrics
     */
    private function getPerformanceMetrics()
    {
        $today = Carbon::today();
        $ordersToday = Order::whereDate('created_at', $today)->count();
        
        return [
            'orders_today' => $ordersToday,
            'revenue_today' => 665000,
            'revenue_today_formatted' => '₦665K',
            'active_das' => 47,
            'customer_satisfaction' => 94.5,
            'delivery_success_rate' => 98.1,
            'inventory_turnover' => 3.2
        ];
    }

    /**
     * Get recent activity
     */
    private function getRecentActivity()
    {
        return [
            [
                'type' => 'document_completed',
                'title' => 'P&L Statement (Last 12 Months)',
                'category' => 'Financials',
                'timestamp' => now()->subHours(2)->format('M j, Y g:i A')
            ],
            [
                'type' => 'document_updated',
                'title' => 'Workflow Map (Zoho-Moniepoint)',
                'category' => 'Operations',
                'timestamp' => now()->subHours(4)->format('M j, Y g:i A')
            ],
            [
                'type' => 'financial_report',
                'title' => 'Monthly Financial Summary',
                'category' => 'Financials',
                'timestamp' => now()->subHours(6)->format('M j, Y g:i A')
            ],
            [
                'type' => 'governance_update',
                'title' => 'Board Resolution - Q4 Budget',
                'category' => 'Governance',
                'timestamp' => now()->subHours(8)->format('M j, Y g:i A')
            ]
        ];
    }

    /**
     * Get document checklist
     */
    public function getDocumentChecklist(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_MASTER_READINESS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Master Readiness access required.'
                ], 403);
            }

            $categories = DocumentCategory::active()
                ->ordered()
                ->with(['documents' => function ($query) {
                    $query->orderBy('priority', 'desc')
                          ->orderBy('due_date', 'asc');
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
                        'progress_percentage' => $document->getProgressPercentage()
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
     * Export documents
     */
    public function exportDocuments(Request $request): JsonResponse
    {
        try {
            $investor = $request->user();
            
            if (!$investor instanceof Investor || $investor->role !== Investor::ROLE_MASTER_READINESS) {
                return response()->json([
                    'success' => false,
                    'message' => 'Access denied. Master Readiness access required.'
                ], 403);
            }

            $exportType = $request->get('type', 'zip');
            $categoryId = $request->get('category_id');

            $documents = InvestorDocument::with('category')
                ->where(function ($query) use ($investor) {
                    $query->whereNull('access_permissions')
                          ->orWhereJsonContains('access_permissions', $investor->role);
                });

            if ($categoryId) {
                $documents->where('category_id', $categoryId);
            }

            $documents = $documents->get();

            $exportData = [
                'export_type' => $exportType,
                'total_documents' => $documents->count(),
                'download_url' => $this->generateExportUrl($documents, $exportType),
                'expires_at' => now()->addHours(24)->toISOString(),
                'documents' => $documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'title' => $document->title,
                        'category' => $document->category->name,
                        'status' => $document->status,
                        'file_size' => $document->getFileSizeFormatted()
                    ];
                })
            ];

            return response()->json([
                'success' => true,
                'data' => $exportData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Private helper methods
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

    private function generateExportUrl($documents, $exportType)
    {
        // This would generate a temporary download URL
        // For now, return a placeholder
        return '/api/investor/master/export/' . $exportType . '/' . time();
    }
}

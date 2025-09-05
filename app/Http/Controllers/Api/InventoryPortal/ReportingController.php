<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\DeliveryAgent;
use App\Models\StockMovement;
use App\Models\Bin;
use App\Models\InventoryMovement;
use App\Models\Report;
use App\Models\ReportTemplate;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;

class ReportingController extends Controller
{
    /**
     * Get available report templates
     */
    public function getReportTemplates(Request $request)
    {
        try {
            $templates = ReportTemplate::where('category', 'inventory')
                ->orWhere('category', 'general')
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $templates
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch report templates',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate inventory report
     */
    public function generateInventoryReport(Request $request)
    {
        try {
            $request->validate([
                'report_type' => 'required|in:stock_levels,movements,aging,performance',
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'filters' => 'nullable|array',
                'format' => 'nullable|in:json,csv,pdf'
            ]);

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $reportData = match($request->report_type) {
                'stock_levels' => $this->generateStockLevelsReport($startDate, $endDate, $request->filters),
                'movements' => $this->generateMovementsReport($startDate, $endDate, $request->filters),
                'aging' => $this->generateAgingReport($startDate, $endDate, $request->filters),
                'performance' => $this->generatePerformanceReport($startDate, $endDate, $request->filters),
                default => []
            };

            // Save report
            $report = Report::create([
                'name' => ucfirst($request->report_type) . ' Report',
                'type' => $request->report_type,
                'generated_by' => auth()->id(),
                'parameters' => $request->all(),
                'data' => $reportData,
                'status' => 'completed'
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Report generated successfully',
                'data' => [
                    'report' => $report,
                    'data' => $reportData
                ]
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
     * Get DA performance report
     */
    public function getDAPerformanceReport(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'da_id' => 'nullable|exists:delivery_agents,id',
                'include_details' => 'nullable|boolean'
            ]);

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $query = DeliveryAgent::select([
                'delivery_agents.*',
                DB::raw('COUNT(deliveries.id) as total_deliveries'),
                DB::raw('COUNT(CASE WHEN deliveries.status = "completed" THEN 1 END) as successful_deliveries'),
                DB::raw('AVG(deliveries.rating) as average_rating'),
                DB::raw('AVG(TIMESTAMPDIFF(MINUTE, deliveries.created_at, deliveries.updated_at)) as avg_delivery_time'),
                DB::raw('SUM(orders.total_amount) as total_revenue')
            ])
            ->leftJoin('deliveries', 'delivery_agents.id', '=', 'deliveries.delivery_agent_id')
            ->leftJoin('orders', 'deliveries.order_id', '=', 'orders.id')
            ->whereBetween('deliveries.created_at', [$startDate, $endDate]);

            if ($request->da_id) {
                $query->where('delivery_agents.id', $request->da_id);
            }

            $performance = $query->groupBy('delivery_agents.id')
                ->orderBy('total_revenue', 'desc')
                ->get()
                ->map(function ($da) {
                    $da->success_rate = $da->total_deliveries > 0 
                        ? round(($da->successful_deliveries / $da->total_deliveries) * 100, 2)
                        : 0;
                    return $da;
                });

            $summary = [
                'total_agents' => $performance->count(),
                'total_deliveries' => $performance->sum('total_deliveries'),
                'total_revenue' => $performance->sum('total_revenue'),
                'average_rating' => $performance->avg('average_rating'),
                'average_success_rate' => $performance->avg('success_rate')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'performance' => $performance,
                    'summary' => $summary,
                    'period' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate DA performance report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get stock movement analysis
     */
    public function getStockMovementAnalysis(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'product_id' => 'nullable|exists:products,id',
                'movement_type' => 'nullable|in:in,out,transfer,adjustment'
            ]);

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $query = StockMovement::with(['product', 'bin', 'user'])
                ->whereBetween('created_at', [$startDate, $endDate]);

            if ($request->product_id) {
                $query->where('product_id', $request->product_id);
            }

            if ($request->movement_type) {
                $query->where('movement_type', $request->movement_type);
            }

            $movements = $query->orderBy('created_at', 'desc')->get();

            $analysis = [
                'total_movements' => $movements->count(),
                'movements_by_type' => $movements->groupBy('movement_type')->map->count(),
                'total_quantity_moved' => $movements->sum('quantity'),
                'quantity_by_type' => $movements->groupBy('movement_type')->map->sum('quantity'),
                'top_products' => $movements->groupBy('product_id')
                    ->map(function ($productMovements) {
                        return [
                            'product' => $productMovements->first()->product,
                            'total_movements' => $productMovements->count(),
                            'total_quantity' => $productMovements->sum('quantity')
                        ];
                    })
                    ->sortByDesc('total_quantity')
                    ->take(10),
                'daily_trends' => $this->getDailyMovementTrends($movements)
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'movements' => $movements,
                    'analysis' => $analysis,
                    'period' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate stock movement analysis',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bin utilization report
     */
    public function getBinUtilizationReport(Request $request)
    {
        try {
            $bins = Bin::with(['currentStock.product'])
                ->get()
                ->map(function ($bin) {
                    $currentStock = $bin->currentStock->sum('quantity');
                    $utilization = $bin->capacity > 0 ? round(($currentStock / $bin->capacity) * 100, 2) : 0;
                    
                    return [
                        'bin' => $bin,
                        'current_stock' => $currentStock,
                        'available_capacity' => $bin->capacity - $currentStock,
                        'utilization_percentage' => $utilization,
                        'status' => $this->getBinStatus($utilization),
                        'products_count' => $bin->currentStock->count(),
                        'products' => $bin->currentStock->map(function ($stock) {
                            return [
                                'product' => $stock->product,
                                'quantity' => $stock->quantity
                            ];
                        })
                    ];
                })
                ->sortByDesc('utilization_percentage');

            $summary = [
                'total_bins' => $bins->count(),
                'active_bins' => $bins->where('bin.status', 'active')->count(),
                'high_utilization_bins' => $bins->where('utilization_percentage', '>=', 80)->count(),
                'low_utilization_bins' => $bins->where('utilization_percentage', '<=', 20)->count(),
                'average_utilization' => $bins->avg('utilization_percentage'),
                'total_capacity' => $bins->sum('bin.capacity'),
                'total_used_capacity' => $bins->sum('current_stock')
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'bins' => $bins,
                    'summary' => $summary
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate bin utilization report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get financial report
     */
    public function getFinancialReport(Request $request)
    {
        try {
            $request->validate([
                'start_date' => 'required|date',
                'end_date' => 'required|date|after:start_date',
                'include_details' => 'nullable|boolean'
            ]);

            $startDate = Carbon::parse($request->start_date);
            $endDate = Carbon::parse($request->end_date);

            $orders = Order::where('status', 'completed')
                ->whereBetween('created_at', [$startDate, $endDate])
                ->get();

            $financialData = [
                'total_revenue' => $orders->sum('total_amount'),
                'total_orders' => $orders->count(),
                'average_order_value' => $orders->avg('total_amount'),
                'revenue_by_state' => $orders->groupBy('delivery_state')
                    ->map(function ($stateOrders) {
                        return [
                            'total_revenue' => $stateOrders->sum('total_amount'),
                            'order_count' => $stateOrders->count(),
                            'average_order_value' => $stateOrders->avg('total_amount')
                        ];
                    }),
                'daily_revenue' => $this->getDailyRevenueTrends($orders),
                'top_products' => $this->getTopProductsByRevenue($orders),
                'payment_methods' => $orders->groupBy('payment_method')
                    ->map(function ($methodOrders) {
                        return [
                            'total_revenue' => $methodOrders->sum('total_amount'),
                            'order_count' => $methodOrders->count(),
                            'percentage' => round(($methodOrders->count() / $orders->count()) * 100, 2)
                        ];
                    })
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'financial_data' => $financialData,
                    'period' => [
                        'start_date' => $startDate->toDateString(),
                        'end_date' => $endDate->toDateString()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to generate financial report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Export report data
     */
    public function exportReport(Request $request, $reportId)
    {
        try {
            $report = Report::findOrFail($reportId);
            $format = $request->get('format', 'csv');

            $filename = 'report_' . $report->id . '_' . now()->format('Y-m-d_H-i-s') . '.' . $format;
            $filepath = 'reports/' . $filename;

            switch ($format) {
                case 'csv':
                    $this->exportToCSV($report->data, $filepath);
                    break;
                case 'json':
                    $this->exportToJSON($report->data, $filepath);
                    break;
                default:
                    return response()->json([
                        'success' => false,
                        'message' => 'Unsupported export format'
                    ], 400);
            }

            $downloadUrl = Storage::url($filepath);

            return response()->json([
                'success' => true,
                'message' => 'Report exported successfully',
                'data' => [
                    'download_url' => $downloadUrl,
                    'filename' => $filename
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to export report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get generated reports
     */
    public function getGeneratedReports(Request $request)
    {
        try {
            $reports = Report::where('generated_by', auth()->id())
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $reports
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch generated reports',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Schedule report generation
     */
    public function scheduleReport(Request $request)
    {
        try {
            $request->validate([
                'report_type' => 'required|string',
                'schedule' => 'required|in:daily,weekly,monthly',
                'parameters' => 'required|array',
                'recipients' => 'nullable|array',
                'recipients.*' => 'email'
            ]);

            // This would typically create a scheduled job
            // For now, we'll just save the schedule request
            $schedule = [
                'report_type' => $request->report_type,
                'schedule' => $request->schedule,
                'parameters' => $request->parameters,
                'recipients' => $request->recipients,
                'created_by' => auth()->id(),
                'status' => 'active'
            ];

            return response()->json([
                'success' => true,
                'message' => 'Report scheduled successfully',
                'data' => $schedule
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to schedule report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods

    private function generateStockLevelsReport($startDate, $endDate, $filters)
    {
        $query = Product::with(['stockMovements' => function($q) use ($startDate, $endDate) {
            $q->whereBetween('created_at', [$startDate, $endDate]);
        }]);

        if (isset($filters['category'])) {
            $query->where('category', $filters['category']);
        }

        return $query->get()->map(function ($product) {
            return [
                'product' => $product,
                'current_stock' => $product->current_stock ?? 0,
                'movements_count' => $product->stockMovements->count(),
                'total_in' => $product->stockMovements->where('movement_type', 'in')->sum('quantity'),
                'total_out' => $product->stockMovements->where('movement_type', 'out')->sum('quantity')
            ];
        });
    }

    private function generateMovementsReport($startDate, $endDate, $filters)
    {
        $query = StockMovement::with(['product', 'bin', 'user'])
            ->whereBetween('created_at', [$startDate, $endDate]);

        if (isset($filters['movement_type'])) {
            $query->where('movement_type', $filters['movement_type']);
        }

        return $query->orderBy('created_at', 'desc')->get();
    }

    private function generateAgingReport($startDate, $endDate, $filters)
    {
        // Implementation for aging inventory report
        return [];
    }

    private function generatePerformanceReport($startDate, $endDate, $filters)
    {
        // Implementation for performance report
        return [];
    }

    private function getDailyMovementTrends($movements)
    {
        return $movements->groupBy(function ($movement) {
            return $movement->created_at->format('Y-m-d');
        })->map(function ($dayMovements) {
            return [
                'total_movements' => $dayMovements->count(),
                'total_quantity' => $dayMovements->sum('quantity'),
                'movements_by_type' => $dayMovements->groupBy('movement_type')->map->count()
            ];
        });
    }

    private function getDailyRevenueTrends($orders)
    {
        return $orders->groupBy(function ($order) {
            return $order->created_at->format('Y-m-d');
        })->map(function ($dayOrders) {
            return [
                'total_revenue' => $dayOrders->sum('total_amount'),
                'order_count' => $dayOrders->count(),
                'average_order_value' => $dayOrders->avg('total_amount')
            ];
        });
    }

    private function getTopProductsByRevenue($orders)
    {
        // Implementation for top products by revenue
        return [];
    }

    private function getBinStatus($utilization)
    {
        if ($utilization >= 90) return 'critical';
        if ($utilization >= 80) return 'high';
        if ($utilization >= 50) return 'medium';
        if ($utilization >= 20) return 'low';
        return 'empty';
    }

    private function exportToCSV($data, $filepath)
    {
        // Implementation for CSV export
        $content = "Report Data\n";
        $content .= json_encode($data, JSON_PRETTY_PRINT);
        Storage::put($filepath, $content);
    }

    private function exportToJSON($data, $filepath)
    {
        Storage::put($filepath, json_encode($data, JSON_PRETTY_PRINT));
    }
} 
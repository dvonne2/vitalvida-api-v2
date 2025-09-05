<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Item;
use App\Models\Category;
use App\Models\Employee;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SalesReportController extends Controller
{
    /**
     * Get sales summary.
     */
    public function salesSummary(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $query = Sale::whereBetween('date', [$startDate, $endDate]);

        // Apply filters
        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        $summary = [
            'total_sales' => $query->sum('total'),
            'total_transactions' => $query->count(),
            'average_order_value' => $query->avg('total'),
            'total_items_sold' => $query->with('items')->get()->sum('total_quantity'),
            'unique_customers' => $query->distinct('customer_id')->count(),
            'verified_sales' => $query->where('otp_verified', true)->count(),
            'unverified_sales' => $query->where('otp_verified', false)->count(),
            'payment_methods' => $query->selectRaw('payment_method, COUNT(*) as count, SUM(total) as total')
                ->groupBy('payment_method')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'data' => $summary,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get sales by item.
     */
    public function salesByItem(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $sales = SaleItem::with(['item.category', 'sale'])
            ->whereHas('sale', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->selectRaw('
                item_id,
                SUM(quantity) as total_quantity,
                SUM(total) as total_revenue,
                AVG(unit_price) as avg_unit_price,
                COUNT(*) as transaction_count
            ')
            ->groupBy('item_id')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'item_id' => $item->item_id,
                    'item_name' => $item->item->name,
                    'category' => $item->item->category->name ?? 'Uncategorized',
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => $item->total_revenue,
                    'avg_unit_price' => $item->avg_unit_price,
                    'transaction_count' => $item->transaction_count,
                    'profit_margin' => $item->item->margin_percentage ?? 0
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sales,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get sales by category.
     */
    public function salesByCategory(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $sales = SaleItem::with(['item.category', 'sale'])
            ->whereHas('sale', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->selectRaw('
                categories.name as category_name,
                SUM(sale_items.quantity) as total_quantity,
                SUM(sale_items.total) as total_revenue,
                COUNT(DISTINCT sales.id) as transaction_count,
                COUNT(DISTINCT sales.customer_id) as unique_customers
            ')
            ->join('items', 'sale_items.item_id', '=', 'items.id')
            ->join('categories', 'items.category_id', '=', 'categories.id')
            ->join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get sales by employee.
     */
    public function salesByEmployee(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $sales = Sale::with(['employee', 'deliveryAgent'])
            ->whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                employee_id,
                delivery_agent_id,
                COUNT(*) as transaction_count,
                SUM(total) as total_revenue,
                AVG(total) as avg_order_value,
                COUNT(DISTINCT customer_id) as unique_customers
            ')
            ->groupBy('employee_id', 'delivery_agent_id')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function ($sale) {
                return [
                    'employee_id' => $sale->employee_id,
                    'employee_name' => $sale->employee->name ?? 'Unknown',
                    'delivery_agent_id' => $sale->delivery_agent_id,
                    'delivery_agent_name' => $sale->deliveryAgent->name ?? 'Unknown',
                    'transaction_count' => $sale->transaction_count,
                    'total_revenue' => $sale->total_revenue,
                    'avg_order_value' => $sale->avg_order_value,
                    'unique_customers' => $sale->unique_customers
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sales,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get sales by payment type.
     */
    public function salesByPaymentType(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $sales = Sale::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                payment_method,
                COUNT(*) as transaction_count,
                SUM(total) as total_revenue,
                AVG(total) as avg_order_value,
                COUNT(CASE WHEN otp_verified = 1 THEN 1 END) as verified_count
            ')
            ->groupBy('payment_method')
            ->orderBy('total_revenue', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $sales,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get sales by modifier.
     */
    public function salesByModifier(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $sales = SaleItem::with(['modifier', 'sale'])
            ->whereHas('sale', function ($query) use ($startDate, $endDate) {
                $query->whereBetween('date', [$startDate, $endDate]);
            })
            ->whereNotNull('modifier_id')
            ->selectRaw('
                modifier_id,
                SUM(quantity) as total_quantity,
                SUM(total) as total_revenue,
                COUNT(*) as usage_count
            ')
            ->groupBy('modifier_id')
            ->orderBy('total_revenue', 'desc')
            ->get()
            ->map(function ($item) {
                return [
                    'modifier_id' => $item->modifier_id,
                    'modifier_name' => $item->modifier->name ?? 'Unknown',
                    'modifier_type' => $item->modifier->type ?? 'Unknown',
                    'total_quantity' => $item->total_quantity,
                    'total_revenue' => $item->total_revenue,
                    'usage_count' => $item->usage_count
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $sales,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Export sales report.
     */
    public function export(Request $request): JsonResponse
    {
        $reportType = $request->get('report_type', 'summary');
        $format = $request->get('format', 'csv');

        // This would implement actual export logic
        $exportData = [
            'report_type' => $reportType,
            'format' => $format,
            'download_url' => '/api/reports/sales/export/download/' . uniqid(),
            'expires_at' => now()->addHours(24)
        ];

        return response()->json([
            'success' => true,
            'data' => $exportData
        ]);
    }
} 
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\Item;
use App\Models\PurchaseOrder;
use App\Models\StockAdjustment;
use App\Models\PerformanceMetric;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class ReportExportController extends Controller
{
    /**
     * Export report data.
     */
    public function export(Request $request, string $type): JsonResponse
    {
        $format = $request->get('format', 'csv');
        $dateRange = $request->get('date_range', '30');
        $filters = $request->all();

        try {
            $data = $this->getReportData($type, $dateRange, $filters);
            $filename = $this->generateFilename($type, $format);
            $filePath = $this->exportToFile($data, $filename, $format);

            return ApiResponse::export($filePath, $format, $filename);

        } catch (\Exception $e) {
            return ApiResponse::error('Export failed: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get report data based on type.
     */
    private function getReportData(string $type, int $dateRange, array $filters): array
    {
        return match($type) {
            'sales-summary' => $this->getSalesSummaryData($dateRange, $filters),
            'sales-by-item' => $this->getSalesByItemData($dateRange, $filters),
            'sales-by-category' => $this->getSalesByCategoryData($dateRange, $filters),
            'inventory-valuation' => $this->getInventoryValuationData($filters),
            'stock-movement' => $this->getStockMovementData($dateRange, $filters),
            'low-stock-alert' => $this->getLowStockAlertData($filters),
            'performance-metrics' => $this->getPerformanceMetricsData($dateRange, $filters),
            'purchase-orders' => $this->getPurchaseOrdersData($dateRange, $filters),
            'stock-adjustments' => $this->getStockAdjustmentsData($dateRange, $filters),
            default => throw new \Exception("Unknown report type: {$type}")
        };
    }

    /**
     * Generate filename for export.
     */
    private function generateFilename(string $type, string $format): string
    {
        $timestamp = now()->format('Y-m-d_H-i-s');
        $type = str_replace('-', '_', $type);
        return "report_{$type}_{$timestamp}.{$format}";
    }

    /**
     * Export data to file.
     */
    private function exportToFile(array $data, string $filename, string $format): string
    {
        $content = match($format) {
            'csv' => $this->toCsv($data),
            'json' => json_encode($data, JSON_PRETTY_PRINT),
            'xml' => $this->toXml($data),
            default => throw new \Exception("Unsupported format: {$format}")
        };

        $filePath = "exports/{$filename}";
        Storage::put($filePath, $content);

        return $filePath;
    }

    /**
     * Convert data to CSV format.
     */
    private function toCsv(array $data): string
    {
        if (empty($data)) {
            return '';
        }

        $output = fopen('php://temp', 'r+');
        
        // Write headers
        if (!empty($data)) {
            fputcsv($output, array_keys($data[0]));
        }
        
        // Write data
        foreach ($data as $row) {
            fputcsv($output, $row);
        }
        
        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);
        
        return $csv;
    }

    /**
     * Convert data to XML format.
     */
    private function toXml(array $data): string
    {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><report></report>');
        
        foreach ($data as $row) {
            $item = $xml->addChild('item');
            foreach ($row as $key => $value) {
                $item->addChild($key, htmlspecialchars($value));
            }
        }
        
        return $xml->asXML();
    }

    // Report data methods
    private function getSalesSummaryData(int $dateRange, array $filters): array
    {
        $query = Sale::where('created_at', '>=', now()->subDays($dateRange));

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        if (isset($filters['payment_status'])) {
            $query->where('payment_status', $filters['payment_status']);
        }

        return $query->select([
            'sale_number',
            'customer_id',
            'delivery_agent_id',
            'date',
            'subtotal',
            'tax_amount',
            'discount_amount',
            'total',
            'payment_method',
            'payment_status',
            'otp_verified',
            'created_at'
        ])->get()->toArray();
    }

    private function getSalesByItemData(int $dateRange, array $filters): array
    {
        $query = Sale::with(['items.item', 'customer'])
            ->where('created_at', '>=', now()->subDays($dateRange));

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        $sales = $query->get();
        $data = [];

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $data[] = [
                    'sale_number' => $sale->sale_number,
                    'customer_name' => $sale->customer->name ?? 'Unknown',
                    'item_name' => $item->item->name,
                    'item_sku' => $item->item->sku,
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'total' => $item->total,
                    'sale_date' => $sale->date,
                    'payment_status' => $sale->payment_status
                ];
            }
        }

        return $data;
    }

    private function getSalesByCategoryData(int $dateRange, array $filters): array
    {
        $query = Sale::with(['items.item.category'])
            ->where('created_at', '>=', now()->subDays($dateRange));

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        $sales = $query->get();
        $categoryData = [];

        foreach ($sales as $sale) {
            foreach ($sale->items as $item) {
                $categoryName = $item->item->category->name ?? 'Uncategorized';
                
                if (!isset($categoryData[$categoryName])) {
                    $categoryData[$categoryName] = [
                        'category_name' => $categoryName,
                        'total_quantity' => 0,
                        'total_revenue' => 0,
                        'total_sales' => 0
                    ];
                }
                
                $categoryData[$categoryName]['total_quantity'] += $item->quantity;
                $categoryData[$categoryName]['total_revenue'] += $item->total;
                $categoryData[$categoryName]['total_sales']++;
            }
        }

        return array_values($categoryData);
    }

    private function getInventoryValuationData(array $filters): array
    {
        $query = Item::with(['category', 'supplier'])
            ->where('is_active', true);

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        if (isset($filters['category_id'])) {
            $query->where('category_id', $filters['category_id']);
        }

        return $query->get()->map(function ($item) {
            return [
                'item_name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category->name ?? 'Uncategorized',
                'supplier' => $item->supplier->name ?? 'No Supplier',
                'stock_quantity' => $item->stock_quantity,
                'unit_price' => $item->unit_price,
                'cost_price' => $item->cost_price,
                'total_value' => $item->stock_quantity * $item->unit_price,
                'total_cost' => $item->stock_quantity * ($item->cost_price ?? 0),
                'potential_profit' => $item->stock_quantity * ($item->unit_price - ($item->cost_price ?? 0)),
                'reorder_level' => $item->reorder_level,
                'status' => $item->stock_quantity <= $item->reorder_level ? 'Low Stock' : 'Normal'
            ];
        })->toArray();
    }

    private function getStockMovementData(int $dateRange, array $filters): array
    {
        $query = DB::table('inventory_history')
            ->join('items', 'inventory_history.item_id', '=', 'items.id')
            ->where('inventory_history.created_at', '>=', now()->subDays($dateRange))
            ->select([
                'items.name as item_name',
                'items.sku',
                'inventory_history.reason',
                'inventory_history.quantity_before',
                'inventory_history.quantity_after',
                'inventory_history.change_quantity',
                'inventory_history.location',
                'inventory_history.created_at'
            ]);

        if (isset($filters['delivery_agent_id'])) {
            $query->where('inventory_history.delivery_agent_id', $filters['delivery_agent_id']);
        }

        if (isset($filters['reason'])) {
            $query->where('inventory_history.reason', $filters['reason']);
        }

        return $query->get()->toArray();
    }

    private function getLowStockAlertData(array $filters): array
    {
        $query = Item::with(['category', 'supplier'])
            ->where('stock_quantity', '<=', DB::raw('reorder_level'))
            ->where('is_active', true);

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        return $query->get()->map(function ($item) {
            return [
                'item_name' => $item->name,
                'sku' => $item->sku,
                'category' => $item->category->name ?? 'Uncategorized',
                'supplier' => $item->supplier->name ?? 'No Supplier',
                'current_stock' => $item->stock_quantity,
                'reorder_level' => $item->reorder_level,
                'reorder_quantity' => $item->reorder_quantity,
                'unit_price' => $item->unit_price,
                'total_value' => $item->stock_quantity * $item->unit_price,
                'days_since_last_purchase' => $item->last_purchase_date ? 
                    now()->diffInDays($item->last_purchase_date) : null,
                'urgency_level' => $item->stock_quantity == 0 ? 'Critical' : 'Warning'
            ];
        })->toArray();
    }

    private function getPerformanceMetricsData(int $dateRange, array $filters): array
    {
        $query = PerformanceMetric::where('date', '>=', now()->subDays($dateRange));

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        return $query->get()->map(function ($metric) {
            return [
                'date' => $metric->date,
                'delivery_rate' => $metric->delivery_rate,
                'otp_success_rate' => $metric->otp_success_rate,
                'stock_accuracy' => $metric->stock_accuracy,
                'sales_amount' => $metric->sales_amount,
                'orders_completed' => $metric->orders_completed,
                'orders_total' => $metric->orders_total,
                'delivery_time_avg' => $metric->delivery_time_avg,
                'customer_satisfaction' => $metric->customer_satisfaction,
                'returns_count' => $metric->returns_count,
                'complaints_count' => $metric->complaints_count,
                'bonus_earned' => $metric->bonus_earned,
                'penalties_incurred' => $metric->penalties_incurred
            ];
        })->toArray();
    }

    private function getPurchaseOrdersData(int $dateRange, array $filters): array
    {
        $query = PurchaseOrder::with(['supplier', 'deliveryAgent'])
            ->where('created_at', '>=', now()->subDays($dateRange));

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        return $query->get()->map(function ($order) {
            return [
                'order_number' => $order->order_number,
                'supplier_name' => $order->supplier->name ?? 'Unknown',
                'delivery_agent' => $order->deliveryAgent->name ?? 'Unknown',
                'date' => $order->date,
                'expected_date' => $order->expected_date,
                'status' => $order->status,
                'total_amount' => $order->total_amount,
                'notes' => $order->notes,
                'created_at' => $order->created_at
            ];
        })->toArray();
    }

    private function getStockAdjustmentsData(int $dateRange, array $filters): array
    {
        $query = StockAdjustment::with(['item', 'deliveryAgent', 'employee'])
            ->where('created_at', '>=', now()->subDays($dateRange));

        if (isset($filters['delivery_agent_id'])) {
            $query->where('delivery_agent_id', $filters['delivery_agent_id']);
        }

        if (isset($filters['adjustment_type'])) {
            $query->where('adjustment_type', $filters['adjustment_type']);
        }

        return $query->get()->map(function ($adjustment) {
            return [
                'reference_number' => $adjustment->reference_number,
                'item_name' => $adjustment->item->name,
                'adjustment_type' => $adjustment->adjustment_type,
                'quantity' => $adjustment->quantity,
                'reason' => $adjustment->reason,
                'date' => $adjustment->date,
                'status' => $adjustment->status,
                'delivery_agent' => $adjustment->deliveryAgent->name ?? 'Unknown',
                'employee' => $adjustment->employee->name ?? 'Unknown',
                'approved_by' => $adjustment->approvedBy->name ?? 'Not Approved',
                'approved_at' => $adjustment->approved_at,
                'created_at' => $adjustment->created_at
            ];
        })->toArray();
    }
} 
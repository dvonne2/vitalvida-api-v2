<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Bin;
use App\Models\DeliveryAgent;
use App\Models\Zobin;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockAlertController extends Controller
{
    /**
     * Get stock alerts and notifications
     * GET /api/inventory-portal/stock/alerts
     */
    public function getStockAlerts(): JsonResponse
    {
        // Critical stock alerts
        $criticalStock = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))
            ->where('available_quantity', '>', 0)
            ->get()
            ->map(function($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => $product->available_quantity,
                    'minimum_stock' => $product->minimum_stock_level,
                    'days_remaining' => $this->calculateDaysUntilStockout($product),
                    'priority' => 'critical',
                    'action_required' => 'restock_immediately'
                ];
            });

        // Out of stock alerts
        $outOfStock = Product::where('available_quantity', 0)
            ->get()
            ->map(function($product) {
                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'current_stock' => 0,
                    'minimum_stock' => $product->minimum_stock_level,
                    'days_out_of_stock' => $this->calculateDaysOutOfStock($product),
                    'priority' => 'urgent',
                    'action_required' => 'emergency_restock'
                ];
            });

        // DA low stock alerts
        $daLowStock = DeliveryAgent::where('status', 'active')
            ->whereHas('zobin', function($query) {
                $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 3');
            })
            ->with('zobin')
            ->get()
            ->map(function($da) {
                $zobin = $da->zobin;
                $lowestStock = min($zobin->shampoo_count, $zobin->pomade_count, $zobin->conditioner_count);
                
                return [
                    'da_id' => $da->da_code,
                    'da_name' => $da->user->name ?? $da->da_code,
                    'location' => $da->current_location,
                    'lowest_stock' => $lowestStock,
                    'stock_breakdown' => [
                        'shampoo' => $zobin->shampoo_count,
                        'pomade' => $zobin->pomade_count,
                        'conditioner' => $zobin->conditioner_count
                    ],
                    'priority' => $lowestStock === 0 ? 'urgent' : 'warning',
                    'action_required' => $lowestStock === 0 ? 'emergency_restock' : 'schedule_restock'
                ];
            });

        // Bin critical stock alerts
        $binCriticalStock = Bin::where('current_stock_count', '<=', 3)
            ->where('is_active', true)
            ->get()
            ->map(function($bin) {
                return [
                    'bin_id' => $bin->bin_code,
                    'bin_name' => $bin->name,
                    'location' => $bin->location,
                    'current_stock' => $bin->current_stock_count,
                    'capacity' => $bin->capacity,
                    'utilization_rate' => round(($bin->current_stock_count / $bin->capacity) * 100, 1),
                    'priority' => 'critical',
                    'action_required' => 'restock_bin'
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => [
                    'total_alerts' => $criticalStock->count() + $outOfStock->count() + $daLowStock->count() + $binCriticalStock->count(),
                    'critical_alerts' => $criticalStock->count(),
                    'urgent_alerts' => $outOfStock->count(),
                    'da_alerts' => $daLowStock->count(),
                    'bin_alerts' => $binCriticalStock->count()
                ],
                'critical_stock' => $criticalStock,
                'out_of_stock' => $outOfStock,
                'da_low_stock' => $daLowStock,
                'bin_critical_stock' => $binCriticalStock,
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get real-time stock levels
     * GET /api/inventory-portal/stock/live-levels
     */
    public function getLiveStockLevels(): JsonResponse
    {
        // Product stock levels
        $productLevels = Product::select('id', 'name', 'sku', 'available_quantity', 'minimum_stock_level', 'unit_price')
            ->get()
            ->map(function($product) {
                $status = 'optimal';
                if ($product->available_quantity === 0) {
                    $status = 'out_of_stock';
                } elseif ($product->available_quantity <= $product->minimum_stock_level) {
                    $status = 'low_stock';
                }

                return [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => $product->available_quantity,
                    'minimum_stock' => $product->minimum_stock_level,
                    'unit_price' => $product->unit_price,
                    'stock_value' => $product->available_quantity * $product->unit_price,
                    'status' => $status,
                    'days_remaining' => $this->calculateDaysUntilStockout($product)
                ];
            });

        // DA stock levels
        $daStockLevels = DeliveryAgent::where('status', 'active')
            ->with('zobin')
            ->get()
            ->map(function($da) {
                $zobin = $da->zobin;
                $totalStock = $zobin->shampoo_count + $zobin->pomade_count + $zobin->conditioner_count;
                $lowestStock = min($zobin->shampoo_count, $zobin->pomade_count, $zobin->conditioner_count);
                
                $status = 'optimal';
                if ($lowestStock === 0) {
                    $status = 'critical';
                } elseif ($lowestStock < 3) {
                    $status = 'warning';
                }

                return [
                    'da_id' => $da->da_code,
                    'da_name' => $da->user->name ?? $da->da_code,
                    'location' => $da->current_location,
                    'total_stock' => $totalStock,
                    'stock_breakdown' => [
                        'shampoo' => $zobin->shampoo_count,
                        'pomade' => $zobin->pomade_count,
                        'conditioner' => $zobin->conditioner_count
                    ],
                    'lowest_stock' => $lowestStock,
                    'status' => $status,
                    'last_updated' => $da->updated_at->toISOString()
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'product_levels' => $productLevels,
                'da_stock_levels' => $daStockLevels,
                'summary' => [
                    'total_products' => $productLevels->count(),
                    'low_stock_products' => $productLevels->where('status', 'low_stock')->count(),
                    'out_of_stock_products' => $productLevels->where('status', 'out_of_stock')->count(),
                    'total_das' => $daStockLevels->count(),
                    'critical_das' => $daStockLevels->where('status', 'critical')->count(),
                    'warning_das' => $daStockLevels->where('status', 'warning')->count()
                ],
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get stock movement alerts
     * GET /api/inventory-portal/stock/movement-alerts
     */
    public function getMovementAlerts(): JsonResponse
    {
        $today = now()->toDateString();
        $thisWeek = now()->startOfWeek();

        // Recent stock movements
        $recentMovements = DB::table('inventory_movements')
            ->whereBetween('created_at', [$thisWeek, now()])
            ->orderBy('created_at', 'desc')
            ->limit(20)
            ->get()
            ->map(function($movement) {
                return [
                    'movement_id' => $movement->id,
                    'movement_type' => $movement->movement_type,
                    'item_name' => $movement->item_name,
                    'quantity_changed' => $movement->quantity_changed,
                    'previous_quantity' => $movement->previous_quantity,
                    'new_quantity' => $movement->new_quantity,
                    'location' => $movement->location ?? 'Unknown',
                    'timestamp' => $movement->created_at,
                    'user' => $movement->user_name ?? 'System'
                ];
            });

        // Unusual stock movements (large quantities)
        $unusualMovements = DB::table('inventory_movements')
            ->whereBetween('created_at', [$thisWeek, now()])
            ->where('quantity_changed', '>', 50) // Large quantity threshold
            ->orderBy('quantity_changed', 'desc')
            ->limit(10)
            ->get()
            ->map(function($movement) {
                return [
                    'movement_id' => $movement->id,
                    'movement_type' => $movement->movement_type,
                    'item_name' => $movement->item_name,
                    'quantity_changed' => $movement->quantity_changed,
                    'reason' => 'Large quantity movement',
                    'timestamp' => $movement->created_at,
                    'priority' => 'review_required'
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'recent_movements' => $recentMovements,
                'unusual_movements' => $unusualMovements,
                'summary' => [
                    'total_movements_this_week' => $recentMovements->count(),
                    'unusual_movements' => $unusualMovements->count(),
                    'movements_today' => $recentMovements->where('timestamp', '>=', $today)->count()
                ],
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Acknowledge stock alert
     * POST /api/inventory-portal/stock/alerts/{alertId}/acknowledge
     */
    public function acknowledgeAlert(Request $request, $alertId): JsonResponse
    {
        $request->validate([
            'acknowledgment_note' => 'nullable|string|max:500'
        ]);

        // In a real implementation, you would update an alerts table
        // For now, we'll return a success response
        return response()->json([
            'success' => true,
            'message' => 'Alert acknowledged successfully',
            'data' => [
                'alert_id' => $alertId,
                'acknowledged_at' => now()->toISOString(),
                'acknowledged_by' => auth()->id(),
                'note' => $request->acknowledgment_note
            ]
        ]);
    }

    /**
     * Calculate days until stockout for a product
     */
    private function calculateDaysUntilStockout($product): int
    {
        // This is a simplified calculation
        // In a real implementation, you would use historical data
        $dailyUsage = 5; // Average daily usage
        return $dailyUsage > 0 ? floor($product->available_quantity / $dailyUsage) : 0;
    }

    /**
     * Calculate days out of stock for a product
     */
    private function calculateDaysOutOfStock($product): int
    {
        // This would require tracking when the product went out of stock
        // For now, return a placeholder
        return 0;
    }
}

<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Bin;
use App\Models\BinStock;
use App\Models\DeliveryAgent;
use App\Models\Zobin;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockManagementController extends Controller
{
    /**
     * Total products, bins, risks summary
     * GET /api/stock/overview
     */
    public function getStockOverview(): JsonResponse
    {
        $totalProducts = Product::count();
        $activeProducts = Product::where('status', 'active')->count();
        $lowStockProducts = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))
            ->where('available_quantity', '>', 0)
            ->count();
        $outOfStockProducts = Product::where('available_quantity', 0)->count();

        $totalBins = Bin::count();
        $activeBins = Bin::where('is_active', true)->count();
        $criticalStockBins = Bin::where('current_stock_count', '<=', 3)->count();

        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        $dasWithLowStock = DeliveryAgent::whereHas('zobin', function($query) {
            $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 3');
        })->count();

        $stockValue = Product::sum(DB::raw('available_quantity * unit_price'));
        $lowStockValue = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))
            ->sum(DB::raw('available_quantity * unit_price'));

        $overview = [
            'products' => [
                'total' => $totalProducts,
                'active' => $activeProducts,
                'low_stock' => $lowStockProducts,
                'out_of_stock' => $outOfStockProducts,
                'risk_percentage' => $totalProducts > 0 ? round((($lowStockProducts + $outOfStockProducts) / $totalProducts) * 100, 1) : 0
            ],
            'bins' => [
                'total' => $totalBins,
                'active' => $activeBins,
                'critical_stock' => $criticalStockBins,
                'utilization_rate' => $this->calculateBinUtilizationRate()
            ],
            'delivery_agents' => [
                'total' => $totalDAs,
                'low_stock' => $dasWithLowStock,
                'low_stock_percentage' => $totalDAs > 0 ? round(($dasWithLowStock / $totalDAs) * 100, 1) : 0
            ],
            'value' => [
                'total_stock_value' => round($stockValue, 2),
                'low_stock_value' => round($lowStockValue, 2),
                'value_at_risk' => round($stockValue - $lowStockValue, 2)
            ],
            'last_updated' => now()->toISOString()
        ];

        return response()->json([
            'success' => true,
            'data' => $overview
        ]);
    }

    /**
     * State-wise stock levels
     * GET /api/stock/state-overview
     */
    public function getStateOverview(): JsonResponse
    {
        $stateOverview = DB::table('delivery_agents')
            ->join('zobins', 'delivery_agents.id', '=', 'zobins.delivery_agent_id')
            ->where('delivery_agents.status', 'active')
            ->selectRaw('
                delivery_agents.state,
                COUNT(*) as total_das,
                AVG(MIN(zobins.shampoo_count, MIN(zobins.pomade_count, zobins.conditioner_count))) as avg_available_sets,
                SUM(CASE WHEN MIN(zobins.shampoo_count, MIN(zobins.pomade_count, zobins.conditioner_count)) < 3 THEN 1 ELSE 0 END) as critical_stock_das,
                SUM(zobins.shampoo_count) as total_shampoo,
                SUM(zobins.pomade_count) as total_pomade,
                SUM(zobins.conditioner_count) as total_conditioner
            ')
            ->groupBy('delivery_agents.state')
            ->orderBy('total_das', 'desc')
            ->get();

        $stateOverview->transform(function ($state) {
            $state->critical_percentage = $state->total_das > 0 
                ? round(($state->critical_stock_das / $state->total_das) * 100, 1)
                : 0;
            $state->avg_available_sets = round($state->avg_available_sets, 1);
            return $state;
        });

        return response()->json([
            'success' => true,
            'data' => $stateOverview
        ]);
    }

    /**
     * Bin management status
     * GET /api/stock/bins/status
     */
    public function getBinStatus(): JsonResponse
    {
        $binStatus = Bin::select('id', 'name', 'state', 'current_stock_count', 'capacity', 'utilization_rate', 'is_active')
            ->orderBy('state')
            ->orderBy('name')
            ->get();

        $binStatus->transform(function ($bin) {
            $bin->status_level = $this->getBinStatusLevel($bin);
            $bin->utilization_percentage = round($bin->utilization_rate, 1);
            return $bin;
        });

        $statusSummary = [
            'total_bins' => $binStatus->count(),
            'active_bins' => $binStatus->where('is_active', true)->count(),
            'excellent_utilization' => $binStatus->where('utilization_rate', '>=', 80)->count(),
            'good_utilization' => $binStatus->whereBetween('utilization_rate', [60, 79])->count(),
            'poor_utilization' => $binStatus->where('utilization_rate', '<', 60)->count(),
            'critical_stock' => $binStatus->where('current_stock_count', '<=', 3)->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $statusSummary,
                'bins' => $binStatus
            ]
        ]);
    }

    /**
     * Real-time stock levels
     * GET /api/stock/live-levels
     */
    public function getLiveStockLevels(): JsonResponse
    {
        $products = Product::select('id', 'name', 'sku', 'category', 'available_quantity', 'minimum_stock_level', 'maximum_stock_level', 'unit_price')
            ->where('status', 'active')
            ->orderBy('category')
            ->orderBy('name')
            ->get();

        $products->transform(function ($product) {
            $product->stock_status = $this->getProductStockStatus($product);
            $product->stock_value = $product->available_quantity * $product->unit_price;
            $product->utilization_percentage = $product->maximum_stock_level > 0 
                ? round(($product->available_quantity / $product->maximum_stock_level) * 100, 1)
                : 0;
            return $product;
        });

        $liveData = [
            'total_products' => $products->count(),
            'in_stock' => $products->where('available_quantity', '>', 0)->count(),
            'low_stock' => $products->where('stock_status', 'low')->count(),
            'out_of_stock' => $products->where('stock_status', 'out_of_stock')->count(),
            'overstocked' => $products->where('stock_status', 'overstocked')->count(),
            'total_value' => $products->sum('stock_value'),
            'last_updated' => now()->toISOString(),
            'products' => $products
        ];

        return response()->json([
            'success' => true,
            'data' => $liveData
        ]);
    }

    /**
     * Critical stock alerts (DA-003, etc.)
     * GET /api/stock/critical-alerts
     */
    public function getCriticalAlerts(): JsonResponse
    {
        $criticalAlerts = [];

        // Product critical alerts
        $criticalProducts = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))
            ->where('status', 'active')
            ->get();

        foreach ($criticalProducts as $product) {
            $criticalAlerts[] = [
                'id' => 'PROD-' . $product->id,
                'type' => 'product_critical',
                'severity' => $product->available_quantity == 0 ? 'critical' : 'high',
                'title' => $product->available_quantity == 0 ? 'Product Out of Stock' : 'Product Low Stock',
                'message' => $product->available_quantity == 0 
                    ? "Product {$product->name} is completely out of stock"
                    : "Product {$product->name} is running low (Available: {$product->available_quantity})",
                'product_id' => $product->id,
                'product_name' => $product->name,
                'available_quantity' => $product->available_quantity,
                'minimum_level' => $product->minimum_stock_level,
                'created_at' => now()->toISOString()
            ];
        }

        // DA critical stock alerts
        $criticalDAs = DeliveryAgent::whereHas('zobin', function($query) {
            $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 3');
        })->with(['user', 'zobin'])->get();

        foreach ($criticalDAs as $da) {
            $criticalAlerts[] = [
                'id' => 'DA-' . $da->id,
                'type' => 'da_critical',
                'severity' => 'critical',
                'title' => 'DA Critical Stock Alert',
                'message' => "DA {$da->da_code} has critically low stock (Available sets: {$da->zobin->available_sets})",
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'da_name' => $da->user->name ?? 'Unknown',
                'available_sets' => $da->zobin->available_sets,
                'shampoo_count' => $da->zobin->shampoo_count,
                'pomade_count' => $da->zobin->pomade_count,
                'conditioner_count' => $da->zobin->conditioner_count,
                'created_at' => now()->toISOString()
            ];
        }

        // Bin critical alerts
        $criticalBins = Bin::where('current_stock_count', '<=', 3)
            ->where('is_active', true)
            ->get();

        foreach ($criticalBins as $bin) {
            $criticalAlerts[] = [
                'id' => 'BIN-' . $bin->id,
                'type' => 'bin_critical',
                'severity' => 'high',
                'title' => 'Bin Critical Stock Alert',
                'message' => "Bin {$bin->name} has critically low stock (Current: {$bin->current_stock_count})",
                'bin_id' => $bin->id,
                'bin_name' => $bin->name,
                'current_stock' => $bin->current_stock_count,
                'capacity' => $bin->capacity,
                'state' => $bin->state,
                'created_at' => now()->toISOString()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_alerts' => count($criticalAlerts),
                'critical_severity' => count(array_filter($criticalAlerts, fn($alert) => $alert['severity'] === 'critical')),
                'high_severity' => count(array_filter($criticalAlerts, fn($alert) => $alert['severity'] === 'high')),
                'alerts' => $criticalAlerts
            ]
        ]);
    }

    /**
     * Stock warning alerts
     * GET /api/stock/warnings
     */
    public function getStockWarnings(): JsonResponse
    {
        $warnings = [];

        // Products approaching low stock
        $approachingLowStock = Product::where('available_quantity', '>', DB::raw('minimum_stock_level'))
            ->where('available_quantity', '<=', DB::raw('minimum_stock_level * 1.5'))
            ->where('status', 'active')
            ->get();

        foreach ($approachingLowStock as $product) {
            $warnings[] = [
                'id' => 'WARN-PROD-' . $product->id,
                'type' => 'approaching_low_stock',
                'severity' => 'medium',
                'title' => 'Product Approaching Low Stock',
                'message' => "Product {$product->name} is approaching low stock level",
                'product_id' => $product->id,
                'product_name' => $product->name,
                'available_quantity' => $product->available_quantity,
                'minimum_level' => $product->minimum_stock_level,
                'days_until_low' => $this->calculateDaysUntilLow($product),
                'created_at' => now()->toISOString()
            ];
        }

        // DAs approaching low stock
        $approachingLowStockDAs = DeliveryAgent::whereHas('zobin', function($query) {
            $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) >= 3')
                  ->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 5');
        })->with(['user', 'zobin'])->get();

        foreach ($approachingLowStockDAs as $da) {
            $warnings[] = [
                'id' => 'WARN-DA-' . $da->id,
                'type' => 'da_approaching_low',
                'severity' => 'medium',
                'title' => 'DA Approaching Low Stock',
                'message' => "DA {$da->da_code} is approaching low stock levels",
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'da_name' => $da->user->name ?? 'Unknown',
                'available_sets' => $da->zobin->available_sets,
                'created_at' => now()->toISOString()
            ];
        }

        // Bins with low utilization
        $lowUtilizationBins = Bin::where('utilization_rate', '<', 30)
            ->where('is_active', true)
            ->get();

        foreach ($lowUtilizationBins as $bin) {
            $warnings[] = [
                'id' => 'WARN-BIN-' . $bin->id,
                'type' => 'low_utilization',
                'severity' => 'low',
                'title' => 'Bin Low Utilization',
                'message' => "Bin {$bin->name} has low utilization rate",
                'bin_id' => $bin->id,
                'bin_name' => $bin->name,
                'utilization_rate' => round($bin->utilization_rate, 1),
                'current_stock' => $bin->current_stock_count,
                'capacity' => $bin->capacity,
                'created_at' => now()->toISOString()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_warnings' => count($warnings),
                'medium_severity' => count(array_filter($warnings, fn($warning) => $warning['severity'] === 'medium')),
                'low_severity' => count(array_filter($warnings, fn($warning) => $warning['severity'] === 'low')),
                'warnings' => $warnings
            ]
        ]);
    }

    /**
     * Trigger restock action
     * POST /api/stock/restock
     */
    public function triggerRestock(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'restock_quantities' => 'required|array',
            'restock_quantities.*' => 'integer|min:1',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'notes' => 'nullable|string',
            'triggered_by' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $restockOrders = [];

            foreach ($request->product_ids as $index => $productId) {
                $product = Product::find($productId);
                $quantity = $request->restock_quantities[$index] ?? 0;

                if ($quantity > 0) {
                    $restockOrders[] = [
                        'product_id' => $productId,
                        'product_name' => $product->name,
                        'current_stock' => $product->available_quantity,
                        'restock_quantity' => $quantity,
                        'target_stock' => $product->available_quantity + $quantity,
                        'estimated_cost' => $quantity * ($product->cost_price ?? $product->unit_price),
                        'priority' => $request->priority ?? 'medium'
                    ];
                }
            }

            // Here you would typically create purchase orders or restock requests
            // For now, we'll just log the restock action
            \Log::info('Restock action triggered', [
                'restock_orders' => $restockOrders,
                'triggered_by' => $request->triggered_by,
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Restock action triggered successfully',
                'data' => [
                    'restock_orders' => $restockOrders,
                    'total_items' => count($restockOrders),
                    'estimated_total_cost' => array_sum(array_column($restockOrders, 'estimated_cost')),
                    'triggered_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to trigger restock action',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all delivery agent bins
     * GET /api/bins
     */
    public function getBins(Request $request): JsonResponse
    {
        $query = Bin::with(['deliveryAgent.user']);

        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('utilization_min')) {
            $query->where('utilization_rate', '>=', $request->utilization_min);
        }

        if ($request->has('utilization_max')) {
            $query->where('utilization_rate', '<=', $request->utilization_max);
        }

        $bins = $query->orderBy('state')
            ->orderBy('name')
            ->paginate($request->get('per_page', 20));

        $bins->getCollection()->transform(function ($bin) {
            $bin->status_level = $this->getBinStatusLevel($bin);
            $bin->utilization_percentage = round($bin->utilization_rate, 1);
            return $bin;
        });

        return response()->json([
            'success' => true,
            'data' => $bins
        ]);
    }

    /**
     * Get specific bin details
     * GET /api/bins/{binId}
     */
    public function getBinDetails($binId): JsonResponse
    {
        $bin = Bin::with(['deliveryAgent.user'])->find($binId);

        if (!$bin) {
            return response()->json([
                'success' => false,
                'message' => 'Bin not found'
            ], 404);
        }

        // Get bin stock details
        $binStock = BinStock::where('bin_id', $binId)
            ->with('product')
            ->get();

        // Get recent movements
        $recentMovements = InventoryMovement::where('destination_bin_id', $binId)
            ->orWhere('source_bin_id', $binId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        $binDetails = [
            'bin' => $bin,
            'status_level' => $this->getBinStatusLevel($bin),
            'utilization_percentage' => round($bin->utilization_rate, 1),
            'stock_details' => $binStock,
            'recent_movements' => $recentMovements,
            'total_stock_value' => $binStock->sum(function($stock) {
                return $stock->quantity * ($stock->product->unit_price ?? 0);
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $binDetails
        ]);
    }

    /**
     * Add stock to bin
     * POST /api/bins/add-stock
     */
    public function addStockToBin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'bin_id' => 'required|exists:bins,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'added_by' => 'required|exists:users,id',
            'notes' => 'nullable|string',
            'source_reference' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            $bin = Bin::find($request->bin_id);
            $product = Product::find($request->product_id);

            // Check if bin has capacity
            if ($bin->current_stock_count + $request->quantity > $bin->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bin capacity exceeded'
                ], 422);
            }

            // Add stock to bin
            $binStock = BinStock::where('bin_id', $request->bin_id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($binStock) {
                $binStock->increment('quantity', $request->quantity);
            } else {
                BinStock::create([
                    'bin_id' => $request->bin_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity
                ]);
            }

            // Update bin current stock count
            $bin->increment('current_stock_count', $request->quantity);

            // Update bin utilization rate
            $bin->update([
                'utilization_rate' => ($bin->current_stock_count / $bin->capacity) * 100
            ]);

            // Create inventory movement
            InventoryMovement::create([
                'movement_type' => 'inbound',
                'item_id' => $request->product_id,
                'item_name' => $product->name,
                'item_sku' => $product->sku,
                'quantity_before' => $product->available_quantity,
                'quantity_changed' => $request->quantity,
                'quantity_after' => $product->available_quantity + $request->quantity,
                'destination_bin_id' => $request->bin_id,
                'performed_by' => $request->added_by,
                'notes' => $request->notes,
                'source_reference' => $request->source_reference,
                'movement_at' => now()
            ]);

            // Update product quantity
            $product->increment('available_quantity', $request->quantity);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock added to bin successfully',
                'data' => [
                    'bin_id' => $request->bin_id,
                    'product_id' => $request->product_id,
                    'quantity_added' => $request->quantity,
                    'new_bin_stock' => $bin->fresh()->current_stock_count,
                    'new_utilization_rate' => round($bin->fresh()->utilization_rate, 1)
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to add stock to bin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer between bins
     * POST /api/bins/transfer
     */
    public function transferBetweenBins(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'source_bin_id' => 'required|exists:bins,id',
            'destination_bin_id' => 'required|exists:bins,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'transferred_by' => 'required|exists:users,id',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        if ($request->source_bin_id === $request->destination_bin_id) {
            return response()->json([
                'success' => false,
                'message' => 'Source and destination bins cannot be the same'
            ], 422);
        }

        try {
            DB::beginTransaction();

            $sourceBin = Bin::find($request->source_bin_id);
            $destinationBin = Bin::find($request->destination_bin_id);
            $product = Product::find($request->product_id);

            // Check source bin stock
            $sourceBinStock = BinStock::where('bin_id', $request->source_bin_id)
                ->where('product_id', $request->product_id)
                ->first();

            if (!$sourceBinStock || $sourceBinStock->quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in source bin'
                ], 422);
            }

            // Check destination bin capacity
            if ($destinationBin->current_stock_count + $request->quantity > $destinationBin->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Destination bin capacity exceeded'
                ], 422);
            }

            // Transfer stock
            $sourceBinStock->decrement('quantity', $request->quantity);
            $sourceBin->decrement('current_stock_count', $request->quantity);

            $destinationBinStock = BinStock::where('bin_id', $request->destination_bin_id)
                ->where('product_id', $request->product_id)
                ->first();

            if ($destinationBinStock) {
                $destinationBinStock->increment('quantity', $request->quantity);
            } else {
                BinStock::create([
                    'bin_id' => $request->destination_bin_id,
                    'product_id' => $request->product_id,
                    'quantity' => $request->quantity
                ]);
            }

            $destinationBin->increment('current_stock_count', $request->quantity);

            // Update utilization rates
            $sourceBin->update([
                'utilization_rate' => ($sourceBin->current_stock_count / $sourceBin->capacity) * 100
            ]);
            $destinationBin->update([
                'utilization_rate' => ($destinationBin->current_stock_count / $destinationBin->capacity) * 100
            ]);

            // Create inventory movement
            InventoryMovement::create([
                'movement_type' => 'transfer',
                'item_id' => $request->product_id,
                'item_name' => $product->name,
                'item_sku' => $product->sku,
                'quantity_before' => $product->available_quantity,
                'quantity_changed' => 0, // No change in total product quantity
                'quantity_after' => $product->available_quantity,
                'source_bin_id' => $request->source_bin_id,
                'destination_bin_id' => $request->destination_bin_id,
                'performed_by' => $request->transferred_by,
                'notes' => $request->notes,
                'movement_at' => now()
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Stock transferred successfully',
                'data' => [
                    'source_bin' => [
                        'id' => $sourceBin->id,
                        'new_stock_count' => $sourceBin->fresh()->current_stock_count,
                        'new_utilization_rate' => round($sourceBin->fresh()->utilization_rate, 1)
                    ],
                    'destination_bin' => [
                        'id' => $destinationBin->id,
                        'new_stock_count' => $destinationBin->fresh()->current_stock_count,
                        'new_utilization_rate' => round($destinationBin->fresh()->utilization_rate, 1)
                    ],
                    'quantity_transferred' => $request->quantity
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer stock',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Critical stockout alerts
     * GET /api/bins/critical-stockouts
     */
    public function getCriticalStockouts(): JsonResponse
    {
        $criticalStockouts = [];

        // Bins with no stock
        $emptyBins = Bin::where('current_stock_count', 0)
            ->where('is_active', true)
            ->with(['deliveryAgent.user'])
            ->get();

        foreach ($emptyBins as $bin) {
            $criticalStockouts[] = [
                'id' => 'EMPTY-BIN-' . $bin->id,
                'type' => 'empty_bin',
                'severity' => 'critical',
                'title' => 'Empty Bin Alert',
                'message' => "Bin {$bin->name} is completely empty",
                'bin_id' => $bin->id,
                'bin_name' => $bin->name,
                'state' => $bin->state,
                'assigned_da' => $bin->deliveryAgent ? $bin->deliveryAgent->da_code : 'Unassigned',
                'created_at' => now()->toISOString()
            ];
        }

        // Bins with critical stock levels
        $criticalBins = Bin::where('current_stock_count', '>', 0)
            ->where('current_stock_count', '<=', 3)
            ->where('is_active', true)
            ->with(['deliveryAgent.user'])
            ->get();

        foreach ($criticalBins as $bin) {
            $criticalStockouts[] = [
                'id' => 'CRITICAL-BIN-' . $bin->id,
                'type' => 'critical_stock',
                'severity' => 'high',
                'title' => 'Critical Stock Level',
                'message' => "Bin {$bin->name} has critically low stock",
                'bin_id' => $bin->id,
                'bin_name' => $bin->name,
                'current_stock' => $bin->current_stock_count,
                'capacity' => $bin->capacity,
                'state' => $bin->state,
                'assigned_da' => $bin->deliveryAgent ? $bin->deliveryAgent->da_code : 'Unassigned',
                'created_at' => now()->toISOString()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_alerts' => count($criticalStockouts),
                'critical_severity' => count(array_filter($criticalStockouts, fn($alert) => $alert['severity'] === 'critical')),
                'high_severity' => count(array_filter($criticalStockouts, fn($alert) => $alert['severity'] === 'high')),
                'alerts' => $criticalStockouts
            ]
        ]);
    }

    /**
     * Get restock recommendations
     * GET /api/stock/recommendations
     */
    public function getRestockRecommendations(): JsonResponse
    {
        $recommendations = [];

        // Products that need restocking
        $productsNeedingRestock = Product::where('available_quantity', '<=', DB::raw('minimum_stock_level'))
            ->where('status', 'active')
            ->get();

        foreach ($productsNeedingRestock as $product) {
            $recommendedQuantity = $product->maximum_stock_level - $product->available_quantity;
            
            $recommendations[] = [
                'id' => 'RESTOCK-PROD-' . $product->id,
                'type' => 'product_restock',
                'priority' => $product->available_quantity == 0 ? 'urgent' : 'high',
                'product_id' => $product->id,
                'product_name' => $product->name,
                'current_stock' => $product->available_quantity,
                'minimum_level' => $product->minimum_stock_level,
                'maximum_level' => $product->maximum_stock_level,
                'recommended_quantity' => $recommendedQuantity,
                'estimated_cost' => $recommendedQuantity * ($product->cost_price ?? $product->unit_price),
                'reason' => $product->available_quantity == 0 ? 'Out of stock' : 'Below minimum level'
            ];
        }

        // DAs needing stock replenishment
        $dasNeedingStock = DeliveryAgent::whereHas('zobin', function($query) {
            $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) < 5');
        })->with(['user', 'zobin'])->get();

        foreach ($dasNeedingStock as $da) {
            $shortfall = 5 - $da->zobin->available_sets;
            
            $recommendations[] = [
                'id' => 'RESTOCK-DA-' . $da->id,
                'type' => 'da_restock',
                'priority' => $da->zobin->available_sets < 3 ? 'urgent' : 'medium',
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'da_name' => $da->user->name ?? 'Unknown',
                'current_sets' => $da->zobin->available_sets,
                'recommended_sets' => $shortfall,
                'shampoo_needed' => max(0, 5 - $da->zobin->shampoo_count),
                'pomade_needed' => max(0, 5 - $da->zobin->pomade_count),
                'conditioner_needed' => max(0, 5 - $da->zobin->conditioner_count),
                'reason' => $da->zobin->available_sets < 3 ? 'Critical stock level' : 'Below recommended level'
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_recommendations' => count($recommendations),
                'urgent_priority' => count(array_filter($recommendations, fn($rec) => $rec['priority'] === 'urgent')),
                'high_priority' => count(array_filter($recommendations, fn($rec) => $rec['priority'] === 'high')),
                'medium_priority' => count(array_filter($recommendations, fn($rec) => $rec['priority'] === 'medium')),
                'recommendations' => $recommendations
            ]
        ]);
    }

    /**
     * Create restock order
     * POST /api/stock/restock-order
     */
    public function createRestockOrder(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'recommendations' => 'required|array',
            'recommendations.*.id' => 'required|string',
            'recommendations.*.quantity' => 'required|integer|min:1',
            'priority' => 'sometimes|in:low,medium,high,urgent',
            'notes' => 'nullable|string',
            'created_by' => 'required|exists:users,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $restockItems = [];
            $totalCost = 0;

            foreach ($request->recommendations as $recommendation) {
                if (str_starts_with($recommendation['id'], 'RESTOCK-PROD-')) {
                    $productId = str_replace('RESTOCK-PROD-', '', $recommendation['id']);
                    $product = Product::find($productId);
                    
                    if ($product) {
                        $itemCost = $recommendation['quantity'] * ($product->cost_price ?? $product->unit_price);
                        $totalCost += $itemCost;
                        
                        $restockItems[] = [
                            'type' => 'product',
                            'product_id' => $productId,
                            'product_name' => $product->name,
                            'quantity' => $recommendation['quantity'],
                            'unit_cost' => $product->cost_price ?? $product->unit_price,
                            'total_cost' => $itemCost
                        ];
                    }
                }
            }

            // Here you would typically create a purchase order
            // For now, we'll just log the restock order
            \Log::info('Restock order created', [
                'restock_items' => $restockItems,
                'total_cost' => $totalCost,
                'priority' => $request->priority,
                'notes' => $request->notes,
                'created_by' => $request->created_by
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Restock order created successfully',
                'data' => [
                    'order_id' => 'RO-' . strtoupper(uniqid()),
                    'restock_items' => $restockItems,
                    'total_cost' => $totalCost,
                    'priority' => $request->priority,
                    'created_at' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create restock order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get aging inventory report
     * GET /api/stock/aging-inventory
     */
    public function getAgingInventory(): JsonResponse
    {
        $agingInventory = [];

        // Get products with low movement
        $lowMovementProducts = Product::where('status', 'active')
            ->where('available_quantity', '>', 0)
            ->get();

        foreach ($lowMovementProducts as $product) {
            // Get last movement date
            $lastMovement = InventoryMovement::where('item_id', $product->id)
                ->orderBy('created_at', 'desc')
                ->first();

            $daysSinceLastMovement = $lastMovement 
                ? now()->diffInDays($lastMovement->created_at)
                : now()->diffInDays($product->created_at);

            if ($daysSinceLastMovement > 30) {
                $agingInventory[] = [
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'sku' => $product->sku,
                    'current_stock' => $product->available_quantity,
                    'stock_value' => $product->available_quantity * $product->unit_price,
                    'days_since_last_movement' => $daysSinceLastMovement,
                    'aging_category' => $this->getAgingCategory($daysSinceLastMovement),
                    'last_movement_date' => $lastMovement ? $lastMovement->created_at->toDateString() : null
                ];
            }
        }

        // Sort by days since last movement (oldest first)
        usort($agingInventory, function($a, $b) {
            return $b['days_since_last_movement'] <=> $a['days_since_last_movement'];
        });

        $agingSummary = [
            'total_items' => count($agingInventory),
            'total_value' => array_sum(array_column($agingInventory, 'stock_value')),
            'over_90_days' => count(array_filter($agingInventory, fn($item) => $item['days_since_last_movement'] > 90)),
            'over_60_days' => count(array_filter($agingInventory, fn($item) => $item['days_since_last_movement'] > 60)),
            'over_30_days' => count(array_filter($agingInventory, fn($item) => $item['days_since_last_movement'] > 30))
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $agingSummary,
                'aging_inventory' => $agingInventory
            ]
        ]);
    }

    /**
     * Calculate bin utilization rate
     */
    private function calculateBinUtilizationRate(): float
    {
        $totalBins = Bin::where('is_active', true)->count();
        
        if ($totalBins === 0) return 0;

        $totalUtilization = Bin::where('is_active', true)->sum('utilization_rate');
        return round($totalUtilization / $totalBins, 1);
    }

    /**
     * Get product stock status
     */
    private function getProductStockStatus($product): string
    {
        if ($product->available_quantity == 0) {
            return 'out_of_stock';
        } elseif ($product->available_quantity <= $product->minimum_stock_level) {
            return 'low';
        } elseif ($product->available_quantity >= $product->maximum_stock_level) {
            return 'overstocked';
        } else {
            return 'adequate';
        }
    }

    /**
     * Get bin status level
     */
    private function getBinStatusLevel($bin): string
    {
        if ($bin->current_stock_count == 0) {
            return 'empty';
        } elseif ($bin->current_stock_count <= 3) {
            return 'critical';
        } elseif ($bin->utilization_rate >= 80) {
            return 'full';
        } elseif ($bin->utilization_rate >= 60) {
            return 'good';
        } else {
            return 'low';
        }
    }

    /**
     * Calculate days until low stock
     */
    private function calculateDaysUntilLow($product): int
    {
        // This would typically use historical movement data
        // For now, we'll use a simple calculation
        $dailyUsage = 1; // Assume 1 unit per day
        return max(0, floor(($product->available_quantity - $product->minimum_stock_level) / $dailyUsage));
    }

    /**
     * Get aging category
     */
    private function getAgingCategory(int $days): string
    {
        if ($days > 90) return 'very_old';
        if ($days > 60) return 'old';
        if ($days > 30) return 'aging';
        return 'recent';
    }
} 
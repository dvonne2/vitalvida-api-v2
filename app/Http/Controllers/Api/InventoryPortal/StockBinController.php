<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Bin;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\InventoryItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class StockBinController extends Controller
{
    /**
     * Get all bins with their current status
     */
    public function getAllBins(Request $request)
    {
        try {
            $bins = Bin::with(['products', 'currentStock'])
                ->orderBy('name')
                ->get()
                ->map(function ($bin) {
                    $bin->utilization_percentage = $this->calculateBinUtilization($bin);
                    $bin->available_capacity = $bin->capacity - $bin->currentStock->sum('quantity');
                    return $bin;
                });

            return response()->json([
                'success' => true,
                'data' => $bins
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bins',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bin details with stock information
     */
    public function getBinDetails(Request $request, $binId)
    {
        try {
            $bin = Bin::with(['products', 'currentStock.product', 'stockMovements'])
                ->findOrFail($binId);

            $bin->utilization_percentage = $this->calculateBinUtilization($bin);
            $bin->available_capacity = $bin->capacity - $bin->currentStock->sum('quantity');
            $bin->recent_movements = $bin->stockMovements()
                ->with(['product', 'user'])
                ->orderBy('created_at', 'desc')
                ->limit(10)
                ->get();

            return response()->json([
                'success' => true,
                'data' => $bin
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch bin details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new bin
     */
    public function createBin(Request $request)
    {
        try {
            $request->validate([
                'name' => 'required|string|max:255|unique:bins,name',
                'location' => 'required|string|max:255',
                'capacity' => 'required|numeric|min:1',
                'description' => 'nullable|string',
                'bin_type' => 'required|in:storage,picking,overflow,quarantine',
                'zone' => 'nullable|string|max:100'
            ]);

            $bin = Bin::create($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Bin created successfully',
                'data' => $bin
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update bin information
     */
    public function updateBin(Request $request, $binId)
    {
        try {
            $bin = Bin::findOrFail($binId);

            $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:bins,name,' . $binId,
                'location' => 'sometimes|required|string|max:255',
                'capacity' => 'sometimes|required|numeric|min:1',
                'description' => 'nullable|string',
                'bin_type' => 'sometimes|required|in:storage,picking,overflow,quarantine',
                'zone' => 'nullable|string|max:100',
                'status' => 'sometimes|required|in:active,inactive,maintenance'
            ]);

            $bin->update($request->all());

            return response()->json([
                'success' => true,
                'message' => 'Bin updated successfully',
                'data' => $bin
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete a bin (if empty)
     */
    public function deleteBin(Request $request, $binId)
    {
        try {
            $bin = Bin::findOrFail($binId);

            // Check if bin has any stock
            if ($bin->currentStock->sum('quantity') > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot delete bin with existing stock'
                ], 400);
            }

            $bin->delete();

            return response()->json([
                'success' => true,
                'message' => 'Bin deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete bin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Add stock to a bin
     */
    public function addStockToBin(Request $request, $binId)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric|min:1',
                'batch_number' => 'nullable|string|max:100',
                'expiry_date' => 'nullable|date',
                'notes' => 'nullable|string'
            ]);

            $bin = Bin::findOrFail($binId);

            // Check bin capacity
            $currentStock = $bin->currentStock->sum('quantity');
            if (($currentStock + $request->quantity) > $bin->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient bin capacity'
                ], 400);
            }

            // Add stock to bin
            $stockItem = InventoryItem::create([
                'bin_id' => $binId,
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'batch_number' => $request->batch_number,
                'expiry_date' => $request->expiry_date,
                'notes' => $request->notes
            ]);

            // Log movement
            StockMovement::create([
                'bin_id' => $binId,
                'product_id' => $request->product_id,
                'movement_type' => 'in',
                'quantity' => $request->quantity,
                'user_id' => auth()->id(),
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock added to bin successfully',
                'data' => $stockItem
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add stock to bin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove stock from a bin
     */
    public function removeStockFromBin(Request $request, $binId)
    {
        try {
            $request->validate([
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric|min:1',
                'reason' => 'required|in:sale,damage,expiry,transfer,adjustment',
                'destination_bin_id' => 'nullable|exists:bins,id',
                'notes' => 'nullable|string'
            ]);

            $bin = Bin::findOrFail($binId);

            // Check available stock
            $availableStock = $bin->currentStock()
                ->where('product_id', $request->product_id)
                ->sum('quantity');

            if ($availableStock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in bin'
                ], 400);
            }

            // Remove stock from bin
            $this->removeStockFromBinLogic($binId, $request->product_id, $request->quantity);

            // Log movement
            StockMovement::create([
                'bin_id' => $binId,
                'product_id' => $request->product_id,
                'movement_type' => 'out',
                'quantity' => $request->quantity,
                'user_id' => auth()->id(),
                'reason' => $request->reason,
                'destination_bin_id' => $request->destination_bin_id,
                'notes' => $request->notes
            ]);

            // If transferring to another bin, add stock there
            if ($request->destination_bin_id) {
                $this->addStockToBinLogic($request->destination_bin_id, $request->product_id, $request->quantity);
            }

            return response()->json([
                'success' => true,
                'message' => 'Stock removed from bin successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove stock from bin',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Transfer stock between bins
     */
    public function transferStock(Request $request)
    {
        try {
            $request->validate([
                'source_bin_id' => 'required|exists:bins,id',
                'destination_bin_id' => 'required|exists:bins,id|different:source_bin_id',
                'product_id' => 'required|exists:products,id',
                'quantity' => 'required|numeric|min:1',
                'notes' => 'nullable|string'
            ]);

            $sourceBin = Bin::findOrFail($request->source_bin_id);
            $destinationBin = Bin::findOrFail($request->destination_bin_id);

            // Check source bin capacity
            $availableStock = $sourceBin->currentStock()
                ->where('product_id', $request->product_id)
                ->sum('quantity');

            if ($availableStock < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in source bin'
                ], 400);
            }

            // Check destination bin capacity
            $destinationCurrentStock = $destinationBin->currentStock->sum('quantity');
            if (($destinationCurrentStock + $request->quantity) > $destinationBin->capacity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient capacity in destination bin'
                ], 400);
            }

            // Perform transfer
            $this->removeStockFromBinLogic($request->source_bin_id, $request->product_id, $request->quantity);
            $this->addStockToBinLogic($request->destination_bin_id, $request->product_id, $request->quantity);

            // Log movements
            StockMovement::create([
                'bin_id' => $request->source_bin_id,
                'product_id' => $request->product_id,
                'movement_type' => 'out',
                'quantity' => $request->quantity,
                'user_id' => auth()->id(),
                'reason' => 'transfer',
                'destination_bin_id' => $request->destination_bin_id,
                'notes' => $request->notes
            ]);

            StockMovement::create([
                'bin_id' => $request->destination_bin_id,
                'product_id' => $request->product_id,
                'movement_type' => 'in',
                'quantity' => $request->quantity,
                'user_id' => auth()->id(),
                'reason' => 'transfer',
                'source_bin_id' => $request->source_bin_id,
                'notes' => $request->notes
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Stock transferred successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to transfer stock',
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
            $bins = Bin::with(['currentStock'])
                ->get()
                ->map(function ($bin) {
                    $currentStock = $bin->currentStock->sum('quantity');
                    $utilization = $this->calculateBinUtilization($bin);
                    
                    return [
                        'bin' => $bin,
                        'current_stock' => $currentStock,
                        'available_capacity' => $bin->capacity - $currentStock,
                        'utilization_percentage' => $utilization,
                        'status' => $this->getBinStatus($utilization),
                        'products_count' => $bin->currentStock->count()
                    ];
                })
                ->sortByDesc('utilization_percentage');

            $summary = [
                'total_bins' => $bins->count(),
                'active_bins' => $bins->where('bin.status', 'active')->count(),
                'high_utilization_bins' => $bins->where('utilization_percentage', '>=', 80)->count(),
                'low_utilization_bins' => $bins->where('utilization_percentage', '<=', 20)->count(),
                'average_utilization' => $bins->avg('utilization_percentage')
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
                'message' => 'Failed to fetch bin utilization report',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bin optimization suggestions
     */
    public function getBinOptimizationSuggestions(Request $request)
    {
        try {
            $suggestions = [];

            // Find underutilized bins
            $underutilizedBins = Bin::with(['currentStock'])
                ->where('status', 'active')
                ->get()
                ->filter(function ($bin) {
                    return $this->calculateBinUtilization($bin) < 30;
                });

            if ($underutilizedBins->count() > 0) {
                $suggestions[] = [
                    'type' => 'consolidation',
                    'title' => 'Consolidate Underutilized Bins',
                    'description' => 'Consider consolidating stock from underutilized bins to free up space',
                    'bins' => $underutilizedBins->pluck('name'),
                    'priority' => 'medium'
                ];
            }

            // Find overutilized bins
            $overutilizedBins = Bin::with(['currentStock'])
                ->where('status', 'active')
                ->get()
                ->filter(function ($bin) {
                    return $this->calculateBinUtilization($bin) > 90;
                });

            if ($overutilizedBins->count() > 0) {
                $suggestions[] = [
                    'type' => 'expansion',
                    'title' => 'Expand Overutilized Bins',
                    'description' => 'Consider redistributing stock from overutilized bins',
                    'bins' => $overutilizedBins->pluck('name'),
                    'priority' => 'high'
                ];
            }

            // Find bins with expiring products
            $expiringBins = Bin::with(['currentStock' => function($query) {
                $query->where('expiry_date', '<=', Carbon::now()->addDays(30));
            }])
            ->whereHas('currentStock', function($query) {
                $query->where('expiry_date', '<=', Carbon::now()->addDays(30));
            })
            ->get();

            if ($expiringBins->count() > 0) {
                $suggestions[] = [
                    'type' => 'expiry',
                    'title' => 'Handle Expiring Products',
                    'description' => 'Products in these bins are expiring soon',
                    'bins' => $expiringBins->pluck('name'),
                    'priority' => 'critical'
                ];
            }

            return response()->json([
                'success' => true,
                'data' => $suggestions
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch optimization suggestions',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bin movement history
     */
    public function getBinMovementHistory(Request $request, $binId)
    {
        try {
            $bin = Bin::findOrFail($binId);

            $movements = StockMovement::where('bin_id', $binId)
                ->with(['product', 'user', 'sourceBin', 'destinationBin'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'data' => $movements
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch movement history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Helper methods

    private function calculateBinUtilization($bin)
    {
        $currentStock = $bin->currentStock->sum('quantity');
        return $bin->capacity > 0 ? round(($currentStock / $bin->capacity) * 100, 2) : 0;
    }

    private function getBinStatus($utilization)
    {
        if ($utilization >= 90) return 'critical';
        if ($utilization >= 80) return 'high';
        if ($utilization >= 50) return 'medium';
        if ($utilization >= 20) return 'low';
        return 'empty';
    }

    private function removeStockFromBinLogic($binId, $productId, $quantity)
    {
        $stockItems = InventoryItem::where('bin_id', $binId)
            ->where('product_id', $productId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at')
            ->get();

        $remainingQuantity = $quantity;

        foreach ($stockItems as $item) {
            if ($remainingQuantity <= 0) break;

            $toRemove = min($remainingQuantity, $item->quantity);
            $item->quantity -= $toRemove;
            $remainingQuantity -= $toRemove;

            if ($item->quantity <= 0) {
                $item->delete();
            } else {
                $item->save();
            }
        }
    }

    private function addStockToBinLogic($binId, $productId, $quantity)
    {
        // Try to find existing stock item with same product
        $existingItem = InventoryItem::where('bin_id', $binId)
            ->where('product_id', $productId)
            ->first();

        if ($existingItem) {
            $existingItem->quantity += $quantity;
            $existingItem->save();
        } else {
            InventoryItem::create([
                'bin_id' => $binId,
                'product_id' => $productId,
                'quantity' => $quantity
            ]);
        }
    }
} 
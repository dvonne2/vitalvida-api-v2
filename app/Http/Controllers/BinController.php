<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Bin;
use App\Models\BinItem;
use App\Models\InventoryLog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class BinController extends Controller
{
    public function index(Request $request)
    {
        $query = Bin::with('items');

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('type')) {
            $query->where('type', $request->type);
        }

        $bins = $query->paginate(20);
        return response()->json($bins);
    }

    public function show($id)
    {
        $bin = Bin::with('items')->findOrFail($id);
        
        return response()->json([
            'bin' => $bin,
            'current_capacity' => $bin->getCurrentCapacity(),
            'available_capacity' => $bin->getAvailableCapacity(),
        ]);
    }

    public function assignDeliveryAgent(Request $request, $binId)
    {
        $request->validate([
            'assigned_to_da' => 'required|string',
            'da_phone' => 'required|string',
        ]);

        $bin = Bin::findOrFail($binId);
        
        // FRAUD PROTECTION: Check if bin has any stock before allowing assignment
        $hasStock = $bin->items()->where('quantity', '>', 0)->exists();
        if (!$hasStock) {
            return response()->json([
                'error' => 'Cannot assign delivery agent to bin without logged inventory',
                'message' => 'Stock must be logged before DA assignment per fraud protection policy'
            ], 400);
        }

        // FRAUD PROTECTION: Check if all stock movements are logged
        $unloggedStock = $this->checkUnloggedStock($bin);
        if ($unloggedStock > 0) {
            return response()->json([
                'error' => 'Unlogged stock detected',
                'message' => 'All stock movements must be logged before DA assignment',
                'unlogged_quantity' => $unloggedStock
            ], 400);
        }

        $oldAgent = $bin->assigned_to_da;
        
        $bin->update([
            'assigned_to_da' => $request->assigned_to_da,
            'da_phone' => $request->da_phone,
            'type' => 'delivery_agent',
        ]);

        // AUDIT LOG: Log the assignment change
        InventoryLog::create([
            'action' => 'agent_assignment',
            'bin_location' => $bin->name,
            'user_id' => Auth::id() ?? 1,
            'notes' => "Delivery agent changed from '{$oldAgent}' to '{$request->assigned_to_da}'",
            'metadata' => [
                'bin_id' => $bin->id,
                'old_agent' => $oldAgent,
                'new_agent' => $request->assigned_to_da,
                'new_phone' => $request->da_phone
            ]
        ]);

        return response()->json([
            'message' => '✅ Delivery agent assigned successfully',
            'bin' => $bin,
            'logged' => true,
        ]);
    }

    public function deductInventory(Request $request, $binId)
    {
        $request->validate([
            'item_id' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string',
            'user_id' => 'nullable|integer',
        ]);

        $bin = Bin::findOrFail($binId);
        $binItem = BinItem::where('bin_id', $binId)
            ->where('item_id', $request->item_id)
            ->first();

        if (!$binItem) {
            return response()->json([
                'error' => 'Item not found in this bin'
            ], 404);
        }

        if ($binItem->getAvailableQuantity() < $request->quantity) {
            return response()->json([
                'error' => 'Insufficient quantity available',
                'available' => $binItem->getAvailableQuantity(),
                'requested' => $request->quantity,
            ], 400);
        }

        return DB::transaction(function () use ($binItem, $request, $bin) {
            $quantityBefore = $binItem->quantity;
            
            // Deduct the inventory
            $success = $binItem->deductQuantity($request->quantity);
            
            if (!$success) {
                throw new \Exception('Failed to deduct inventory');
            }

            $quantityAfter = $binItem->fresh()->quantity;

            // MANDATORY AUDIT LOG - No stock movement without logs
            InventoryLog::create([
                'zoho_item_id' => $binItem->item_id,
                'item_name' => $binItem->item_name,
                'sku' => $binItem->item_id,
                'action' => 'deduction',
                'quantity' => $request->quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'bin_location' => $bin->name,
                'user_id' => $request->user_id ?? Auth::id() ?? 1,
                'notes' => $request->reason,
                'metadata' => [
                    'bin_id' => $bin->id,
                    'bin_item_id' => $binItem->id,
                    'deduction_reason' => $request->reason,
                    'timestamp' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'message' => '✅ Inventory deducted and logged successfully',
                'remaining_quantity' => $quantityAfter,
                'logged' => true,
            ]);
        });
    }

    public function addInventory(Request $request, $binId)
    {
        $request->validate([
            'item_id' => 'required|string',
            'item_name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'cost_per_unit' => 'nullable|numeric|min:0',
            'user_id' => 'nullable|integer',
            'notes' => 'nullable|string',
        ]);

        $bin = Bin::findOrFail($binId);

        // Check capacity
        if ($bin->max_capacity && !$bin->canAccommodate($request->quantity)) {
            return response()->json([
                'error' => 'Bin capacity exceeded',
                'max_capacity' => $bin->max_capacity,
                'current_capacity' => $bin->getCurrentCapacity(),
                'available_capacity' => $bin->getAvailableCapacity(),
            ], 400);
        }

        return DB::transaction(function () use ($request, $binId, $bin) {
            $binItem = BinItem::where('bin_id', $binId)
                ->where('item_id', $request->item_id)
                ->first();

            $quantityBefore = $binItem ? $binItem->quantity : 0;

            $binItem = BinItem::updateOrCreate(
                [
                    'bin_id' => $binId,
                    'item_id' => $request->item_id,
                ],
                [
                    'item_name' => $request->item_name,
                    'cost_per_unit' => $request->cost_per_unit ?? 0,
                ]
            );

            $binItem->increment('quantity', $request->quantity);
            $quantityAfter = $binItem->fresh()->quantity;

            // MANDATORY AUDIT LOG - No stock movement without logs
            InventoryLog::create([
                'zoho_item_id' => $request->item_id,
                'item_name' => $request->item_name,
                'sku' => $request->item_id,
                'action' => 'addition',
                'quantity' => $request->quantity,
                'quantity_before' => $quantityBefore,
                'quantity_after' => $quantityAfter,
                'bin_location' => $bin->name,
                'user_id' => $request->user_id ?? Auth::id() ?? 1,
                'notes' => $request->notes ?? 'Inventory addition',
                'metadata' => [
                    'bin_id' => $bin->id,
                    'bin_item_id' => $binItem->id,
                    'cost_per_unit' => $request->cost_per_unit,
                    'timestamp' => now()->toISOString()
                ]
            ]);

            return response()->json([
                'message' => '✅ Inventory added and logged successfully',
                'bin_item' => $binItem->fresh(),
                'logged' => true,
            ]);
        });
    }

    public function getAuditLogs($binId)
    {
        $bin = Bin::findOrFail($binId);
        
        $logs = InventoryLog::where('bin_location', $bin->name)
            ->orderBy('created_at', 'desc')
            ->paginate(50);

        return response()->json([
            'bin' => $bin,
            'audit_logs' => $logs,
        ]);
    }

    // FRAUD PROTECTION: Check for unlogged stock
    private function checkUnloggedStock(Bin $bin)
    {
        $totalBinQuantity = $bin->items()->sum('quantity');
        $totalLoggedAdditions = InventoryLog::where('bin_location', $bin->name)
            ->where('action', 'addition')
            ->sum('quantity');
        $totalLoggedDeductions = InventoryLog::where('bin_location', $bin->name)
            ->where('action', 'deduction')
            ->sum('quantity');

        $expectedQuantity = $totalLoggedAdditions - $totalLoggedDeductions;
        return max(0, $totalBinQuantity - $expectedQuantity);
    }

    public function validateBinIntegrity($binId)
    {
        $bin = Bin::findOrFail($binId);
        $unloggedStock = $this->checkUnloggedStock($bin);
        
        return response()->json([
            'bin' => $bin->name,
            'current_stock' => $bin->getCurrentCapacity(),
            'unlogged_stock' => $unloggedStock,
            'integrity_status' => $unloggedStock > 0 ? 'VIOLATION' : 'CLEAN',
            'can_assign_da' => $unloggedStock === 0
        ]);
    }
}

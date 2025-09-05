<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDAReturnRequest;
use App\Http\Requests\StoreFactoryReturnRequest;
use App\Models\BinStock;
use App\Models\InventoryMovement;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnController extends Controller
{
    /**
     * Process DA Return (Delivery Agent returns items to IM)
     */
    public function storeDAReturn(StoreDAReturnRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $fromBinId = $request->delivery_agent_id; // DA bin ID
            $toBinId = 1; // IM bin ID (assuming bin 1 is IM)

            // Check if stock exists in DA bin
            $fromBinStock = BinStock::where('bin_id', $fromBinId)
                                   ->where('product_id', $request->product_id)
                                   ->first();

            if (!$fromBinStock || $fromBinStock->quantity < $request->quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in DA bin',
                    'available_quantity' => $fromBinStock ? $fromBinStock->quantity : 0
                ], 400);
            }

            // Decrease stock from DA bin
            $fromBinStock->decrement('quantity', $request->quantity);

            // Increase stock in IM bin
            BinStock::updateOrCreate(
                ['bin_id' => $toBinId, 'product_id' => $request->product_id],
                ['quantity' => 0] // Default quantity if creating new record
            )->increment('quantity', $request->quantity);

            // Create inventory movement record
            $movement = InventoryMovement::create([
                'product_id' => $request->product_id,
                'from_bin_id' => $fromBinId,
                'to_bin_id' => $toBinId,
                'quantity' => $request->quantity,
                'movement_type' => 'da_return',
                'reason' => $request->reason,
                'status' => 'completed'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'DA return processed successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DA Return Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing DA return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Process Factory Return (Items returned to factory from IM)
     */
    public function storeFactoryReturn(StoreFactoryReturnRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            $fromBinId = 1; // IM bin ID
            // Use a special bin ID for factory (e.g., 999) instead of null
            $toBinId = 999; // Factory bin ID (virtual bin representing items leaving inventory)

            // Check if stock exists in IM bin
            $fromBinStock = BinStock::where('bin_id', $fromBinId)
                                   ->where('product_id', $request->product_id)
                                   ->first();

            if (!$fromBinStock || $fromBinStock->quantity < $request->quantity) {
                DB::rollBack();
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in IM bin',
                    'available_quantity' => $fromBinStock ? $fromBinStock->quantity : 0
                ], 400);
            }

            // Decrease stock from IM bin
            $fromBinStock->decrement('quantity', $request->quantity);

            // Create inventory movement record
            $movement = InventoryMovement::create([
                'product_id' => $request->product_id,
                'from_bin_id' => $fromBinId,
                'to_bin_id' => $toBinId, // Use special factory bin ID instead of null
                'quantity' => $request->quantity,
                'movement_type' => 'return_to_factory',
                'reason' => $request->reason,
                'status' => 'completed'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'data' => $movement,
                'message' => 'Factory return processed successfully'
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Factory Return Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error processing factory return',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get return statistics
     */
    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'da_returns' => InventoryMovement::where('movement_type', 'da_return')->count(),
                'factory_returns' => InventoryMovement::where('movement_type', 'return_to_factory')->count(),
                'total_da_return_quantity' => InventoryMovement::where('movement_type', 'da_return')->sum('quantity'),
                'total_factory_return_quantity' => InventoryMovement::where('movement_type', 'return_to_factory')->sum('quantity'),
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ]);
            
        } catch (\Exception $e) {
            Log::error('Return Stats Error: ' . $e->getMessage());
            
            return response()->json([
                'success' => false,
                'message' => 'Error retrieving return statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\InventoryTransferRequest;
use App\Models\Bin;
use App\Models\Product;
use App\Models\InventoryMovement;
use App\Models\BinContent;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class InventoryTransferController extends Controller
{
    /**
     * Create a new inventory transfer
     * Frontend form posts to this endpoint
     */
    public function store(InventoryTransferRequest $request): JsonResponse
    {
        try {
            DB::beginTransaction();

            // 1. Get source and destination bins
            $sourceBin = Bin::findOrFail($request->source_bin_id);
            $destinationBin = Bin::findOrFail($request->destination_bin_id);
            $product = Product::findOrFail($request->product_id);

            // 2. Check if source bin has sufficient quantity
            $sourceBinContent = BinContent::where('bin_id', $sourceBin->id)
                ->where('product_id', $product->id)
                ->first();

            if (!$sourceBinContent || $sourceBinContent->quantity < $request->quantity) {
                return response()->json([
                    'success' => false,
                    'message' => 'Insufficient stock in source bin. Available: ' . 
                        ($sourceBinContent->quantity ?? 0) . ', Requested: ' . $request->quantity
                ], 422);
            }

            // 3. Create the inventory movement record
            $movement = InventoryMovement::create([
                'source_bin_id' => $sourceBin->id,
                'destination_bin_id' => $destinationBin->id,
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'notes' => $request->notes,
                'movement_type' => 'transfer',
                'status' => 'completed',
                'created_by' => auth()->id() ?? 1, // Default to user 1 if no auth
            ]);

            // 4. Update bin contents
            $this->updateBinContents($sourceBin, $destinationBin, $product, $request->quantity);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Inventory transfer created successfully',
                'data' => [
                    'movement' => $movement->load(['sourceBin', 'destinationBin', 'product']),
                    'source_bin' => $sourceBin->load('contents'),
                    'destination_bin' => $destinationBin->load('contents')
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollback();
            
            Log::error('Inventory transfer failed', [
                'error' => $e->getMessage(),
                'request' => $request->all()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Transfer failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update local bin contents after successful transfer
     */
    private function updateBinContents(Bin $sourceBin, Bin $destinationBin, Product $product, int $quantity): void
    {
        // Reduce from source bin
        $sourceBinContent = BinContent::where('bin_id', $sourceBin->id)
            ->where('product_id', $product->id)
            ->first();
        
        if ($sourceBinContent) {
            $sourceBinContent->decrement('quantity', $quantity);
            $sourceBinContent->update(['last_updated' => now()]);
        }

        // Add to destination bin
        BinContent::updateOrCreate(
            [
                'bin_id' => $destinationBin->id,
                'product_id' => $product->id
            ],
            [
                'quantity' => DB::raw("COALESCE(quantity, 0) + {$quantity}"),
                'unit_cost' => $sourceBinContent->unit_cost ?? 0,
                'last_updated' => now()
            ]
        );
    }
}

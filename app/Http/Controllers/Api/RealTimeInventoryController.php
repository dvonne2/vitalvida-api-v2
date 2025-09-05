<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Services\ZohoService;
use App\Models\Bin;
use App\Models\Product;
use App\Models\InventoryMovement;

class RealTimeInventoryController extends Controller
{
    private $zohoService;

    public function __construct(ZohoService $zohoService)
    {
        $this->zohoService = $zohoService;
    }

    /**
     * Phase 7: Real-time deduction when packages are created
     */
    public function deductForPackage(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'bin_id' => 'required|exists:bins,id',
            'package_id' => 'nullable|string',
            'notes' => 'nullable|string'
        ]);

        try {
            $product = Product::findOrFail($request->product_id);
            $bin = Bin::findOrFail($request->bin_id);

            // Execute real-time deduction using Zoho bin API
            $result = $this->executeZohoDeduction($product, $bin, $request->quantity);

            return response()->json([
                'success' => true,
                'message' => 'Inventory deducted successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to deduct inventory: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Phase 8: Add stock to a specific bin via portal
     */
    public function addToBin(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'bin_id' => 'required|exists:bins,id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string'
        ]);

        try {
            $product = Product::findOrFail($request->product_id);
            $bin = Bin::findOrFail($request->bin_id);

            // Execute addition using Zoho bin API
            $result = $this->executeZohoAddition($product, $bin, $request->quantity, $request->reason);

            return response()->json([
                'success' => true,
                'message' => 'Stock added successfully',
                'data' => $result
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to add stock: ' . $e->getMessage()
            ], 500);
        }
    }

    private function executeZohoDeduction($product, $bin, $quantity)
    {
        // Use Zoho adjustment API with bin location structure from PDF
        $adjustmentData = [
            'date' => now()->format('Y-m-d'),
            'reason' => 'package_creation',
            'line_items' => [
                [
                    'item_id' => $product->zoho_item_id,
                    'quantity_adjusted' => $quantity,
                    'storages' => [
                        [
                            'storage_id' => $bin->zoho_storage_id,
                            'out_quantity' => $quantity
                        ]
                    ]
                ]
            ]
        ];

        return $this->zohoService->makeApiRequest('inventoryadjustments', 'POST', array_merge($adjustmentData, [
            'organization_id' => config('services.zoho.organization_id')
        ]));
    }

    private function executeZohoAddition($product, $bin, $quantity, $reason)
    {
        // Use Zoho adjustment API for stock addition
        $adjustmentData = [
            'date' => now()->format('Y-m-d'),
            'reason' => $reason,
            'line_items' => [
                [
                    'item_id' => $product->zoho_item_id,
                    'quantity_adjusted' => $quantity,
                    'storages' => [
                        [
                            'storage_id' => $bin->zoho_storage_id,
                            'in_quantity' => $quantity
                        ]
                    ]
                ]
            ]
        ];

        return $this->zohoService->makeApiRequest('inventoryadjustments', 'POST', array_merge($adjustmentData, [
            'organization_id' => config('services.zoho.organization_id')
        ]));
    }
}

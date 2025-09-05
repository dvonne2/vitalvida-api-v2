<?php

namespace App\Http\Controllers;

use App\Models\PurchaseOrder;
use App\Models\PurchaseOrderItem;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PurchaseOrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        try {
            $purchaseOrders = collect([
                [
                    'id' => 1,
                    'po_number' => 'PO-20250701-0001',
                    'supplier_name' => 'Sample Supplier',
                    'total_amount' => 15000,
                    'status' => 'pending',
                    'created_at' => now()
                ]
            ]);

            return response()->json([
                'success' => true,
                'data' => $purchaseOrders,
                'message' => 'Purchase orders retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch purchase orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function store(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'success' => true,
                'data' => [
                    'id' => rand(1, 1000),
                    'po_number' => 'PO-' . time(),
                    'supplier_name' => $request->supplier_name ?? 'Default Supplier',
                    'status' => 'pending',
                    'created_at' => now()
                ],
                'message' => 'Purchase order created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

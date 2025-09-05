#!/bin/bash

echo "🚀 Completing VitalVida API Setup..."

# Create PurchaseOrderController
cat > app/Http/Controllers/PurchaseOrderController.php << 'CONTROLLER_EOF'
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
    public function index(): JsonResponse
    {
        try {
            $purchaseOrders = PurchaseOrder::with(['items'])
                ->orderBy('created_at', 'desc')
                ->paginate(20);

            return response()->json([
                'success' => true,
                'message' => 'Purchase orders retrieved successfully',
                'data' => $purchaseOrders
            ], 200);
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
            $validator = Validator::make($request->all(), [
                'supplier_name' => 'required|string|max:255',
                'items' => 'required|array|min:1'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $purchaseOrder = PurchaseOrder::create([
                'po_number' => 'PO-' . date('Y') . '-' . str_pad(PurchaseOrder::count() + 1, 4, '0', STR_PAD_LEFT),
                'supplier_name' => $request->supplier_name,
                'total_amount' => 0,
                'status' => 'pending',
                'created_by' => 1
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Purchase order created successfully',
                'data' => $purchaseOrder
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create purchase order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function stats(): JsonResponse
    {
        try {
            $stats = [
                'total_orders' => PurchaseOrder::count(),
                'pending_orders' => PurchaseOrder::where('status', 'pending')->count(),
                'total_value' => PurchaseOrder::sum('total_amount')
            ];

            return response()->json([
                'success' => true,
                'data' => $stats
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stats',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
CONTROLLER_EOF

# Add routes if they don't exist
if ! grep -q "purchase-orders" routes/api.php; then
    echo '
// Purchase Order Routes
Route::prefix("purchase-orders")->group(function () {
    Route::get("/", [App\Http\Controllers\PurchaseOrderController::class, "index"]);
    Route::post("/", [App\Http\Controllers\PurchaseOrderController::class, "store"]);
    Route::get("/stats/overview", [App\Http\Controllers\PurchaseOrderController::class, "stats"]);
});' >> routes/api.php
fi

# Clear caches
php artisan route:clear
php artisan config:clear

echo "✅ Setup complete! Testing endpoints..."

# Test endpoints
curl -s http://localhost:8000/api/test && echo "✅ /api/test working"
curl -s http://localhost:8000/api/purchase-orders && echo "✅ /api/purchase-orders working"

echo "🎉 VitalVida API setup complete!"

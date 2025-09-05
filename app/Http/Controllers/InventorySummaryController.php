<?php

namespace App\Http\Controllers;

use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventorySummaryController extends Controller
{
    public function index(): JsonResponse
    {
        $movementSummary = InventoryMovement::select('movement_type')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(quantity) as total_quantity')
            ->selectRaw('SUM(total_cost) as total_value')
            ->where('approval_status', 'approved')
            ->groupBy('movement_type')
            ->get();

        $approvalStats = InventoryMovement::select('approval_status')
            ->selectRaw('COUNT(*) as count')
            ->selectRaw('SUM(total_cost) as total_value')
            ->groupBy('approval_status')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'movement_summary' => $movementSummary,
                'approval_statistics' => $approvalStats,
                'total_movements' => InventoryMovement::count(),
                'pending_approvals' => InventoryMovement::where('approval_status', 'pending')->count(),
                'total_value' => InventoryMovement::where('approval_status', 'approved')->sum('total_cost')
            ],
            'message' => 'Inventory summary retrieved successfully'
        ]);
    }

    public function productTotals(): JsonResponse
    {
        $productTotals = DB::table('inventory_movements')
            ->select('product_id')
            ->selectRaw('SUM(CASE WHEN movement_type IN ("da_to_da", "receiving") THEN quantity ELSE 0 END) as total_in')
            ->selectRaw('SUM(CASE WHEN movement_type IN ("da_to_bins", "shipping") THEN quantity ELSE 0 END) as total_out')
            ->selectRaw('COUNT(*) as total_movements')
            ->where('approval_status', 'approved')
            ->groupBy('product_id')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $productTotals,
            'message' => 'Product totals retrieved successfully'
        ]);
    }
}

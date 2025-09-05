<?php

namespace App\Http\Controllers;

use App\Services\InventoryMovementService;
use App\Models\InventoryMovement;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class InventoryMovementController extends Controller
{
    private $movementService;

    public function __construct(InventoryMovementService $movementService)
    {
        $this->movementService = $movementService;
    }

    /**
     * Get BIN movement history
     */
    public function getBinMovements(string $binId, Request $request): JsonResponse
    {
        $filters = [
            'movement_type' => $request->get('movement_type'),
            'source_type' => $request->get('source_type'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'item_id' => $request->get('item_id'),
            'per_page' => $request->get('per_page', 20)
        ];

        $movements = $this->movementService->getBinMovementHistory($binId, $filters);

        return response()->json([
            'success' => true,
            'bin_id' => $binId,
            'movements' => $movements
        ]);
    }

    /**
     * Get item movement history across all BINs
     */
    public function getItemMovements(string $itemId, Request $request): JsonResponse
    {
        $filters = [
            'movement_type' => $request->get('movement_type'),
            'bin_id' => $request->get('bin_id'),
            'start_date' => $request->get('start_date'),
            'end_date' => $request->get('end_date'),
            'per_page' => $request->get('per_page', 20)
        ];

        $movements = $this->movementService->getItemMovementHistory($itemId, $filters);

        return response()->json([
            'success' => true,
            'item_id' => $itemId,
            'movements' => $movements
        ]);
    }

    /**
     * Get movement summary and analytics
     */
    public function getMovementSummary(Request $request): JsonResponse
    {
        $filters = [
            'start_date' => $request->get('start_date', now()->subDays(30)),
            'end_date' => $request->get('end_date', now())
        ];

        $summary = $this->movementService->getMovementSummary($filters);

        return response()->json([
            'success' => true,
            'summary' => $summary,
            'filters' => $filters
        ]);
    }

    /**
     * Get order movement details
     */
    public function getOrderMovements(string $orderNumber): JsonResponse
    {
        $movements = InventoryMovement::where('order_number', $orderNumber)
            ->with(['user', 'binLocation'])
            ->orderBy('movement_at', 'desc')
            ->get();

        return response()->json([
            'success' => true,
            'order_number' => $orderNumber,
            'movements' => $movements
        ]);
    }

    /**
     * Get recent movements across the system
     */
    public function getRecentMovements(Request $request): JsonResponse
    {
        $limit = $request->get('limit', 50);
        
        $movements = InventoryMovement::with(['user', 'binLocation'])
            ->orderBy('movement_at', 'desc')
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'movements' => $movements
        ]);
    }
}

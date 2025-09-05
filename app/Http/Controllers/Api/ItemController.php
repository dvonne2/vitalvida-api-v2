<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Item;
use App\Models\Category;
use App\Models\InventoryHistory;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    /**
     * Display a listing of items.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Item::with(['category', 'supplier']);

        // Apply filters
        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('sku', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($request->has('status')) {
            $query->where('is_active', $request->status === 'active');
        }

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        $items = $query->paginate($request->get('per_page', 15));

        return ApiResponse::paginate($items, 'Items retrieved successfully');
    }

    /**
     * Store a newly created item.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:items',
            'category_id' => 'required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit_price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'required|integer|min:0',
            'reorder_level' => 'required|integer|min:0',
            'reorder_quantity' => 'required|integer|min:1',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id'
        ]);

        try {
            $item = Item::create($request->all());

            return ApiResponse::created($item->load(['category', 'supplier']), 'Item created successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to create item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified item.
     */
    public function show(Item $item): JsonResponse
    {
        return ApiResponse::success($item->load(['category', 'supplier', 'deliveryAgent']), 'Item retrieved successfully');
    }

    /**
     * Update the specified item.
     */
    public function update(Request $request, Item $item): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'sometimes|required|string|max:100|unique:items,sku,' . $item->id,
            'category_id' => 'sometimes|required|exists:categories,id',
            'supplier_id' => 'nullable|exists:suppliers,id',
            'unit_price' => 'sometimes|required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock_quantity' => 'sometimes|required|integer|min:0',
            'reorder_level' => 'sometimes|required|integer|min:0',
            'reorder_quantity' => 'sometimes|required|integer|min:1',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:255',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'is_active' => 'sometimes|boolean'
        ]);

        try {
            $item->update($request->all());

            return ApiResponse::success($item->load(['category', 'supplier']), 'Item updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified item.
     */
    public function destroy(Item $item): JsonResponse
    {
        try {
            $item->delete();

            return ApiResponse::success(null, 'Item deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete item: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get stock levels for the specified item.
     */
    public function stockLevels(Item $item): JsonResponse
    {
        $stockData = [
            'item' => $item->load(['category', 'supplier']),
            'current_stock' => $item->stock_quantity,
            'reorder_level' => $item->reorder_level,
            'reorder_quantity' => $item->reorder_quantity,
            'stock_status' => $item->stock_quantity <= $item->reorder_level ? 'low' : 'normal',
            'total_value' => $item->stock_quantity * $item->unit_price,
            'last_movement' => $item->inventoryHistory()->latest()->first(),
            'recent_movements' => $item->inventoryHistory()->latest()->limit(10)->get()
        ];

        return ApiResponse::success($stockData, 'Stock levels retrieved successfully');
    }

    /**
     * Get movement history for the specified item.
     */
    public function movementHistory(Item $item, Request $request): JsonResponse
    {
        $query = $item->inventoryHistory()->with(['user', 'deliveryAgent']);

        if ($request->has('start_date')) {
            $query->where('created_at', '>=', $request->start_date);
        }

        if ($request->has('end_date')) {
            $query->where('created_at', '<=', $request->end_date);
        }

        $movements = $query->orderBy('created_at', 'desc')->paginate(15);

        return ApiResponse::paginate($movements, 'Movement history retrieved successfully');
    }
} 
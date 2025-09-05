<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\Customer;
use App\Helpers\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    /**
     * Display a listing of sales.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Sale::with(['customer', 'deliveryAgent', 'employee', 'items.item']);

        // Apply filters
        if ($request->has('customer_id')) {
            $query->where('customer_id', $request->customer_id);
        }

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        if ($request->has('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->has('date_from')) {
            $query->where('date', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->where('date', '<=', $request->date_to);
        }

        $sales = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 15));

        return ApiResponse::paginate($sales, 'Sales retrieved successfully');
    }

    /**
     * Store a newly created sale.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'employee_id' => 'nullable|exists:employees,id',
            'date' => 'required|date',
            'payment_method' => 'required|in:cash,card,transfer,mobile_money,other',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'notes' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.item_id' => 'required|exists:items,id',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'items.*.modifier_id' => 'nullable|exists:modifiers,id'
        ]);

        try {
            DB::beginTransaction();

            $sale = Sale::create([
                'customer_id' => $request->customer_id,
                'delivery_agent_id' => $request->delivery_agent_id,
                'employee_id' => $request->employee_id,
                'date' => $request->date,
                'payment_method' => $request->payment_method,
                'payment_status' => $request->payment_status ?? 'pending',
                'notes' => $request->notes,
                'otp_verified' => false
            ]);

            // Create sale items
            foreach ($request->items as $itemData) {
                $total = ($itemData['quantity'] * $itemData['unit_price']) - ($itemData['discount'] ?? 0);
                
                SaleItem::create([
                    'sale_id' => $sale->id,
                    'item_id' => $itemData['item_id'],
                    'quantity' => $itemData['quantity'],
                    'unit_price' => $itemData['unit_price'],
                    'discount' => $itemData['discount'] ?? 0,
                    'total' => $total,
                    'modifier_id' => $itemData['modifier_id'] ?? null
                ]);
            }

            // Calculate totals
            $sale->calculateTotals();

            DB::commit();

            return ApiResponse::created($sale->load(['customer', 'deliveryAgent', 'employee', 'items.item']), 'Sale created successfully');
        } catch (\Exception $e) {
            DB::rollBack();
            return ApiResponse::error('Failed to create sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Display the specified sale.
     */
    public function show(Sale $sale): JsonResponse
    {
        return ApiResponse::success($sale->load(['customer', 'deliveryAgent', 'employee', 'items.item', 'receipt']), 'Sale retrieved successfully');
    }

    /**
     * Update the specified sale.
     */
    public function update(Request $request, Sale $sale): JsonResponse
    {
        $request->validate([
            'customer_id' => 'sometimes|required|exists:customers,id',
            'delivery_agent_id' => 'nullable|exists:delivery_agents,id',
            'employee_id' => 'nullable|exists:employees,id',
            'date' => 'sometimes|required|date',
            'payment_method' => 'sometimes|required|in:cash,card,transfer,mobile_money,other',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'notes' => 'nullable|string'
        ]);

        try {
            $sale->update($request->all());

            return ApiResponse::success($sale->load(['customer', 'deliveryAgent', 'employee', 'items.item']), 'Sale updated successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to update sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove the specified sale.
     */
    public function destroy(Sale $sale): JsonResponse
    {
        try {
            $sale->delete();

            return ApiResponse::success(null, 'Sale deleted successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to delete sale: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Verify sale with OTP.
     */
    public function verify(Sale $sale, Request $request): JsonResponse
    {
        $request->validate([
            'user_id' => 'required|exists:users,id'
        ]);

        try {
            if (!$sale->canVerify()) {
                return ApiResponse::error('Sale cannot be verified', 400);
            }

            $sale->verify($request->user_id);

            return ApiResponse::success($sale->load(['customer', 'deliveryAgent', 'employee', 'items.item']), 'Sale verified successfully');
        } catch (\Exception $e) {
            return ApiResponse::error('Failed to verify sale: ' . $e->getMessage(), 500);
        }
    }
} 
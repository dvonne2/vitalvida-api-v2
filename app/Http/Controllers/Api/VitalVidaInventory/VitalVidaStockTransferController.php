<?php

namespace App\Http\Controllers\Api\VitalVidaInventory;

use App\Http\Controllers\Controller;
use App\Models\VitalVidaInventory\StockTransfer;
use App\Models\VitalVidaInventory\DeliveryAgent;
use App\Models\VitalVidaInventory\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VitalVidaStockTransferController extends Controller
{
    /**
     * Get all stock transfers
     */
    public function index(Request $request): JsonResponse
    {
        $query = StockTransfer::with(['product', 'fromAgent', 'toAgent']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('from_date')) {
            $query->whereDate('created_at', '>=', $request->from_date);
        }

        if ($request->has('to_date')) {
            $query->whereDate('created_at', '<=', $request->to_date);
        }

        $transfers = $query->latest()->paginate($request->get('per_page', 15));

        $summary = [
            'total' => StockTransfer::count(),
            'completed' => StockTransfer::completed()->count(),
            'in_transit' => StockTransfer::inTransit()->count(),
            'pending' => StockTransfer::pending()->count(),
            'failed' => StockTransfer::failed()->count()
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'transfers' => $transfers,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Create new stock transfer
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required|exists:vitalvida_products,id',
            'from_agent_id' => 'required|exists:vitalvida_delivery_agents,id',
            'to_agent_id' => 'required|exists:vitalvida_delivery_agents,id|different:from_agent_id',
            'quantity' => 'required|integer|min:1',
            'reason' => 'required|string|max:255',
            'notes' => 'nullable|string|max:500'
        ]);

        $product = Product::findOrFail($request->product_id);
        $fromAgent = DeliveryAgent::findOrFail($request->from_agent_id);
        $toAgent = DeliveryAgent::findOrFail($request->to_agent_id);

        // Check if from agent has enough stock
        $fromAgentProduct = $fromAgent->products()
            ->where('product_id', $request->product_id)
            ->first();

        if (!$fromAgentProduct || $fromAgentProduct->quantity < $request->quantity) {
            return response()->json([
                'status' => 'error',
                'message' => 'Insufficient stock in source agent inventory'
            ], 400);
        }

        $transfer = StockTransfer::create([
            'transfer_id' => 'TRF' . str_pad(StockTransfer::count() + 1, 6, '0', STR_PAD_LEFT),
            'product_id' => $request->product_id,
            'from_agent_id' => $request->from_agent_id,
            'to_agent_id' => $request->to_agent_id,
            'quantity' => $request->quantity,
            'unit_price' => $product->unit_price,
            'total_value' => $request->quantity * $product->unit_price,
            'status' => 'Pending',
            'reason' => $request->reason,
            'notes' => $request->notes,
            'requested_by' => auth()->user()->name ?? 'System',
            'tracking_number' => 'TRK' . uniqid()
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Stock transfer created successfully',
            'data' => $transfer->load(['product', 'fromAgent', 'toAgent'])
        ], 201);
    }

    /**
     * Get specific stock transfer
     */
    public function show($id): JsonResponse
    {
        $transfer = StockTransfer::with(['product', 'fromAgent', 'toAgent'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $transfer
        ]);
    }

    /**
     * Update transfer status
     */
    public function updateStatus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'status' => 'required|in:Pending,In Transit,Completed,Failed',
            'notes' => 'nullable|string|max:500'
        ]);

        $transfer = StockTransfer::findOrFail($id);
        $oldStatus = $transfer->status;

        $transfer->update([
            'status' => $request->status,
            'notes' => $request->notes ? $transfer->notes . "\n" . $request->notes : $transfer->notes
        ]);

        // Handle status-specific logic
        if ($request->status === 'Completed' && $oldStatus !== 'Completed') {
            $this->processCompletedTransfer($transfer);
        } elseif ($request->status === 'In Transit' && $oldStatus === 'Pending') {
            $transfer->update(['approved_by' => auth()->user()->name ?? 'System']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Transfer status updated successfully',
            'data' => $transfer->fresh(['product', 'fromAgent', 'toAgent'])
        ]);
    }

    /**
     * Process completed transfer
     */
    private function processCompletedTransfer($transfer): void
    {
        // Update from agent inventory
        $fromAgentProduct = $transfer->fromAgent->products()
            ->where('product_id', $transfer->product_id)
            ->first();

        if ($fromAgentProduct) {
            $fromAgentProduct->quantity -= $transfer->quantity;
            $fromAgentProduct->total_value = $fromAgentProduct->quantity * $fromAgentProduct->unit_price;
            $fromAgentProduct->save();
        }

        // Update to agent inventory
        $toAgentProduct = $transfer->toAgent->products()
            ->where('product_id', $transfer->product_id)
            ->first();

        if ($toAgentProduct) {
            $toAgentProduct->quantity += $transfer->quantity;
            $toAgentProduct->total_value = $toAgentProduct->quantity * $toAgentProduct->unit_price;
            $toAgentProduct->save();
        } else {
            // Create new product entry for to agent
            $transfer->toAgent->products()->create([
                'product_id' => $transfer->product_id,
                'quantity' => $transfer->quantity,
                'unit_price' => $transfer->unit_price,
                'total_value' => $transfer->total_value,
                'assigned_date' => now(),
                'status' => 'assigned'
            ]);
        }

        // Update agent stock values
        $transfer->fromAgent->update([
            'stock_value' => $transfer->fromAgent->products()->sum('total_value')
        ]);

        $transfer->toAgent->update([
            'stock_value' => $transfer->toAgent->products()->sum('total_value')
        ]);

        // Mark transfer as completed
        $transfer->update([
            'completed_at' => now()
        ]);
    }
}

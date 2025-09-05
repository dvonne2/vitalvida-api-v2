<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\DaInventory;
use App\Models\Order;
use App\Models\Payment;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class DeliveryAgentController extends Controller
{
    /**
     * Get delivery agent bin visibility
     */
    public function binVisibility(Request $request): JsonResponse
    {
        $filter = $request->get('filter', 'all');
        
        $query = DeliveryAgent::with(['user', 'inventory', 'orders']);
        
        // Apply filters
        switch ($filter) {
            case 'stagnant':
                $query->where('last_activity_date', '<', now()->subDays(3));
                break;
            case 'zero_stock':
                $query->whereHas('inventory', function($q) {
                    $q->havingRaw('SUM(quantity) = 0');
                });
                break;
            case 'no_recent_otp':
                $query->whereDoesntHave('orders', function($q) {
                    $q->whereDate('delivered_at', today())
                      ->whereNotNull('otp_code');
                });
                break;
            case 'active':
                $query->where('status', 'active');
                break;
            case 'inactive':
                $query->where('status', 'inactive');
                break;
        }

        $deliveryAgents = $query->get()->map(function ($da) {
            $inventory = $da->inventory->groupBy('product_type');
            $lastOrder = $da->orders()->whereNotNull('otp_code')->latest('delivered_at')->first();
            
            return [
                'id' => $da->id,
                'name' => $da->user->name,
                'state' => $da->state,
                'status' => $da->status,
                'inventory' => [
                    'shampoo' => $inventory->get('shampoo', collect())->sum('quantity'),
                    'pomade' => $inventory->get('pomade', collect())->sum('quantity'),
                    'conditioner' => $inventory->get('conditioner', collect())->sum('quantity'),
                ],
                'last_otp' => $lastOrder?->otp_code,
                'last_delivery_date' => $lastOrder?->delivered_at?->format('Y-m-d'),
                'photo_uploaded' => $lastOrder?->delivery_photo_path ? true : false,
                'payment_status' => $this->calculatePaymentStatus($da),
                'days_stagnant' => $da->last_activity_date ? 
                    now()->diffInDays($da->last_activity_date) : 0,
                'performance_rating' => $da->rating ?? 0,
                'total_deliveries' => $da->total_deliveries ?? 0,
                'successful_deliveries' => $da->successful_deliveries ?? 0,
                'success_rate' => $da->success_rate ?? 0,
                'current_stock_value' => $this->calculateStockValue($da),
                'last_activity' => $da->last_activity_date?->diffForHumans() ?? 'Unknown',
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $deliveryAgents,
            'filter' => $filter,
            'summary' => $this->getBinVisibilitySummary($deliveryAgents),
        ]);
    }

    /**
     * Call delivery agent
     */
    public function callDa(Request $request, $id): JsonResponse
    {
        $request->validate([
            'call_reason' => 'required|in:restock,inventory_check,performance_issue,payment_issue,other',
            'call_notes' => 'nullable|string|max:500',
        ]);

        $da = DeliveryAgent::with('user')->findOrFail($id);
        
        // Log the call action
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'da_called',
            'previous_status' => 'active',
            'new_status' => 'active',
            'timestamp' => now(),
            'notes' => "Called DA {$da->user->name}. Reason: {$request->call_reason}. Notes: {$request->call_notes}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Call logged successfully',
            'data' => [
                'da_name' => $da->user->name,
                'da_phone' => $da->user->phone,
                'call_reason' => $request->call_reason,
                'call_notes' => $request->call_notes,
            ],
        ]);
    }

    /**
     * Stop restock for delivery agent
     */
    public function stopRestock(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $da = DeliveryAgent::findOrFail($id);
        
        // Update inventory to stop restock
        $da->inventory()->update([
            'min_stock_level' => 0,
            'reorder_point' => 0,
        ]);

        // Log the action
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'restock_stopped',
            'previous_status' => 'active',
            'new_status' => 'active',
            'timestamp' => now(),
            'notes' => "Stopped restock for DA {$da->user->name}. Reason: {$request->reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Restock stopped successfully',
            'data' => $da->fresh(['user', 'inventory']),
        ]);
    }

    /**
     * Demand proof from delivery agent
     */
    public function demandProof(Request $request, $id): JsonResponse
    {
        $request->validate([
            'proof_type' => 'required|in:inventory_photo,delivery_photo,payment_proof,location_proof',
            'deadline' => 'required|date|after:now',
            'notes' => 'nullable|string|max:500',
        ]);

        $da = DeliveryAgent::with('user')->findOrFail($id);
        
        // Log the proof demand
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'proof_demanded',
            'previous_status' => 'active',
            'new_status' => 'active',
            'timestamp' => now(),
            'notes' => "Demanded {$request->proof_type} proof from DA {$da->user->name}. Deadline: {$request->deadline}. Notes: {$request->notes}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Proof demand logged successfully',
            'data' => [
                'da_name' => $da->user->name,
                'proof_type' => $request->proof_type,
                'deadline' => $request->deadline,
                'notes' => $request->notes,
            ],
        ]);
    }

    /**
     * Get delivery agent details
     */
    public function show($id): JsonResponse
    {
        $da = DeliveryAgent::with(['user', 'inventory', 'orders' => function($q) {
            $q->whereDate('created_at', '>=', now()->subDays(30));
        }])->findOrFail($id);

        $recentDeliveries = $da->orders()->whereNotNull('delivered_at')->latest('delivered_at')->take(10)->get();
        $inventoryStatus = $this->getInventoryStatus($da);

        return response()->json([
            'status' => 'success',
            'data' => [
                'da' => $da,
                'recent_deliveries' => $recentDeliveries,
                'inventory_status' => $inventoryStatus,
                'performance_metrics' => $this->getPerformanceMetrics($da),
            ],
        ]);
    }

    /**
     * Calculate payment status for DA
     */
    private function calculatePaymentStatus($da): array
    {
        $expectedPayments = $da->orders()
            ->where('status', 'delivered')
            ->sum('total_amount');
            
        $receivedPayments = Payment::whereIn('order_id', 
            $da->orders()->pluck('id')
        )->where('status', 'confirmed')
         ->sum('amount');

        $difference = $expectedPayments - $receivedPayments;

        return [
            'expected' => $expectedPayments,
            'received' => $receivedPayments,
            'difference' => $difference,
            'formatted' => "₦" . number_format($receivedPayments) . " / ₦" . number_format($expectedPayments),
            'status' => $difference > 0 ? 'pending' : 'complete',
        ];
    }

    /**
     * Calculate stock value for DA
     */
    private function calculateStockValue($da): float
    {
        $inventory = $da->inventory;
        $totalValue = 0;

        foreach ($inventory as $item) {
            $price = match($item->product_type) {
                'shampoo' => 2500,
                'pomade' => 3000,
                'conditioner' => 2800,
                default => 0
            };
            $totalValue += $item->quantity * $price;
        }

        return $totalValue;
    }

    /**
     * Get bin visibility summary
     */
    private function getBinVisibilitySummary($deliveryAgents): array
    {
        $totalDas = $deliveryAgents->count();
        $activeDas = $deliveryAgents->where('status', 'active')->count();
        $stagnantDas = $deliveryAgents->where('days_stagnant', '>', 3)->count();
        $zeroStockDas = $deliveryAgents->filter(function($da) {
            return collect($da['inventory'])->sum() === 0;
        })->count();

        $avgSuccessRate = $deliveryAgents->avg('success_rate');
        $avgStockValue = $deliveryAgents->avg('current_stock_value');

        return [
            'total_das' => $totalDas,
            'active_das' => $activeDas,
            'stagnant_das' => $stagnantDas,
            'zero_stock_das' => $zeroStockDas,
            'avg_success_rate' => round($avgSuccessRate, 2),
            'avg_stock_value' => round($avgStockValue, 2),
        ];
    }

    /**
     * Get inventory status for DA
     */
    private function getInventoryStatus($da): array
    {
        $inventory = $da->inventory;
        $status = [];

        foreach ($inventory as $item) {
            $status[$item->product_type] = [
                'quantity' => $item->quantity,
                'min_stock_level' => $item->min_stock_level,
                'reorder_point' => $item->reorder_point,
                'days_stagnant' => $item->days_stagnant,
                'needs_restock' => $item->quantity <= $item->reorder_point,
                'is_stagnant' => $item->days_stagnant >= 5,
            ];
        }

        return $status;
    }

    /**
     * Get performance metrics for DA
     */
    private function getPerformanceMetrics($da): array
    {
        $orders = $da->orders;
        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', 'delivered')->count();
        $ghostedOrders = $orders->where('is_ghosted', true)->count();

        return [
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'ghosted_orders' => $ghostedOrders,
            'delivery_rate' => $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0,
            'ghost_rate' => $totalOrders > 0 ? round(($ghostedOrders / $totalOrders) * 100, 2) : 0,
            'avg_delivery_time' => $this->calculateAverageDeliveryTime($orders),
            'total_earnings' => $da->total_earnings ?? 0,
            'rating' => $da->rating ?? 0,
        ];
    }

    /**
     * Calculate average delivery time
     */
    private function calculateAverageDeliveryTime($orders): float
    {
        if ($orders->count() === 0) {
            return 0;
        }
        
        $totalTime = $orders->sum(function ($order) {
            if ($order->assigned_at && $order->delivered_at) {
                return $order->assigned_at->diffInMinutes($order->delivered_at);
            }
            return 0;
        });
        
        return $totalTime / $orders->count();
    }

    // TELESALES PORTAL METHODS

    // GET /api/delivery-agents/available
    public function getAvailableAgents(Request $request): JsonResponse
    {
        $location = $request->get('location');
        $productRequirements = $request->get('products', []);
        
        $agents = DeliveryAgent::where('status', 'active')
            ->when($location, function($query) use ($location) {
                return $query->where('location', 'like', "%$location%")
                    ->orWhere('territory', 'like', "%$location%");
            })
            ->get()
            ->filter(function($agent) use ($productRequirements) {
                return $this->hasRequiredStock($agent, $productRequirements);
            })
            ->map(function($agent) {
                return [
                    'id' => $agent->id,
                    'name' => $agent->name ?? $agent->user->name ?? 'Unknown',
                    'phone' => $agent->phone_number ?? $agent->user->phone ?? '',
                    'location' => $agent->location,
                    'territory' => $agent->territory,
                    'current_stock' => $agent->current_stock ?? [],
                    'active_orders_count' => $agent->active_orders_count ?? 0,
                    'rating' => $agent->rating ?? 0,
                    'success_rate' => $agent->success_rate ?? 0,
                    'last_active_at' => $agent->last_active_at?->diffForHumans() ?? 'Unknown'
                ];
            })
            ->values();
            
        return response()->json([
            'status' => 'success',
            'data' => $agents,
            'total_available' => $agents->count()
        ]);
    }
    
    // GET /api/delivery-agents/{agentId}/stock
    public function getAgentStock($agentId): JsonResponse
    {
        $agent = DeliveryAgent::findOrFail($agentId);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'agent' => [
                    'id' => $agent->id,
                    'name' => $agent->name ?? $agent->user->name ?? 'Unknown',
                    'phone' => $agent->phone_number ?? $agent->user->phone ?? '',
                    'location' => $agent->location,
                    'territory' => $agent->territory,
                    'status' => $agent->status
                ],
                'stock' => $agent->current_stock ?? [],
                'active_orders' => $agent->active_orders_count ?? 0,
                'last_sync' => $agent->last_stock_sync?->format('Y-m-d H:i:s') ?? 'Never',
                'zoho_bin_id' => $agent->zoho_bin_id
            ]
        ]);
    }
    
    // POST /api/delivery-agents/sync-stock
    public function syncStockFromZoho(): JsonResponse
    {
        $agents = DeliveryAgent::whereNotNull('zoho_bin_id')->get();
        $syncedCount = 0;
        
        foreach ($agents as $agent) {
            try {
                $stockData = $this->fetchZohoBinStock($agent->zoho_bin_id);
                $agent->current_stock = $stockData;
                $agent->last_stock_sync = now();
                $agent->save();
                $syncedCount++;
            } catch (\Exception $e) {
                // Log error but continue with other agents
                \Log::error("Failed to sync stock for agent {$agent->id}: " . $e->getMessage());
            }
        }
        
        return response()->json([
            'status' => 'success',
            'message' => "Stock synchronized successfully for {$syncedCount} agents",
            'synced_count' => $syncedCount,
            'total_agents' => $agents->count()
        ]);
    }

    // PRIVATE HELPER METHODS FOR TELESALES PORTAL

    private function hasRequiredStock($agent, $productRequirements)
    {
        if (empty($productRequirements)) return true;
        
        $currentStock = $agent->current_stock ?? [];
        
        foreach ($productRequirements as $product => $quantity) {
            if (($currentStock[$product] ?? 0) < $quantity) {
                return false;
            }
        }
        
        return true;
    }

    private function fetchZohoBinStock($zohoBinId)
    {
        // TODO: Integrate with Zoho Inventory API
        // This is a placeholder implementation
        // In production, this would make an API call to Zoho
        
        // For now, return mock data
        return [
            'shampoo' => rand(0, 50),
            'pomade' => rand(0, 30),
            'conditioner' => rand(0, 40)
        ];
    }
}

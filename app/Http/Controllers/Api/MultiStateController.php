<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Staff;
use App\Models\DeliveryAgent;
use App\Models\TelesalesPerformance;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class MultiStateController extends Controller
{
    /**
     * Get state overview
     */
    public function overview(Request $request): JsonResponse
    {
        $states = config('gm_portal.states');
        $overview = [];

        foreach ($states as $state) {
            $overview[$state] = $this->getStateOverview($state);
        }

        return response()->json([
            'status' => 'success',
            'data' => $overview,
            'summary' => $this->getMultiStateSummary($overview),
        ]);
    }

    /**
     * Get state performance
     */
    public function statePerformance(Request $request, $state): JsonResponse
    {
        $period = $request->get('period', 'today');
        $startDate = $period === 'today' ? today() : now()->startOfWeek();
        $endDate = $period === 'today' ? today() : now();

        $performance = $this->getStatePerformance($state, $startDate, $endDate);

        return response()->json([
            'status' => 'success',
            'data' => $performance,
            'state' => $state,
            'period' => $period,
        ]);
    }

    /**
     * Get state comparison
     */
    public function stateComparison(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $startDate = $period === 'today' ? today() : now()->startOfWeek();
        $endDate = $period === 'today' ? today() : now();

        $states = config('gm_portal.states');
        $comparison = [];

        foreach ($states as $state) {
            $comparison[$state] = $this->getStateMetrics($state, $startDate, $endDate);
        }

        return response()->json([
            'status' => 'success',
            'data' => $comparison,
            'period' => $period,
            'ranking' => $this->getStateRanking($comparison),
        ]);
    }

    /**
     * Get state staff allocation
     */
    public function staffAllocation(Request $request): JsonResponse
    {
        $states = config('gm_portal.states');
        $allocation = [];

        foreach ($states as $state) {
            $allocation[$state] = [
                'telesales_reps' => Staff::where('staff_type', 'telesales_rep')
                    ->where('state_assigned', $state)
                    ->where('status', 'active')
                    ->count(),
                'delivery_agents' => DeliveryAgent::where('state', $state)
                    ->where('status', 'active')
                    ->count(),
                'total_staff' => Staff::where('state_assigned', $state)
                    ->where('status', 'active')
                    ->count() + DeliveryAgent::where('state', $state)
                    ->where('status', 'active')
                    ->count(),
            ];
        }

        return response()->json([
            'status' => 'success',
            'data' => $allocation,
            'summary' => $this->getStaffAllocationSummary($allocation),
        ]);
    }

    /**
     * Get state inventory status
     */
    public function inventoryStatus(Request $request): JsonResponse
    {
        $states = config('gm_portal.states');
        $inventory = [];

        foreach ($states as $state) {
            $inventory[$state] = $this->getStateInventoryStatus($state);
        }

        return response()->json([
            'status' => 'success',
            'data' => $inventory,
            'summary' => $this->getInventorySummary($inventory),
        ]);
    }

    /**
     * Get state overview data
     */
    private function getStateOverview($state): array
    {
        $today = today();
        
        return [
            'orders_today' => Order::where('state', $state)
                ->whereDate('created_at', $today)
                ->count(),
            'delivered_today' => Order::where('state', $state)
                ->whereDate('delivered_at', $today)
                ->count(),
            'ghosted_today' => Order::where('state', $state)
                ->whereDate('created_at', $today)
                ->where('is_ghosted', true)
                ->count(),
            'revenue_today' => Order::where('state', $state)
                ->whereDate('created_at', $today)
                ->where('payment_status', 'confirmed')
                ->sum('total_amount'),
            'active_staff' => Staff::where('state_assigned', $state)
                ->where('status', 'active')
                ->count() + DeliveryAgent::where('state', $state)
                ->where('status', 'active')
                ->count(),
            'delivery_rate' => $this->calculateStateDeliveryRate($state, $today),
            'ghost_rate' => $this->calculateStateGhostRate($state, $today),
        ];
    }

    /**
     * Get state performance data
     */
    private function getStatePerformance($state, $startDate, $endDate): array
    {
        $orders = Order::where('state', $state)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', 'delivered')->count();
        $ghostedOrders = $orders->where('is_ghosted', true)->count();
        $confirmedPayments = $orders->where('payment_status', 'confirmed')->count();

        return [
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'ghosted_orders' => $ghostedOrders,
            'confirmed_payments' => $confirmedPayments,
            'delivery_rate' => $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0,
            'ghost_rate' => $totalOrders > 0 ? round(($ghostedOrders / $totalOrders) * 100, 2) : 0,
            'payment_confirmation_rate' => $totalOrders > 0 ? round(($confirmedPayments / $totalOrders) * 100, 2) : 0,
            'total_revenue' => $orders->where('payment_status', 'confirmed')->sum('total_amount'),
            'average_order_value' => $orders->where('payment_status', 'confirmed')->avg('total_amount') ?? 0,
            'top_performers' => $this->getStateTopPerformers($state, $startDate, $endDate),
        ];
    }

    /**
     * Get state metrics
     */
    private function getStateMetrics($state, $startDate, $endDate): array
    {
        $orders = Order::where('state', $state)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();

        $totalOrders = $orders->count();
        $deliveredOrders = $orders->where('status', 'delivered')->count();
        $ghostedOrders = $orders->where('is_ghosted', true)->count();
        $revenue = $orders->where('payment_status', 'confirmed')->sum('total_amount');

        return [
            'total_orders' => $totalOrders,
            'delivered_orders' => $deliveredOrders,
            'ghosted_orders' => $ghostedOrders,
            'delivery_rate' => $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0,
            'ghost_rate' => $totalOrders > 0 ? round(($ghostedOrders / $totalOrders) * 100, 2) : 0,
            'revenue' => $revenue,
            'average_order_value' => $orders->where('payment_status', 'confirmed')->avg('total_amount') ?? 0,
        ];
    }

    /**
     * Get state ranking
     */
    private function getStateRanking($comparison): array
    {
        $ranking = [];

        // Rank by delivery rate
        $deliveryRateRanking = collect($comparison)
            ->sortByDesc('delivery_rate')
            ->keys()
            ->values()
            ->toArray();

        // Rank by revenue
        $revenueRanking = collect($comparison)
            ->sortByDesc('revenue')
            ->keys()
            ->values()
            ->toArray();

        // Rank by order volume
        $orderVolumeRanking = collect($comparison)
            ->sortByDesc('total_orders')
            ->keys()
            ->values()
            ->toArray();

        return [
            'delivery_rate_ranking' => $deliveryRateRanking,
            'revenue_ranking' => $revenueRanking,
            'order_volume_ranking' => $orderVolumeRanking,
        ];
    }

    /**
     * Get state inventory status
     */
    private function getStateInventoryStatus($state): array
    {
        $deliveryAgents = DeliveryAgent::where('state', $state)
            ->with('inventory')
            ->get();

        $totalInventory = [
            'shampoo' => 0,
            'pomade' => 0,
            'conditioner' => 0,
        ];

        $stagnantInventory = 0;
        $lowStockAgents = 0;

        foreach ($deliveryAgents as $da) {
            foreach ($da->inventory as $inventory) {
                $totalInventory[$inventory->product_type] += $inventory->quantity;
                
                if ($inventory->days_stagnant >= 5) {
                    $stagnantInventory++;
                }
                
                if ($inventory->quantity <= $inventory->min_stock_level) {
                    $lowStockAgents++;
                }
            }
        }

        return [
            'total_inventory' => $totalInventory,
            'active_das' => $deliveryAgents->where('status', 'active')->count(),
            'stagnant_inventory' => $stagnantInventory,
            'low_stock_agents' => $lowStockAgents,
            'total_stock_value' => $this->calculateTotalStockValue($totalInventory),
        ];
    }

    /**
     * Calculate state delivery rate
     */
    private function calculateStateDeliveryRate($state, $date): float
    {
        $totalOrders = Order::where('state', $state)
            ->whereDate('created_at', $date)
            ->count();
        $deliveredOrders = Order::where('state', $state)
            ->whereDate('delivered_at', $date)
            ->count();
        
        return $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0;
    }

    /**
     * Calculate state ghost rate
     */
    private function calculateStateGhostRate($state, $date): float
    {
        $totalOrders = Order::where('state', $state)
            ->whereDate('created_at', $date)
            ->count();
        $ghostedOrders = Order::where('state', $state)
            ->whereDate('created_at', $date)
            ->where('is_ghosted', true)
            ->count();
        
        return $totalOrders > 0 ? round(($ghostedOrders / $totalOrders) * 100, 2) : 0;
    }

    /**
     * Get state top performers
     */
    private function getStateTopPerformers($state, $startDate, $endDate): array
    {
        return Staff::where('staff_type', 'telesales_rep')
            ->where('state_assigned', $state)
            ->where('status', 'active')
            ->orderBy('performance_score', 'desc')
            ->take(5)
            ->with('user')
            ->get()
            ->map(function ($staff) {
                return [
                    'name' => $staff->user->name,
                    'performance_score' => $staff->performance_score,
                    'delivery_rate' => $staff->delivery_rate,
                    'orders_delivered' => $staff->completed_orders,
                ];
            })
            ->toArray();
    }

    /**
     * Calculate total stock value
     */
    private function calculateTotalStockValue($inventory): float
    {
        $prices = [
            'shampoo' => 2500,
            'pomade' => 3000,
            'conditioner' => 2800,
        ];

        $totalValue = 0;
        foreach ($inventory as $product => $quantity) {
            $totalValue += $quantity * ($prices[$product] ?? 0);
        }

        return $totalValue;
    }

    /**
     * Get multi-state summary
     */
    private function getMultiStateSummary($overview): array
    {
        $totalOrders = collect($overview)->sum('orders_today');
        $totalDelivered = collect($overview)->sum('delivered_today');
        $totalGhosted = collect($overview)->sum('ghosted_today');
        $totalRevenue = collect($overview)->sum('revenue_today');
        $totalStaff = collect($overview)->sum('active_staff');

        return [
            'total_orders' => $totalOrders,
            'total_delivered' => $totalDelivered,
            'total_ghosted' => $totalGhosted,
            'total_revenue' => $totalRevenue,
            'total_staff' => $totalStaff,
            'overall_delivery_rate' => $totalOrders > 0 ? round(($totalDelivered / $totalOrders) * 100, 2) : 0,
            'overall_ghost_rate' => $totalOrders > 0 ? round(($totalGhosted / $totalOrders) * 100, 2) : 0,
        ];
    }

    /**
     * Get staff allocation summary
     */
    private function getStaffAllocationSummary($allocation): array
    {
        $totalTelesalesReps = collect($allocation)->sum('telesales_reps');
        $totalDeliveryAgents = collect($allocation)->sum('delivery_agents');
        $totalStaff = collect($allocation)->sum('total_staff');

        return [
            'total_telesales_reps' => $totalTelesalesReps,
            'total_delivery_agents' => $totalDeliveryAgents,
            'total_staff' => $totalStaff,
            'staff_distribution' => [
                'telesales_percentage' => $totalStaff > 0 ? round(($totalTelesalesReps / $totalStaff) * 100, 2) : 0,
                'delivery_percentage' => $totalStaff > 0 ? round(($totalDeliveryAgents / $totalStaff) * 100, 2) : 0,
            ],
        ];
    }

    /**
     * Get inventory summary
     */
    private function getInventorySummary($inventory): array
    {
        $totalInventory = [
            'shampoo' => collect($inventory)->sum('total_inventory.shampoo'),
            'pomade' => collect($inventory)->sum('total_inventory.pomade'),
            'conditioner' => collect($inventory)->sum('total_inventory.conditioner'),
        ];

        return [
            'total_inventory' => $totalInventory,
            'total_stock_value' => $this->calculateTotalStockValue($totalInventory),
            'total_active_das' => collect($inventory)->sum('active_das'),
            'total_stagnant_inventory' => collect($inventory)->sum('stagnant_inventory'),
            'total_low_stock_agents' => collect($inventory)->sum('low_stock_agents'),
        ];
    }
}

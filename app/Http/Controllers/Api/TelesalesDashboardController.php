<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelesalesAgent;
use App\Models\Order;
use App\Models\DeliveryAgent;
use App\Models\WeeklyPerformance;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TelesalesDashboardController extends Controller
{
    // GET /api/telesales/{agentId}/dashboard
    public function getDashboard($agentId, Request $request)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $period = $request->get('period', 'week'); // day, week, month, quarter
        $dateRange = $this->getDateRange($period, $request->get('date'));
        
        return response()->json([
            'agent' => $agent,
            'kpis' => $this->getKPIs($agent, $dateRange),
            'qualification_status' => $this->getQualificationStatus($agent, $dateRange),
            'bonus_info' => $this->getBonusInfo($agent),
            'period_breakdown' => $this->getPeriodBreakdown($agent, $dateRange, $period)
        ]);
    }
    
    // GET /api/telesales/{agentId}/orders
    public function getOrders($agentId, Request $request)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $status = $request->get('status', 'all');
        
        $orders = Order::where('telesales_agent_id', $agentId)
            ->when($status !== 'all', function($query) use ($status) {
                return $query->where('call_status', $status);
            })
            ->with('deliveryAgent')
            ->orderBy('created_at', 'desc')
            ->paginate(20);
            
        return response()->json($orders);
    }
    
    // GET /api/telesales/{agentId}/orders/{orderId}/details
    public function getOrderDetails($agentId, $orderId)
    {
        $order = Order::where('telesales_agent_id', $agentId)
            ->where('id', $orderId)
            ->with('deliveryAgent')
            ->firstOrFail();
            
        $availableAgents = $this->getAvailableDeliveryAgents($order);
        
        return response()->json([
            'order' => $order,
            'available_agents' => $availableAgents,
            'kemi_chat' => $order->kemi_chat_log ?? []
        ]);
    }
    
    private function getKPIs($agent, $dateRange)
    {
        $orders = Order::where('telesales_agent_id', $agent->id)
            ->whereBetween('created_at', $dateRange)
            ->get();
            
        return [
            'locked_bonus' => $agent->accumulated_bonus,
            'orders_assigned' => $orders->count(),
            'orders_delivered' => $orders->where('delivery_status', 'delivered')->count(),
            'delivery_rate' => $orders->count() > 0 ? 
                round(($orders->where('delivery_status', 'delivered')->count() / $orders->count()) * 100, 1) : 0,
            'avg_response_time' => $this->calculateAvgResponseTime($orders),
            'weekly_bonus' => $this->calculateWeeklyBonus($orders),
            'days_to_unlock' => $this->getDaysToUnlock($agent)
        ];
    }
    
    private function getQualificationStatus($agent, $dateRange)
    {
        $orders = Order::where('telesales_agent_id', $agent->id)
            ->whereBetween('created_at', $dateRange)
            ->get();
            
        $deliveredCount = $orders->where('delivery_status', 'delivered')->count();
        $totalCount = $orders->count();
        $deliveryRate = $totalCount > 0 ? ($deliveredCount / $totalCount) * 100 : 0;
        
        return [
            'qualified' => $deliveryRate >= 70 && $totalCount >= 20,
            'delivery_rate' => round($deliveryRate, 1),
            'orders_required' => max(0, 20 - $totalCount),
            'rate_required' => max(0, 70 - $deliveryRate),
            'bonus_eligible' => $deliveryRate >= 70 && $totalCount >= 20
        ];
    }
    
    private function getBonusInfo($agent)
    {
        $employmentDate = Carbon::parse($agent->employment_start);
        $threeMonthsDate = $employmentDate->addMonths(3);
        $daysToUnlock = max(0, now()->diffInDays($threeMonthsDate));
        
        return [
            'accumulated_bonus' => $agent->accumulated_bonus,
            'bonus_unlocked' => $agent->bonus_unlocked,
            'employment_start' => $agent->employment_start,
            'days_to_unlock' => $daysToUnlock,
            'can_unlock' => $agent->canUnlockBonus(),
            'bonus_per_delivery' => 150
        ];
    }
    
    private function getPeriodBreakdown($agent, $dateRange, $period)
    {
        $orders = Order::where('telesales_agent_id', $agent->id)
            ->whereBetween('created_at', $dateRange)
            ->get();
            
        $breakdown = [];
        
        if ($period === 'day') {
            for ($i = 0; $i < 7; $i++) {
                $date = now()->subDays($i);
                $dayOrders = $orders->where('created_at', '>=', $date->startOfDay())
                    ->where('created_at', '<=', $date->endOfDay());
                    
                $breakdown[] = [
                    'date' => $date->format('Y-m-d'),
                    'orders' => $dayOrders->count(),
                    'delivered' => $dayOrders->where('delivery_status', 'delivered')->count(),
                    'rate' => $dayOrders->count() > 0 ? 
                        round(($dayOrders->where('delivery_status', 'delivered')->count() / $dayOrders->count()) * 100, 1) : 0
                ];
            }
        } elseif ($period === 'week') {
            for ($i = 0; $i < 4; $i++) {
                $weekStart = now()->subWeeks($i)->startOfWeek();
                $weekEnd = $weekStart->copy()->endOfWeek();
                
                $weekOrders = $orders->where('created_at', '>=', $weekStart)
                    ->where('created_at', '<=', $weekEnd);
                    
                $breakdown[] = [
                    'week_start' => $weekStart->format('Y-m-d'),
                    'week_end' => $weekEnd->format('Y-m-d'),
                    'orders' => $weekOrders->count(),
                    'delivered' => $weekOrders->where('delivery_status', 'delivered')->count(),
                    'rate' => $weekOrders->count() > 0 ? 
                        round(($weekOrders->where('delivery_status', 'delivered')->count() / $weekOrders->count()) * 100, 1) : 0
                ];
            }
        }
        
        return array_reverse($breakdown);
    }
    
    private function getAvailableDeliveryAgents($order)
    {
        $location = $order->customer_location ?? $order->delivery_address;
        $productRequirements = $order->product_details ?? [];
        
        return DeliveryAgent::where('status', 'active')
            ->when($location, function($query) use ($location) {
                return $query->where('location', 'like', "%$location%")
                    ->orWhere('territory', 'like', "%$location%");
            })
            ->get()
            ->filter(function($agent) use ($productRequirements) {
                return $this->hasRequiredStock($agent, $productRequirements);
            })
            ->values();
    }
    
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
    
    private function calculateAvgResponseTime($orders)
    {
        // This would be calculated based on actual response time data
        // For now, return a placeholder
        return 2.5; // minutes
    }
    
    private function calculateWeeklyBonus($orders)
    {
        $deliveredCount = $orders->where('delivery_status', 'delivered')->count();
        return $deliveredCount * 150; // ₦150 per delivery
    }
    
    private function getDaysToUnlock($agent)
    {
        $employmentDate = Carbon::parse($agent->employment_start);
        $threeMonthsDate = $employmentDate->addMonths(3);
        return max(0, now()->diffInDays($threeMonthsDate));
    }
    
    private function getDateRange($period, $date = null)
    {
        $startDate = $date ? Carbon::parse($date) : now();
        
        switch ($period) {
            case 'day':
                return [$startDate->startOfDay(), $startDate->endOfDay()];
            case 'week':
                return [$startDate->startOfWeek(), $startDate->endOfWeek()];
            case 'month':
                return [$startDate->startOfMonth(), $startDate->endOfMonth()];
            case 'quarter':
                return [$startDate->startOfQuarter(), $startDate->endOfQuarter()];
            default:
                return [$startDate->startOfWeek(), $startDate->endOfWeek()];
        }
    }
}

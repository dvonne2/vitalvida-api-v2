<?php

namespace App\Http\Controllers;

use App\Models\TelesalesAgent;
use App\Models\Order;
use App\Models\WeeklyPerformance;
use App\Services\PerformanceService;
use Illuminate\Http\Request;
use Carbon\Carbon;

class TelesalesWebController extends Controller
{
    private $performanceService;
    
    public function __construct(PerformanceService $performanceService)
    {
        $this->performanceService = $performanceService;
    }
    
    public function dashboard($agentId, Request $request)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $period = $request->get('period', 'week');
        $date = $request->get('date', now()->format('Y-m-d'));
        
        $dateRange = $this->getDateRange($period, $date);
        
        $kpis = $this->getKPIs($agent, $dateRange);
        $qualificationStatus = $this->performanceService->getQualificationStatus($agentId);
        $bonusInfo = $this->performanceService->calculateBonusUnlockDate($agent);
        
        $orders = Order::where('telesales_agent_id', $agentId)
            ->whereBetween('created_at', $dateRange)
            ->with('deliveryAgent')
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();
        
        return view('telesales.dashboard', compact(
            'agent', 'kpis', 'qualificationStatus', 'bonusInfo', 'orders', 'period', 'date'
        ));
    }
    
    public function orders($agentId, Request $request)
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
        
        return view('telesales.orders', compact('agent', 'orders', 'status'));
    }
    
    public function performance($agentId, Request $request)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $weeks = $request->get('weeks', 12);
        
        $weeklyPerformances = WeeklyPerformance::where('telesales_agent_id', $agentId)
            ->orderBy('week_start', 'desc')
            ->limit($weeks)
            ->get();
        
        return view('telesales.performance', compact('agent', 'weeklyPerformances'));
    }
    
    public function bonusTracker($agentId)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $bonusInfo = $this->performanceService->calculateBonusUnlockDate($agent);
        
        $weeklyBreakdown = WeeklyPerformance::where('telesales_agent_id', $agentId)
            ->orderBy('week_start', 'desc')
            ->get();
        
        return view('telesales.bonus-tracker', compact('agent', 'bonusInfo', 'weeklyBreakdown'));
    }
    
    private function getDateRange($period, $date)
    {
        $baseDate = Carbon::parse($date);
        
        switch ($period) {
            case 'day':
                return [$baseDate->startOfDay(), $baseDate->endOfDay()];
            case 'week':
                return [$baseDate->startOfWeek(), $baseDate->endOfWeek()];
            case 'month':
                return [$baseDate->startOfMonth(), $baseDate->endOfMonth()];
            case 'quarter':
                return [$baseDate->startOfQuarter(), $baseDate->endOfQuarter()];
            default:
                return [$baseDate->startOfWeek(), $baseDate->endOfWeek()];
        }
    }
    
    private function getKPIs($agent, $dateRange)
    {
        $orders = Order::where('telesales_agent_id', $agent->id)
            ->whereBetween('created_at', $dateRange)
            ->get();
            
        $ordersAssigned = $orders->count();
        $ordersDelivered = $orders->where('delivery_status', 'delivered')->count();
        $deliveryRate = $ordersAssigned > 0 ? ($ordersDelivered / $ordersAssigned) * 100 : 0;
        
        // Calculate weekly bonus
        $weeklyBonus = 0;
        if ($deliveryRate >= 70 && $ordersAssigned >= 20) {
            $weeklyBonus = $ordersDelivered * 150; // ₦150 per delivery
        }
        
        return [
            'locked_bonus' => $agent->accumulated_bonus,
            'orders_assigned' => $ordersAssigned,
            'orders_delivered' => $ordersDelivered,
            'delivery_rate' => round($deliveryRate, 2),
            'weekly_bonus' => $weeklyBonus
        ];
    }
} 
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TelesalesAgent;
use App\Models\WeeklyPerformance;
use App\Models\Order;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class PerformanceController extends Controller
{
    // GET /api/performance/{agentId}/weekly
    public function getWeeklyPerformance($agentId, Request $request)
    {
        $weekStart = $request->get('week_start', now()->startOfWeek()->format('Y-m-d'));
        
        $performance = WeeklyPerformance::where('telesales_agent_id', $agentId)
            ->where('week_start', $weekStart)
            ->first();
            
        if (!$performance) {
            $performance = $this->calculateWeeklyPerformance($agentId, $weekStart);
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $performance
        ]);
    }
    
    // GET /api/performance/{agentId}/history
    public function getPerformanceHistory($agentId, Request $request)
    {
        $limit = $request->get('limit', 12); // Last 12 weeks by default
        
        $history = WeeklyPerformance::where('telesales_agent_id', $agentId)
            ->orderBy('week_start', 'desc')
            ->limit($limit)
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => $history,
            'total_weeks' => $history->count()
        ]);
    }
    
    // GET /api/performance/{agentId}/bonus-breakdown
    public function getBonusBreakdown($agentId)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $weeklyPerformances = WeeklyPerformance::where('telesales_agent_id', $agentId)
            ->orderBy('week_start', 'desc')
            ->get();
            
        return response()->json([
            'status' => 'success',
            'data' => [
                'total_accumulated' => $agent->accumulated_bonus,
                'employment_start' => $agent->employment_start,
                'days_to_unlock' => $this->getDaysToUnlock($agent),
                'weekly_breakdown' => $weeklyPerformances,
                'qualification_rules' => [
                    'min_orders' => 20,
                    'min_delivery_rate' => 70,
                    'bonus_per_delivery' => 150
                ]
            ]
        ]);
    }

    // GET /api/performance/{agentId}/current-week
    public function getCurrentWeekPerformance($agentId)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $currentWeek = $agent->getCurrentWeekPerformance();
        
        $weekStart = now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        
        $orders = Order::where('telesales_agent_id', $agentId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();
            
        $currentPerformance = [
            'week_start' => $weekStart->format('Y-m-d'),
            'week_end' => $weekEnd->format('Y-m-d'),
            'orders_assigned' => $orders->count(),
            'orders_delivered' => $orders->where('delivery_status', 'delivered')->count(),
            'orders_pending' => $orders->where('delivery_status', 'pending')->count(),
            'orders_in_transit' => $orders->where('delivery_status', 'in_transit')->count(),
            'delivery_rate' => $orders->count() > 0 ? 
                round(($orders->where('delivery_status', 'delivered')->count() / $orders->count()) * 100, 1) : 0,
            'qualified' => $this->isQualifiedForBonus($orders),
            'bonus_earned' => $this->calculateWeeklyBonus($orders),
            'avg_response_time' => $this->calculateAvgResponseTime($orders)
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $currentPerformance
        ]);
    }

    // GET /api/performance/{agentId}/comparison
    public function getPerformanceComparison($agentId, Request $request)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $period = $request->get('period', 'week');
        
        $currentPeriod = $this->getCurrentPeriodPerformance($agent, $period);
        $previousPeriod = $this->getPreviousPeriodPerformance($agent, $period);
        
        $comparison = [
            'current_period' => $currentPeriod,
            'previous_period' => $previousPeriod,
            'improvements' => $this->calculateImprovements($currentPeriod, $previousPeriod)
        ];
        
        return response()->json([
            'status' => 'success',
            'data' => $comparison
        ]);
    }

    // POST /api/performance/{agentId}/unlock-bonus
    public function unlockBonus($agentId)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        
        if (!$agent->canUnlockBonus()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Bonus cannot be unlocked yet. You need to be employed for at least 3 months.'
            ], 400);
        }
        
        $agent->unlockBonus();
        
        return response()->json([
            'status' => 'success',
            'message' => 'Bonus unlocked successfully',
            'data' => [
                'accumulated_bonus' => $agent->accumulated_bonus,
                'bonus_unlocked' => $agent->bonus_unlocked
            ]
        ]);
    }

    // PRIVATE HELPER METHODS

    private function calculateWeeklyPerformance($agentId, $weekStart)
    {
        $weekEnd = Carbon::parse($weekStart)->endOfWeek();
        
        $orders = Order::where('telesales_agent_id', $agentId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();
            
        $deliveredCount = $orders->where('delivery_status', 'delivered')->count();
        $totalCount = $orders->count();
        $deliveryRate = $totalCount > 0 ? ($deliveredCount / $totalCount) * 100 : 0;
        $qualified = $deliveryRate >= 70 && $totalCount >= 20;
        $bonusEarned = $qualified ? $deliveredCount * 150 : 0;
        
        $performance = WeeklyPerformance::create([
            'telesales_agent_id' => $agentId,
            'week_start' => $weekStart,
            'week_end' => $weekEnd->format('Y-m-d'),
            'orders_assigned' => $totalCount,
            'orders_delivered' => $deliveredCount,
            'delivery_rate' => $deliveryRate,
            'qualified' => $qualified,
            'bonus_earned' => $bonusEarned,
            'avg_response_time' => $this->calculateAvgResponseTime($orders)
        ]);
        
        return $performance;
    }

    private function isQualifiedForBonus($orders)
    {
        $deliveredCount = $orders->where('delivery_status', 'delivered')->count();
        $totalCount = $orders->count();
        $deliveryRate = $totalCount > 0 ? ($deliveredCount / $totalCount) * 100 : 0;
        
        return $deliveryRate >= 70 && $totalCount >= 20;
    }

    private function calculateWeeklyBonus($orders)
    {
        $deliveredCount = $orders->where('delivery_status', 'delivered')->count();
        return $deliveredCount * 150; // ₦150 per delivery
    }

    private function calculateAvgResponseTime($orders)
    {
        // This would be calculated based on actual response time data
        // For now, return a placeholder
        return 2.5; // minutes
    }

    private function getDaysToUnlock($agent)
    {
        $employmentDate = Carbon::parse($agent->employment_start);
        $threeMonthsDate = $employmentDate->addMonths(3);
        return max(0, now()->diffInDays($threeMonthsDate));
    }

    private function getCurrentPeriodPerformance($agent, $period)
    {
        $startDate = $this->getPeriodStartDate($period);
        $endDate = $this->getPeriodEndDate($period);
        
        $orders = Order::where('telesales_agent_id', $agent->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
            
        return $this->calculatePeriodMetrics($orders);
    }

    private function getPreviousPeriodPerformance($agent, $period)
    {
        $startDate = $this->getPeriodStartDate($period, true);
        $endDate = $this->getPeriodEndDate($period, true);
        
        $orders = Order::where('telesales_agent_id', $agent->id)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->get();
            
        return $this->calculatePeriodMetrics($orders);
    }

    private function calculatePeriodMetrics($orders)
    {
        $deliveredCount = $orders->where('delivery_status', 'delivered')->count();
        $totalCount = $orders->count();
        $deliveryRate = $totalCount > 0 ? ($deliveredCount / $totalCount) * 100 : 0;
        
        return [
            'orders_assigned' => $totalCount,
            'orders_delivered' => $deliveredCount,
            'delivery_rate' => round($deliveryRate, 1),
            'bonus_earned' => $deliveredCount * 150,
            'qualified' => $deliveryRate >= 70 && $totalCount >= 20
        ];
    }

    private function getPeriodStartDate($period, $previous = false)
    {
        $date = now();
        
        if ($previous) {
            switch ($period) {
                case 'week':
                    $date = $date->subWeek();
                    break;
                case 'month':
                    $date = $date->subMonth();
                    break;
                case 'quarter':
                    $date = $date->subQuarter();
                    break;
            }
        }
        
        switch ($period) {
            case 'week':
                return $date->startOfWeek();
            case 'month':
                return $date->startOfMonth();
            case 'quarter':
                return $date->startOfQuarter();
            default:
                return $date->startOfWeek();
        }
    }

    private function getPeriodEndDate($period, $previous = false)
    {
        $date = now();
        
        if ($previous) {
            switch ($period) {
                case 'week':
                    $date = $date->subWeek();
                    break;
                case 'month':
                    $date = $date->subMonth();
                    break;
                case 'quarter':
                    $date = $date->subQuarter();
                    break;
            }
        }
        
        switch ($period) {
            case 'week':
                return $date->endOfWeek();
            case 'month':
                return $date->endOfMonth();
            case 'quarter':
                return $date->endOfQuarter();
            default:
                return $date->endOfWeek();
        }
    }

    private function calculateImprovements($current, $previous)
    {
        $improvements = [];
        
        $metrics = ['orders_assigned', 'orders_delivered', 'delivery_rate', 'bonus_earned'];
        
        foreach ($metrics as $metric) {
            $currentValue = $current[$metric] ?? 0;
            $previousValue = $previous[$metric] ?? 0;
            
            if ($previousValue > 0) {
                $percentage = (($currentValue - $previousValue) / $previousValue) * 100;
                $improvements[$metric] = [
                    'current' => $currentValue,
                    'previous' => $previousValue,
                    'change' => $currentValue - $previousValue,
                    'percentage' => round($percentage, 1)
                ];
            } else {
                $improvements[$metric] = [
                    'current' => $currentValue,
                    'previous' => $previousValue,
                    'change' => $currentValue,
                    'percentage' => $currentValue > 0 ? 100 : 0
                ];
            }
        }
        
        return $improvements;
    }
}

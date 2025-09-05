<?php

namespace App\Services;

use App\Models\TelesalesAgent;
use App\Models\Order;
use App\Models\WeeklyPerformance;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;

class PerformanceService
{
    public function calculateWeeklyPerformance($agentId, $weekStart = null)
    {
        $weekStart = $weekStart ? Carbon::parse($weekStart) : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        
        $orders = Order::where('telesales_agent_id', $agentId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();
            
        $ordersAssigned = $orders->count();
        $ordersDelivered = $orders->where('delivery_status', 'delivered')->count();
        $deliveryRate = $ordersAssigned > 0 ? ($ordersDelivered / $ordersAssigned) * 100 : 0;
        
        // Qualification: ≥70% delivery rate AND ≥20 orders
        $qualified = $deliveryRate >= 70 && $ordersAssigned >= 20;
        $bonusEarned = $qualified ? $ordersDelivered * 150 : 0; // ₦150 per delivery
        
        $avgResponseTime = $this->calculateAverageResponseTime($orders);
        
        // Store or update weekly performance
        $performance = WeeklyPerformance::updateOrCreate(
            [
                'telesales_agent_id' => $agentId,
                'week_start' => $weekStart->toDateString(),
                'week_end' => $weekEnd->toDateString()
            ],
            [
                'orders_assigned' => $ordersAssigned,
                'orders_delivered' => $ordersDelivered,
                'delivery_rate' => round($deliveryRate, 2),
                'qualified' => $qualified,
                'bonus_earned' => $bonusEarned,
                'avg_response_time' => $avgResponseTime
            ]
        );
        
        return $performance;
    }
    
    public function getQualificationStatus($agentId, $weekStart = null)
    {
        $weekStart = $weekStart ? Carbon::parse($weekStart) : now()->startOfWeek();
        $weekEnd = $weekStart->copy()->endOfWeek();
        
        $orders = Order::where('telesales_agent_id', $agentId)
            ->whereBetween('created_at', [$weekStart, $weekEnd])
            ->get();
            
        $ordersAssigned = $orders->count();
        $ordersDelivered = $orders->where('delivery_status', 'delivered')->count();
        $deliveryRate = $ordersAssigned > 0 ? ($ordersDelivered / $ordersAssigned) * 100 : 0;
        
        return [
            'qualified' => $deliveryRate >= 70 && $ordersAssigned >= 20,
            'orders_assigned' => $ordersAssigned,
            'orders_delivered' => $ordersDelivered,
            'delivery_rate' => round($deliveryRate, 2),
            'needs_more_orders' => max(0, 20 - $ordersAssigned),
            'needs_better_rate' => max(0, 70 - $deliveryRate),
            'potential_bonus' => $ordersDelivered * 150
        ];
    }
    
    public function calculateBonusUnlockDate($agent)
    {
        $employmentStart = Carbon::parse($agent->employment_start);
        $unlockDate = $employmentStart->addMonths(3);
        $daysRemaining = max(0, now()->diffInDays($unlockDate, false));
        
        return [
            'unlock_date' => $unlockDate->toDateString(),
            'days_remaining' => $daysRemaining,
            'can_unlock' => $daysRemaining <= 0
        ];
    }
    
    public function unlockBonus($agentId)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $bonusInfo = $this->calculateBonusUnlockDate($agent);
        
        if (!$bonusInfo['can_unlock']) {
            throw new Exception('Bonus cannot be unlocked yet. Employment period not met.');
        }
        
        $agent->accumulated_bonus = 0; // Reset after unlocking
        $agent->save();
        
        Log::info("Bonus unlocked for agent {$agentId}: ₦{$agent->accumulated_bonus}");
        
        return $agent->accumulated_bonus;
    }
    
    private function calculateAverageResponseTime($orders)
    {
        $responseTimes = [];
        
        foreach ($orders as $order) {
            if ($order->assigned_at && $order->created_at) {
                $responseTime = Carbon::parse($order->created_at)
                    ->diffInMinutes(Carbon::parse($order->assigned_at));
                $responseTimes[] = $responseTime;
            }
        }
        
        return count($responseTimes) > 0 ? round(array_sum($responseTimes) / count($responseTimes), 2) : 0;
    }
} 
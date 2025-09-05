<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Staff;
use App\Models\Order;
use App\Models\OrderRerouting;
use App\Models\TelesalesPerformance;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class TelesalesController extends Controller
{
    /**
     * Get telesales performance metrics
     */
    public function performance(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        
        $query = User::where('role', 'telesales_rep')
            ->with(['staff', 'orders' => function($q) use ($period) {
                if ($period === 'today') {
                    $q->whereDate('created_at', today());
                } elseif ($period === 'week') {
                    $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
                } elseif ($period === 'month') {
                    $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]);
                }
            }]);

        $telesalesReps = $query->get()->map(function ($rep) use ($period) {
            $orders = $rep->orders;
            $totalOrders = $orders->count();
            $attendedIn15Min = $orders->filter(function($order) {
                return $order->created_at && $order->assigned_at && 
                       $order->assigned_at->diffInMinutes($order->created_at) <= 15;
            })->count();
            $delivered = $orders->where('status', 'delivered')->count();
            $ghosted = $orders->where('is_ghosted', true)->count();
            $reassigned = OrderRerouting::where('from_staff_id', $rep->id)->count();
            
            $deliveryRate = $totalOrders > 0 ? round(($delivered / $totalOrders) * 100, 2) : 0;
            
            // Determine bonus flag
            $bonusFlag = 'ELIGIBLE';
            if ($deliveryRate < 50) $bonusFlag = 'BLOCKED';
            elseif ($deliveryRate < 70) $bonusFlag = 'BORDERLINE';
            elseif ($deliveryRate > 85) $bonusFlag = 'TOP PERFORMER';

            // Calculate performance score
            $performanceScore = $this->calculatePerformanceScore($rep, $period);

            return [
                'id' => $rep->id,
                'name' => $rep->name,
                'state' => $rep->staff->state_assigned ?? 'Unassigned',
                'orders_assigned' => $totalOrders,
                'attended_in_15min' => $attendedIn15Min,
                'delivered' => $delivered,
                'ghosted' => $ghosted,
                'reassigned' => $reassigned,
                'delivery_rate' => $deliveryRate,
                'performance_score' => $performanceScore,
                'bonus_flag' => $bonusFlag,
                'status' => $deliveryRate < 30 ? 'BLOCKED' : 'ACTIVE',
                'last_activity' => $rep->staff->last_activity_date?->diffForHumans() ?? 'Unknown',
                'total_earnings' => $rep->staff->total_earnings ?? 0,
                'commission_rate' => $rep->staff->commission_rate ?? 5,
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => $telesalesReps,
            'period' => $period,
            'summary' => $this->getPerformanceSummary($telesalesReps),
        ]);
    }

    /**
     * Block telesales rep
     */
    public function blockRep(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
        ]);

        $rep = User::findOrFail($id);
        $rep->staff()->update(['status' => 'blocked']);
        
        // Log the action
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'staff_blocked',
            'previous_status' => 'active',
            'new_status' => 'blocked',
            'timestamp' => now(),
            'notes' => "Blocked telesales rep {$rep->name}. Reason: {$request->reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Telesales rep blocked successfully',
            'data' => $rep->fresh('staff'),
        ]);
    }

    /**
     * Award bonus to telesales rep
     */
    public function awardBonus(Request $request, $id): JsonResponse
    {
        $request->validate([
            'bonus_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $rep = User::findOrFail($id);
        $staff = $rep->staff;
        
        $staff->update([
            'total_earnings' => $staff->total_earnings + $request->bonus_amount,
        ]);

        // Log the bonus
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'bonus_awarded',
            'previous_status' => 'active',
            'new_status' => 'active',
            'timestamp' => now(),
            'notes' => "Awarded ₦{$request->bonus_amount} bonus to {$rep->name}. Reason: {$request->reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Bonus awarded successfully',
            'data' => $rep->fresh('staff'),
        ]);
    }

    /**
     * Schedule training for telesales rep
     */
    public function scheduleTraining(Request $request, $id): JsonResponse
    {
        $request->validate([
            'training_type' => 'required|in:performance,product_knowledge,communication,compliance',
            'scheduled_date' => 'required|date|after:today',
            'notes' => 'nullable|string|max:500',
        ]);

        $rep = User::findOrFail($id);
        
        // Log the training schedule
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'training_scheduled',
            'previous_status' => 'active',
            'new_status' => 'active',
            'timestamp' => now(),
            'notes' => "Scheduled {$request->training_type} training for {$rep->name} on {$request->scheduled_date}. Notes: {$request->notes}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Training scheduled successfully',
            'data' => [
                'rep_name' => $rep->name,
                'training_type' => $request->training_type,
                'scheduled_date' => $request->scheduled_date,
            ],
        ]);
    }

    /**
     * Get telesales rep details
     */
    public function show($id): JsonResponse
    {
        $rep = User::with(['staff', 'orders' => function($q) {
            $q->whereDate('created_at', '>=', now()->subDays(30));
        }, 'orderHistory'])->findOrFail($id);

        $recentOrders = $rep->orders->take(10);
        $performanceHistory = $this->getPerformanceHistory($rep->id);

        return response()->json([
            'status' => 'success',
            'data' => [
                'rep' => $rep,
                'recent_orders' => $recentOrders,
                'performance_history' => $performanceHistory,
                'statistics' => $this->getRepStatistics($rep),
            ],
        ]);
    }

    /**
     * Calculate performance score for rep
     */
    private function calculatePerformanceScore($rep, $period): float
    {
        $orders = $rep->orders;
        $totalOrders = $orders->count();
        
        if ($totalOrders === 0) return 0;

        $delivered = $orders->where('status', 'delivered')->count();
        $ghosted = $orders->where('is_ghosted', true)->count();
        $attendedIn15Min = $orders->filter(function($order) {
            return $order->created_at && $order->assigned_at && 
                   $order->assigned_at->diffInMinutes($order->created_at) <= 15;
        })->count();

        $deliveryRate = ($delivered / $totalOrders) * 100;
        $ghostRate = ($ghosted / $totalOrders) * 100;
        $responseRate = ($attendedIn15Min / $totalOrders) * 100;

        // Weighted score calculation
        $score = ($deliveryRate * 0.5) + ($responseRate * 0.3) + ((100 - $ghostRate) * 0.2);

        return round($score, 2);
    }

    /**
     * Get performance summary
     */
    private function getPerformanceSummary($reps): array
    {
        $totalReps = $reps->count();
        $activeReps = $reps->where('status', 'ACTIVE')->count();
        $blockedReps = $reps->where('status', 'BLOCKED')->count();
        $topPerformers = $reps->where('bonus_flag', 'TOP PERFORMER')->count();
        $blockedBonus = $reps->where('bonus_flag', 'BLOCKED')->count();

        $avgDeliveryRate = $reps->avg('delivery_rate');
        $avgPerformanceScore = $reps->avg('performance_score');

        return [
            'total_reps' => $totalReps,
            'active_reps' => $activeReps,
            'blocked_reps' => $blockedReps,
            'top_performers' => $topPerformers,
            'blocked_bonus' => $blockedBonus,
            'avg_delivery_rate' => round($avgDeliveryRate, 2),
            'avg_performance_score' => round($avgPerformanceScore, 2),
        ];
    }

    /**
     * Get performance history for rep
     */
    private function getPerformanceHistory($repId): array
    {
        return TelesalesPerformance::where('staff_id', $repId)
            ->orderBy('date', 'desc')
            ->take(30)
            ->get()
            ->map(function ($performance) {
                return [
                    'date' => $performance->date->format('Y-m-d'),
                    'orders_assigned' => $performance->orders_assigned,
                    'orders_delivered' => $performance->orders_delivered,
                    'delivery_rate' => $performance->delivery_rate,
                    'bonus_eligible' => $performance->bonus_eligible,
                ];
            })
            ->toArray();
    }

    /**
     * Get rep statistics
     */
    private function getRepStatistics($rep): array
    {
        $orders = $rep->orders;
        
        return [
            'total_orders' => $orders->count(),
            'delivered_orders' => $orders->where('status', 'delivered')->count(),
            'ghosted_orders' => $orders->where('is_ghosted', true)->count(),
            'pending_orders' => $orders->where('status', 'pending')->count(),
            'total_revenue' => $orders->where('payment_status', 'confirmed')->sum('total_amount'),
            'avg_order_value' => $orders->where('payment_status', 'confirmed')->avg('total_amount') ?? 0,
            'response_time_avg' => $this->calculateAverageResponseTime($orders),
        ];
    }

    /**
     * Calculate average response time
     */
    private function calculateAverageResponseTime($orders): float
    {
        $responseTimes = $orders->filter(function($order) {
            return $order->created_at && $order->assigned_at;
        })->map(function($order) {
            return $order->assigned_at->diffInMinutes($order->created_at);
        });

        return $responseTimes->count() > 0 ? round($responseTimes->avg(), 2) : 0;
    }
}

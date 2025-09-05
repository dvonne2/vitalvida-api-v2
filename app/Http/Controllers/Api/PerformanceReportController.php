<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PerformanceMetric;
use App\Models\DeliveryAgent;
use App\Models\Sale;
use App\Models\OtpVerification;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class PerformanceReportController extends Controller
{
    /**
     * Get agent performance.
     */
    public function agentPerformance(int $agentId, Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $agent = DeliveryAgent::findOrFail($agentId);

        // Get performance metrics
        $metrics = PerformanceMetric::where('delivery_agent_id', $agentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->get();

        // Calculate averages
        $averages = [
            'delivery_rate' => $metrics->avg('delivery_rate'),
            'otp_success_rate' => $metrics->avg('otp_success_rate'),
            'stock_accuracy' => $metrics->avg('stock_accuracy'),
            'customer_satisfaction' => $metrics->avg('customer_satisfaction'),
            'delivery_time_avg' => $metrics->avg('delivery_time_avg'),
            'sales_amount' => $metrics->sum('sales_amount'),
            'orders_completed' => $metrics->sum('orders_completed'),
            'orders_total' => $metrics->sum('orders_total'),
            'returns_count' => $metrics->sum('returns_count'),
            'complaints_count' => $metrics->sum('complaints_count'),
            'bonus_earned' => $metrics->sum('bonus_earned'),
            'penalties_incurred' => $metrics->sum('penalties_incurred')
        ];

        // Get recent sales
        $recentSales = Sale::where('delivery_agent_id', $agentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->with(['customer', 'items.item'])
            ->orderBy('date', 'desc')
            ->limit(10)
            ->get();

        // Get OTP statistics
        $otpStats = OtpVerification::where('delivery_agent_id', $agentId)
            ->whereBetween('generated_at', [$startDate, $endDate])
            ->selectRaw('
                action_type,
                COUNT(*) as total_generated,
                COUNT(CASE WHEN status = "verified" THEN 1 END) as successful,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
                COUNT(CASE WHEN status = "expired" THEN 1 END) as expired
            ')
            ->groupBy('action_type')
            ->get();

        $performance = [
            'agent' => [
                'id' => $agent->id,
                'name' => $agent->name,
                'code' => $agent->code,
                'location' => $agent->location,
                'status' => $agent->status
            ],
            'averages' => $averages,
            'recent_sales' => $recentSales,
            'otp_statistics' => $otpStats,
            'performance_level' => $this->getPerformanceLevel($averages['delivery_rate'], $averages['otp_success_rate']),
            'trends' => $this->getPerformanceTrends($agentId, $startDate, $endDate)
        ];

        return response()->json([
            'success' => true,
            'data' => $performance,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get overall performance.
     */
    public function overallPerformance(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $metrics = PerformanceMetric::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                AVG(delivery_rate) as avg_delivery_rate,
                AVG(otp_success_rate) as avg_otp_success_rate,
                AVG(stock_accuracy) as avg_stock_accuracy,
                AVG(customer_satisfaction) as avg_customer_satisfaction,
                SUM(sales_amount) as total_sales,
                SUM(orders_completed) as total_orders_completed,
                SUM(orders_total) as total_orders,
                SUM(returns_count) as total_returns,
                SUM(complaints_count) as total_complaints,
                SUM(bonus_earned) as total_bonus_earned,
                SUM(penalties_incurred) as total_penalties
            ')
            ->first();

        $topPerformers = PerformanceMetric::whereBetween('date', [$startDate, $endDate])
            ->with('deliveryAgent')
            ->selectRaw('
                delivery_agent_id,
                AVG(delivery_rate) as avg_delivery_rate,
                AVG(otp_success_rate) as avg_otp_success_rate,
                SUM(sales_amount) as total_sales
            ')
            ->groupBy('delivery_agent_id')
            ->orderBy('avg_delivery_rate', 'desc')
            ->limit(10)
            ->get();

        $performance = [
            'overall_metrics' => $metrics,
            'top_performers' => $topPerformers,
            'performance_distribution' => $this->getPerformanceDistribution($startDate, $endDate),
            'system_health' => $this->getSystemHealth($startDate, $endDate)
        ];

        return response()->json([
            'success' => true,
            'data' => $performance,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get performance trends.
     */
    public function performanceTrends(Request $request): JsonResponse
    {
        $days = $request->get('days', 30);
        $startDate = now()->subDays($days)->toDateString();
        $endDate = now()->toDateString();

        $trends = PerformanceMetric::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                date,
                AVG(delivery_rate) as avg_delivery_rate,
                AVG(otp_success_rate) as avg_otp_success_rate,
                AVG(stock_accuracy) as avg_stock_accuracy,
                SUM(sales_amount) as total_sales,
                SUM(orders_completed) as total_orders_completed
            ')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return response()->json([
            'success' => true,
            'data' => $trends,
            'filters' => [
                'days' => $days,
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get OTP statistics.
     */
    public function otpStatistics(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());

        $stats = OtpVerification::whereBetween('generated_at', [$startDate, $endDate])
            ->selectRaw('
                action_type,
                COUNT(*) as total_generated,
                COUNT(CASE WHEN status = "verified" THEN 1 END) as successful,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
                COUNT(CASE WHEN status = "expired" THEN 1 END) as expired,
                AVG(CASE WHEN status = "verified" THEN attempts END) as avg_attempts_success,
                AVG(CASE WHEN status = "failed" THEN attempts END) as avg_attempts_failed
            ')
            ->groupBy('action_type')
            ->get()
            ->map(function ($stat) {
                $successRate = $stat->total_generated > 0 ? 
                    round(($stat->successful / $stat->total_generated) * 100, 2) : 0;

                return [
                    'action_type' => $stat->action_type,
                    'total_generated' => $stat->total_generated,
                    'successful' => $stat->successful,
                    'failed' => $stat->failed,
                    'expired' => $stat->expired,
                    'success_rate' => $successRate,
                    'avg_attempts_success' => round($stat->avg_attempts_success ?? 0, 2),
                    'avg_attempts_failed' => round($stat->avg_attempts_failed ?? 0, 2)
                ];
            });

        return response()->json([
            'success' => true,
            'data' => $stats,
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate
            ]
        ]);
    }

    /**
     * Get performance level.
     */
    private function getPerformanceLevel(float $deliveryRate, float $otpSuccessRate): string
    {
        $score = ($deliveryRate + $otpSuccessRate) / 2;
        
        if ($score >= 90) return 'excellent';
        if ($score >= 80) return 'good';
        if ($score >= 70) return 'average';
        if ($score >= 60) return 'below_average';
        return 'poor';
    }

    /**
     * Get performance trends.
     */
    private function getPerformanceTrends(int $agentId, string $startDate, string $endDate): array
    {
        $trends = PerformanceMetric::where('delivery_agent_id', $agentId)
            ->whereBetween('date', [$startDate, $endDate])
            ->orderBy('date')
            ->get(['date', 'delivery_rate', 'otp_success_rate', 'sales_amount']);

        return [
            'delivery_rate_trend' => $trends->pluck('delivery_rate', 'date'),
            'otp_success_rate_trend' => $trends->pluck('otp_success_rate', 'date'),
            'sales_amount_trend' => $trends->pluck('sales_amount', 'date')
        ];
    }

    /**
     * Get performance distribution.
     */
    private function getPerformanceDistribution(string $startDate, string $endDate): array
    {
        $distribution = PerformanceMetric::whereBetween('date', [$startDate, $endDate])
            ->selectRaw('
                CASE 
                    WHEN AVG(delivery_rate) >= 90 THEN "excellent"
                    WHEN AVG(delivery_rate) >= 80 THEN "good"
                    WHEN AVG(delivery_rate) >= 70 THEN "average"
                    WHEN AVG(delivery_rate) >= 60 THEN "below_average"
                    ELSE "poor"
                END as performance_level,
                COUNT(*) as agent_count
            ')
            ->groupBy('delivery_agent_id')
            ->groupBy('performance_level')
            ->get();

        return $distribution->pluck('agent_count', 'performance_level')->toArray();
    }

    /**
     * Get system health.
     */
    private function getSystemHealth(string $startDate, string $endDate): array
    {
        $avgDeliveryRate = PerformanceMetric::whereBetween('date', [$startDate, $endDate])
            ->avg('delivery_rate');

        $avgOtpSuccessRate = PerformanceMetric::whereBetween('date', [$startDate, $endDate])
            ->avg('otp_success_rate');

        $totalSales = Sale::whereBetween('date', [$startDate, $endDate])
            ->where('otp_verified', true)
            ->count();

        $totalOrders = Sale::whereBetween('date', [$startDate, $endDate])
            ->count();

        return [
            'delivery_rate_health' => $avgDeliveryRate >= 85 ? 'good' : 'needs_attention',
            'otp_success_rate_health' => $avgOtpSuccessRate >= 90 ? 'good' : 'needs_attention',
            'verification_rate' => $totalOrders > 0 ? round(($totalSales / $totalOrders) * 100, 2) : 0,
            'overall_health' => ($avgDeliveryRate >= 85 && $avgOtpSuccessRate >= 90) ? 'healthy' : 'needs_improvement'
        ];
    }
} 
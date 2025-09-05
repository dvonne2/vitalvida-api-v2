<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Payment;
use App\Models\PaymentMismatch;
use App\Models\Order;
use App\Models\OtpVerification;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class PaymentAnalyticsController extends Controller
{
    /**
     * Get payment matching accuracy for accountant performance
     */
    public function getMatchingAccuracy(Request $request)
    {
        $user = $request->user();
        $period = $request->get('period', 'week'); // week, month, day

        $startDate = $this->getStartDate($period);

        // Get total payments processed
        $totalPayments = Payment::where('created_at', '>=', $startDate)->count();

        // Get mismatches
        $mismatches = PaymentMismatch::whereHas('payment', function($query) use ($startDate) {
            $query->where('created_at', '>=', $startDate);
        })->count();

        $accuracy = $totalPayments > 0 ? (($totalPayments - $mismatches) / $totalPayments) * 100 : 100;

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => [
                'total_payments' => $totalPayments,
                'successful_matches' => $totalPayments - $mismatches,
                'mismatches' => $mismatches,
                'accuracy_percentage' => round($accuracy, 2),
                'target_accuracy' => 100,
                'meets_target' => $accuracy >= 100,
                'penalty_amount' => $mismatches * 10000, // ₦10,000 per mismatch
                'performance_grade' => $this->getPerformanceGrade($accuracy)
            ]
        ]);
    }

    /**
     * Get detailed mismatch investigation data
     */
    public function getMismatchDetails(Request $request)
    {
        $mismatches = PaymentMismatch::with(['order.customer', 'payment'])
            ->when($request->status, function($query, $status) {
                if ($status === 'unresolved') {
                    $query->where('investigation_required', true);
                } elseif ($status === 'resolved') {
                    $query->whereNotNull('resolved_at');
                }
            })
            ->when($request->type, function($query, $type) {
                $query->where('mismatch_type', $type);
            })
            ->when($request->priority, function($query, $priority) {
                if ($priority === 'high') {
                    $query->where('created_at', '<', now()->subDays(3));
                }
            })
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => [
                'mismatches' => $mismatches->map(function($mismatch) {
                    return [
                        'id' => $mismatch->id,
                        'mismatch_id' => $mismatch->mismatch_id,
                        'order_number' => $mismatch->order->order_number,
                        'customer_name' => $mismatch->order->customer->name ?? 'N/A',
                        'mismatch_type' => $mismatch->mismatch_type,
                        'entered_phone' => $mismatch->entered_phone,
                        'actual_phone' => $mismatch->actual_phone,
                        'entered_order_id' => $mismatch->entered_order_id,
                        'actual_order_id' => $mismatch->actual_order_id,
                        'payment_amount' => $mismatch->payment_amount,
                        'penalty_amount' => $mismatch->penalty_amount,
                        'severity_level' => $mismatch->severity_level,
                        'days_since_created' => $mismatch->days_since_created,
                        'created_at' => $mismatch->created_at,
                        'investigation_notes' => $mismatch->investigation_notes,
                        'resolved' => $mismatch->resolved_at !== null,
                        'resolved_at' => $mismatch->resolved_at,
                        'penalty_applied' => $mismatch->penalty_applied
                    ];
                }),
                'pagination' => [
                    'current_page' => $mismatches->currentPage(),
                    'total_pages' => $mismatches->lastPage(),
                    'total_items' => $mismatches->total(),
                    'per_page' => $mismatches->perPage()
                ]
            ]
        ]);
    }

    /**
     * Real-time payment status dashboard
     */
    public function getPaymentStatus(Request $request)
    {
        $today = Carbon::today();
        $period = $request->get('period', 'today');
        $startDate = $this->getStartDate($period);

        $stats = [
            'payments' => [
                'total_received' => Payment::where('created_at', '>=', $startDate)->count(),
                'verified' => Payment::where('created_at', '>=', $startDate)
                    ->where('status', 'confirmed')->count(),
                'failed' => Payment::where('created_at', '>=', $startDate)
                    ->where('status', 'failed')->count(),
                'pending' => Payment::where('created_at', '>=', $startDate)
                    ->where('status', 'pending')->count(),
                'total_amount' => Payment::where('created_at', '>=', $startDate)
                    ->where('status', 'confirmed')->sum('amount')
            ],
            'otps' => [
                'sent' => OtpVerification::where('sent_at', '>=', $startDate)->count(),
                'verified' => OtpVerification::where('verified_at', '>=', $startDate)->count(),
                'pending' => OtpVerification::where('status', 'pending')
                    ->where('expires_at', '>', now())->count(),
                'expired' => OtpVerification::where('status', 'pending')
                    ->where('expires_at', '<', now())->count()
            ],
            'mismatches' => [
                'total' => PaymentMismatch::where('created_at', '>=', $startDate)->count(),
                'unresolved' => PaymentMismatch::where('investigation_required', true)->count(),
                'high_priority' => PaymentMismatch::where('created_at', '<', now()->subDays(3))
                    ->where('investigation_required', true)->count(),
                'total_penalties' => PaymentMismatch::where('created_at', '>=', $startDate)
                    ->sum('penalty_amount')
            ],
            'orders' => [
                'paid_today' => Order::where('payment_status', 'paid')
                    ->whereDate('updated_at', $today)->count(),
                'otp_verified' => Order::where('otp_verified', true)
                    ->whereDate('otp_verified_at', $today)->count(),
                'ready_for_delivery' => Order::where('status', 'ready_for_delivery')->count()
            ]
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $stats,
            'recent_activity' => $this->getRecentPaymentActivity(),
            'performance_metrics' => $this->getPerformanceMetricsData($startDate)
        ]);
    }

    /**
     * Get payment processing performance metrics
     */
    public function getPerformanceMetrics(Request $request)
    {
        $period = $request->get('period', 'week');
        $startDate = $this->getStartDate($period);

        $metrics = [
            'processing_times' => [
                'average_seconds' => $this->calculateAverageProcessingTime($startDate),
                'fastest_seconds' => $this->getFastestProcessingTime($startDate),
                'slowest_seconds' => $this->getSlowestProcessingTime($startDate)
            ],
            'success_rates' => [
                'payment_verification' => $this->getPaymentVerificationRate($startDate),
                'otp_delivery' => $this->getOtpDeliveryRate($startDate),
                'order_completion' => $this->getOrderCompletionRate($startDate)
            ],
            'volume_trends' => $this->getVolumeData($startDate, $period),
            'error_analysis' => $this->getErrorAnalysis($startDate)
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $metrics
        ]);
    }

    /**
     * Get hourly payment volume data
     */
    public function getHourlyVolume(Request $request)
    {
        $date = $request->get('date', today()->toDateString());
        
        $hourlyData = Payment::whereDate('created_at', $date)
            ->select(
                DB::raw('HOUR(created_at) as hour'),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount'),
                DB::raw('SUM(CASE WHEN status = "confirmed" THEN 1 ELSE 0 END) as successful')
            )
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        return response()->json([
            'success' => true,
            'date' => $date,
            'data' => $hourlyData
        ]);
    }

    /**
     * Get mismatch trends analysis
     */
    public function getMismatchTrends(Request $request)
    {
        $period = $request->get('period', 'week');
        $startDate = $this->getStartDate($period);

        $trends = [
            'by_type' => PaymentMismatch::where('created_at', '>=', $startDate)
                ->select('mismatch_type', DB::raw('COUNT(*) as count'))
                ->groupBy('mismatch_type')
                ->get(),
            'by_day' => PaymentMismatch::where('created_at', '>=', $startDate)
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('COUNT(*) as count'),
                    DB::raw('SUM(penalty_amount) as total_penalties')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get(),
            'resolution_times' => $this->getResolutionTimeAnalysis($startDate)
        ];

        return response()->json([
            'success' => true,
            'period' => $period,
            'data' => $trends
        ]);
    }

    // Private helper methods

    private function getStartDate(string $period): Carbon
    {
        return match($period) {
            'today' => Carbon::today(),
            'yesterday' => Carbon::yesterday(),
            'week' => Carbon::now()->startOfWeek(),
            'month' => Carbon::now()->startOfMonth(),
            'year' => Carbon::now()->startOfYear(),
            default => Carbon::today()
        };
    }

    private function getPerformanceGrade(float $accuracy): string
    {
        if ($accuracy >= 99.5) return 'A+';
        if ($accuracy >= 99.0) return 'A';
        if ($accuracy >= 98.0) return 'B+';
        if ($accuracy >= 95.0) return 'B';
        if ($accuracy >= 90.0) return 'C';
        return 'F';
    }

    private function getRecentPaymentActivity(): array
    {
        return Payment::with(['order.customer'])
            ->latest()
            ->limit(10)
            ->get()
            ->map(function($payment) {
                return [
                    'id' => $payment->id,
                    'payment_id' => $payment->payment_id,
                    'order_number' => $payment->order->order_number ?? 'N/A',
                    'customer_name' => $payment->order->customer->name ?? 'N/A',
                    'amount' => $payment->amount,
                    'status' => $payment->status,
                    'created_at' => $payment->created_at,
                    'time_ago' => $payment->created_at->diffForHumans()
                ];
            })->toArray();
    }

    private function getPerformanceMetricsData($startDate): array
    {
        $totalPayments = Payment::where('created_at', '>=', $startDate)->count();
        $successfulPayments = Payment::where('created_at', '>=', $startDate)
            ->where('status', 'confirmed')->count();
        
        return [
            'success_rate' => $totalPayments > 0 ? round(($successfulPayments / $totalPayments) * 100, 2) : 0,
            'average_processing_time' => $this->calculateAverageProcessingTime($startDate),
            'total_processed' => $totalPayments,
            'total_amount' => Payment::where('created_at', '>=', $startDate)
                ->where('status', 'confirmed')->sum('amount')
        ];
    }

    private function calculateAverageProcessingTime($startDate): float
    {
        $payments = Payment::where('created_at', '>=', $startDate)
            ->whereNotNull('verified_at')
            ->get();

        if ($payments->isEmpty()) {
            return 0;
        }

        $totalSeconds = $payments->sum(function ($payment) {
            return $payment->created_at->diffInSeconds($payment->verified_at);
        });

        return round($totalSeconds / $payments->count(), 2);
    }

    private function getFastestProcessingTime($startDate): float
    {
        $payment = Payment::where('created_at', '>=', $startDate)
            ->whereNotNull('verified_at')
            ->get()
            ->min(function ($payment) {
                return $payment->created_at->diffInSeconds($payment->verified_at);
            });

        return $payment ?? 0;
    }

    private function getSlowestProcessingTime($startDate): float
    {
        $payment = Payment::where('created_at', '>=', $startDate)
            ->whereNotNull('verified_at')
            ->get()
            ->max(function ($payment) {
                return $payment->created_at->diffInSeconds($payment->verified_at);
            });

        return $payment ?? 0;
    }

    private function getPaymentVerificationRate($startDate): float
    {
        $total = Payment::where('created_at', '>=', $startDate)->count();
        $verified = Payment::where('created_at', '>=', $startDate)
            ->where('status', 'confirmed')->count();

        return $total > 0 ? round(($verified / $total) * 100, 2) : 0;
    }

    private function getOtpDeliveryRate($startDate): float
    {
        $total = OtpVerification::where('sent_at', '>=', $startDate)->count();
        $delivered = OtpVerification::where('sent_at', '>=', $startDate)
            ->where('sms_sent', true)->count();

        return $total > 0 ? round(($delivered / $total) * 100, 2) : 0;
    }

    private function getOrderCompletionRate($startDate): float
    {
        $total = Order::where('created_at', '>=', $startDate)->count();
        $completed = Order::where('created_at', '>=', $startDate)
            ->where('status', 'delivered')->count();

        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function getVolumeData($startDate, $period): array
    {
        $groupBy = match($period) {
            'today' => 'HOUR(created_at)',
            'week' => 'DATE(created_at)',
            'month' => 'DATE(created_at)',
            'year' => 'MONTH(created_at)',
            default => 'DATE(created_at)'
        };

        return Payment::where('created_at', '>=', $startDate)
            ->select(
                DB::raw("$groupBy as period"),
                DB::raw('COUNT(*) as count'),
                DB::raw('SUM(amount) as total_amount')
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get()
            ->toArray();
    }

    private function getErrorAnalysis($startDate): array
    {
        return [
            'failed_payments' => Payment::where('created_at', '>=', $startDate)
                ->where('status', 'failed')->count(),
            'payment_mismatches' => PaymentMismatch::where('created_at', '>=', $startDate)->count(),
            'failed_otps' => OtpVerification::where('sent_at', '>=', $startDate)
                ->where('status', 'failed')->count(),
            'common_issues' => $this->getCommonIssues($startDate)
        ];
    }

    private function getCommonIssues($startDate): array
    {
        return PaymentMismatch::where('created_at', '>=', $startDate)
            ->select('mismatch_type', DB::raw('COUNT(*) as count'))
            ->groupBy('mismatch_type')
            ->orderBy('count', 'desc')
            ->limit(5)
            ->get()
            ->toArray();
    }

    private function getResolutionTimeAnalysis($startDate): array
    {
        $resolved = PaymentMismatch::where('created_at', '>=', $startDate)
            ->whereNotNull('resolved_at')
            ->get();

        if ($resolved->isEmpty()) {
            return [
                'average_hours' => 0,
                'fastest_hours' => 0,
                'slowest_hours' => 0
            ];
        }

        $resolutionTimes = $resolved->map(function($mismatch) {
            return $mismatch->created_at->diffInHours($mismatch->resolved_at);
        });

        return [
            'average_hours' => round($resolutionTimes->avg(), 2),
            'fastest_hours' => $resolutionTimes->min(),
            'slowest_hours' => $resolutionTimes->max()
        ];
    }
}

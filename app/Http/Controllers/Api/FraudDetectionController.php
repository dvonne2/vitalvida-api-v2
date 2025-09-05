<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Models\PaymentMismatch;
use App\Models\Staff;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class FraudDetectionController extends Controller
{
    /**
     * Get fraud alerts
     */
    public function alerts(Request $request): JsonResponse
    {
        $severity = $request->get('severity', 'all');
        $status = $request->get('status', 'active');
        
        $alerts = $this->generateFraudAlerts($severity, $status);

        return response()->json([
            'status' => 'success',
            'data' => $alerts,
            'summary' => $this->getAlertSummary($alerts),
        ]);
    }

    /**
     * Get fraud statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $startDate = $period === 'today' ? today() : now()->startOfWeek();
        $endDate = $period === 'today' ? today() : now();

        $stats = [
            'total_alerts' => $this->getTotalAlerts($startDate, $endDate),
            'critical_alerts' => $this->getCriticalAlerts($startDate, $endDate),
            'high_alerts' => $this->getHighAlerts($startDate, $endDate),
            'medium_alerts' => $this->getMediumAlerts($startDate, $endDate),
            'fraud_amount' => $this->getFraudAmount($startDate, $endDate),
            'prevented_loss' => $this->getPreventedLoss($startDate, $endDate),
            'detection_rate' => $this->getDetectionRate($startDate, $endDate),
            'false_positives' => $this->getFalsePositives($startDate, $endDate),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
            'period' => $period,
        ]);
    }

    /**
     * Acknowledge fraud alert
     */
    public function acknowledgeAlert(Request $request, $id): JsonResponse
    {
        $request->validate([
            'action_taken' => 'required|in:investigate,block,ignore,resolve',
            'notes' => 'nullable|string|max:500',
        ]);

        // Log the acknowledgment
        OrderHistory::create([
            'order_id' => null,
            'staff_id' => auth()->id(),
            'action' => 'fraud_alert_acknowledged',
            'previous_status' => 'active',
            'new_status' => 'acknowledged',
            'timestamp' => now(),
            'notes' => "Fraud alert acknowledged. Action: {$request->action_taken}. Notes: {$request->notes}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Alert acknowledged successfully',
            'data' => [
                'alert_id' => $id,
                'action_taken' => $request->action_taken,
                'acknowledged_by' => auth()->user()->name,
                'acknowledged_at' => now()->toISOString(),
            ],
        ]);
    }

    /**
     * Run fraud detection scan
     */
    public function runScan(Request $request): JsonResponse
    {
        $scanType = $request->get('scan_type', 'comprehensive');
        
        $results = match($scanType) {
            'payment_mismatch' => $this->scanPaymentMismatches(),
            'ghost_patterns' => $this->scanGhostPatterns(),
            'suspicious_activity' => $this->scanSuspiciousActivity(),
            'comprehensive' => $this->runComprehensiveScan(),
            default => $this->runComprehensiveScan(),
        };

        return response()->json([
            'status' => 'success',
            'message' => 'Fraud detection scan completed',
            'data' => $results,
            'scan_type' => $scanType,
            'scan_timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * Generate fraud alerts
     */
    private function generateFraudAlerts($severity = 'all', $status = 'active'): array
    {
        $alerts = [];

        // Payment mismatch alerts
        $paymentMismatches = PaymentMismatch::where('resolution_status', 'pending')
            ->with(['order', 'staff'])
            ->get();

        foreach ($paymentMismatches as $mismatch) {
            $alertSeverity = $mismatch->amount_difference > 100000 ? 'CRITICAL' : 'HIGH';
            $staffName = $mismatch->staff->user->name ?? 'Unknown';
            
            if ($severity === 'all' || $severity === $alertSeverity) {
                $alerts[] = [
                    'id' => 'PM_' . $mismatch->id,
                    'type' => 'PAYMENT_MISMATCH',
                    'severity' => $alertSeverity,
                    'title' => 'Payment Mismatch Detected',
                    'message' => "Staff {$staffName} claimed ₦{$mismatch->order->total_amount} but Moniepoint received ₦0",
                    'amount' => $mismatch->amount_difference,
                    'order_id' => $mismatch->order_id,
                    'staff_id' => $mismatch->staff_id,
                    'detected_at' => $mismatch->created_at->toISOString(),
                    'status' => 'active',
                    'auto_frozen' => $mismatch->auto_frozen,
                ];
            }
        }

        // High ghost rate alerts
        $highGhostRateReps = Staff::where('staff_type', 'telesales_rep')
            ->where('status', 'active')
            ->get()
            ->filter(function ($staff) {
                $totalOrders = $staff->completed_orders + $staff->ghosted_orders;
                return $totalOrders > 0 && ($staff->ghosted_orders / $totalOrders) > 0.8;
            });

        foreach ($highGhostRateReps as $rep) {
            $ghostRate = round(($rep->ghosted_orders / ($rep->completed_orders + $rep->ghosted_orders)) * 100, 2);
            
            if ($severity === 'all' || $severity === 'HIGH') {
                $alerts[] = [
                    'id' => 'GR_' . $rep->user_id,
                    'type' => 'HIGH_GHOST_RATE',
                    'severity' => 'HIGH',
                    'title' => 'High Ghost Rate Detected',
                    'message' => "{$rep->user->name} has {$ghostRate}% ghost rate ({$rep->ghosted_orders} ghosted out of " . ($rep->completed_orders + $rep->ghosted_orders) . " total orders)",
                    'ghost_rate' => $ghostRate,
                    'staff_id' => $rep->user_id,
                    'detected_at' => now()->toISOString(),
                    'status' => 'active',
                    'auto_frozen' => false,
                ];
            }
        }

        // Suspicious order patterns
        $suspiciousOrders = Order::whereNotNull('fraud_flags')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        foreach ($suspiciousOrders as $order) {
            $flags = $order->fraud_flags;
            $flagCount = count($flags);
            
            if ($flagCount >= 2) {
                $alerts[] = [
                    'id' => 'SO_' . $order->id,
                    'type' => 'SUSPICIOUS_ORDER',
                    'severity' => 'MEDIUM',
                    'title' => 'Suspicious Order Pattern',
                    'message' => "Order {$order->order_number} has {$flagCount} fraud flags",
                    'order_id' => $order->id,
                    'fraud_flags' => $flags,
                    'detected_at' => now()->toISOString(),
                    'status' => 'active',
                    'auto_frozen' => false,
                ];
            }
        }

        // Stagnant inventory alerts
        $stagnantInventory = DaInventory::where('days_stagnant', '>=', 5)
            ->with(['deliveryAgent.user'])
            ->get();

        foreach ($stagnantInventory as $inventory) {
            $alerts[] = [
                'id' => 'SI_' . $inventory->id,
                'type' => 'STAGNANT_INVENTORY',
                'severity' => 'MEDIUM',
                'title' => 'Stagnant Inventory Detected',
                'message' => "{$inventory->deliveryAgent->user->name} has {$inventory->quantity} {$inventory->product_type} stagnant for {$inventory->days_stagnant} days",
                'da_id' => $inventory->da_id,
                'product_type' => $inventory->product_type,
                'days_stagnant' => $inventory->days_stagnant,
                'detected_at' => now()->toISOString(),
                'status' => 'active',
                'auto_frozen' => false,
            ];
        }

        return $alerts;
    }

    /**
     * Get alert summary
     */
    private function getAlertSummary($alerts): array
    {
        $totalAlerts = count($alerts);
        $criticalAlerts = collect($alerts)->where('severity', 'CRITICAL')->count();
        $highAlerts = collect($alerts)->where('severity', 'HIGH')->count();
        $mediumAlerts = collect($alerts)->where('severity', 'MEDIUM')->count();
        $autoFrozen = collect($alerts)->where('auto_frozen', true)->count();

        return [
            'total_alerts' => $totalAlerts,
            'critical_alerts' => $criticalAlerts,
            'high_alerts' => $highAlerts,
            'medium_alerts' => $mediumAlerts,
            'auto_frozen' => $autoFrozen,
            'risk_level' => $this->calculateRiskLevel($criticalAlerts, $highAlerts),
        ];
    }

    /**
     * Calculate risk level
     */
    private function calculateRiskLevel($criticalAlerts, $highAlerts): string
    {
        if ($criticalAlerts > 0) return 'CRITICAL';
        if ($highAlerts > 5) return 'HIGH';
        if ($highAlerts > 0) return 'MEDIUM';
        return 'LOW';
    }

    /**
     * Scan for payment mismatches
     */
    private function scanPaymentMismatches(): array
    {
        $mismatches = PaymentMismatch::where('resolution_status', 'pending')->count();
        $totalAmount = PaymentMismatch::where('resolution_status', 'pending')->sum('amount_difference');
        
        return [
            'mismatches_found' => $mismatches,
            'total_amount' => $totalAmount,
            'average_amount' => $mismatches > 0 ? round($totalAmount / $mismatches, 2) : 0,
        ];
    }

    /**
     * Scan for ghost patterns
     */
    private function scanGhostPatterns(): array
    {
        $highGhostRateReps = Staff::where('staff_type', 'telesales_rep')
            ->where('status', 'active')
            ->get()
            ->filter(function ($staff) {
                $totalOrders = $staff->completed_orders + $staff->ghosted_orders;
                return $totalOrders > 0 && ($staff->ghosted_orders / $totalOrders) > 0.8;
            });

        return [
            'high_ghost_rate_reps' => $highGhostRateReps->count(),
            'total_ghosted_orders' => $highGhostRateReps->sum('ghosted_orders'),
            'average_ghost_rate' => $highGhostRateReps->count() > 0 ? 
                round($highGhostRateReps->avg(function($rep) {
                    $totalOrders = $rep->completed_orders + $rep->ghosted_orders;
                    return $totalOrders > 0 ? ($rep->ghosted_orders / $totalOrders) * 100 : 0;
                }), 2) : 0,
        ];
    }

    /**
     * Scan for suspicious activity
     */
    private function scanSuspiciousActivity(): array
    {
        $suspiciousOrders = Order::whereNotNull('fraud_flags')
            ->where('created_at', '>=', now()->subDays(7))
            ->get();

        $multipleFlags = $suspiciousOrders->filter(function($order) {
            return count($order->fraud_flags) >= 2;
        });

        return [
            'suspicious_orders' => $suspiciousOrders->count(),
            'multiple_flags' => $multipleFlags->count(),
            'total_flags' => $suspiciousOrders->sum(function($order) {
                return count($order->fraud_flags);
            }),
        ];
    }

    /**
     * Run comprehensive fraud scan
     */
    private function runComprehensiveScan(): array
    {
        return [
            'payment_mismatches' => $this->scanPaymentMismatches(),
            'ghost_patterns' => $this->scanGhostPatterns(),
            'suspicious_activity' => $this->scanSuspiciousActivity(),
            'scan_timestamp' => now()->toISOString(),
        ];
    }

    /**
     * Get total alerts for period
     */
    private function getTotalAlerts($startDate, $endDate): int
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])->count() +
               Order::whereBetween('created_at', [$startDate, $endDate])->whereNotNull('fraud_flags')->count();
    }

    /**
     * Get critical alerts for period
     */
    private function getCriticalAlerts($startDate, $endDate): int
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->where('amount_difference', '>', 100000)
            ->count();
    }

    /**
     * Get high alerts for period
     */
    private function getHighAlerts($startDate, $endDate): int
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->where('amount_difference', '<=', 100000)
            ->where('amount_difference', '>', 50000)
            ->count();
    }

    /**
     * Get medium alerts for period
     */
    private function getMediumAlerts($startDate, $endDate): int
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->where('amount_difference', '<=', 50000)
            ->count();
    }

    /**
     * Get fraud amount for period
     */
    private function getFraudAmount($startDate, $endDate): float
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])->sum('amount_difference');
    }

    /**
     * Get prevented loss for period
     */
    private function getPreventedLoss($startDate, $endDate): float
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->where('auto_frozen', true)
            ->sum('amount_difference');
    }

    /**
     * Get detection rate for period
     */
    private function getDetectionRate($startDate, $endDate): float
    {
        $totalPayments = Payment::whereBetween('created_at', [$startDate, $endDate])->count();
        $detectedMismatches = PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])->count();
        
        return $totalPayments > 0 ? round(($detectedMismatches / $totalPayments) * 100, 2) : 0;
    }

    /**
     * Get false positives for period
     */
    private function getFalsePositives($startDate, $endDate): int
    {
        return PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->where('resolution_status', 'false_positive')
            ->count();
    }
}

<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\KPIMetric;
use App\Models\Order;
use App\Models\Consignment;
use App\Models\FraudAlert;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class KPIController extends Controller
{
    /**
     * Get KPI performance scorecard
     */
    public function performance(): JsonResponse
    {
        try {
            // Get or create KPI metrics
            $dispatchAccuracy = KPIMetric::updateDispatchAccuracyRate(98.5);
            $deliveryChainMatch = KPIMetric::updateDeliveryChainMatchRate(97.2);
            $proofCompliance = KPIMetric::updateProofComplianceScore(94.8);
            $slaRelayCompletion = KPIMetric::updateSLARelayCompletionRate(96.3);
            $fraudEscalationResponse = KPIMetric::updateFraudEscalationResponseTime(26);

            $kpis = [
                [
                    'name' => 'Dispatch Accuracy Rate',
                    'current' => $dispatchAccuracy->current_value,
                    'target' => $dispatchAccuracy->target_value,
                    'status' => $dispatchAccuracy->status
                ],
                [
                    'name' => 'Delivery Chain Match Rate',
                    'current' => $deliveryChainMatch->current_value,
                    'target' => $deliveryChainMatch->target_value,
                    'status' => $deliveryChainMatch->status
                ],
                [
                    'name' => 'Proof Compliance Score',
                    'current' => $proofCompliance->current_value,
                    'target' => $proofCompliance->target_value,
                    'status' => $proofCompliance->status
                ],
                [
                    'name' => 'SLA Relay Completion Rate',
                    'current' => $slaRelayCompletion->current_value,
                    'target' => $slaRelayCompletion->target_value,
                    'status' => $slaRelayCompletion->status
                ],
                [
                    'name' => 'Fraud Escalation Response Time',
                    'current' => $fraudEscalationResponse->formatted_value,
                    'target' => $fraudEscalationResponse->formatted_target,
                    'status' => $fraudEscalationResponse->status
                ]
            ];

            return response()->json(['kpis' => $kpis]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch KPI performance',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KPI by name
     */
    public function show(string $name): JsonResponse
    {
        try {
            $kpi = KPIMetric::where('name', $name)
                ->orderBy('recorded_date', 'desc')
                ->first();

            if (!$kpi) {
                return response()->json([
                    'error' => 'KPI not found'
                ], 404);
            }

            $data = [
                'name' => $kpi->name,
                'current_value' => $kpi->current_value,
                'target_value' => $kpi->target_value,
                'unit' => $kpi->unit,
                'status' => $kpi->status,
                'period' => $kpi->period,
                'recorded_date' => $kpi->recorded_date->format('Y-m-d'),
                'formatted_value' => $kpi->formatted_value,
                'formatted_target' => $kpi->formatted_target,
                'is_on_target' => $kpi->isOnTarget(),
                'performance_percentage' => $kpi->performance_percentage
            ];

            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch KPI',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KPI trends
     */
    public function trends(string $name): JsonResponse
    {
        try {
            $trends = KPIMetric::where('name', $name)
                ->orderBy('recorded_date', 'desc')
                ->limit(30)
                ->get()
                ->map(function ($kpi) {
                    return [
                        'date' => $kpi->recorded_date->format('Y-m-d'),
                        'value' => $kpi->current_value,
                        'target' => $kpi->target_value,
                        'status' => $kpi->status
                    ];
                })
                ->reverse()
                ->values();

            return response()->json([
                'name' => $name,
                'trends' => $trends
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch KPI trends',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update KPI value
     */
    public function update(Request $request, string $name): JsonResponse
    {
        try {
            $request->validate([
                'value' => 'required|numeric',
                'period' => 'nullable|string|in:daily,weekly,monthly'
            ]);

            $kpi = KPIMetric::where('name', $name)
                ->where('period', $request->period ?? 'daily')
                ->where('recorded_date', today())
                ->first();

            if ($kpi) {
                $kpi->update([
                    'current_value' => $request->value
                ]);
            } else {
                $kpi = KPIMetric::create([
                    'name' => $name,
                    'current_value' => $request->value,
                    'target_value' => $this->getTargetValue($name),
                    'unit' => $this->getUnit($name),
                    'status' => $this->calculateStatus($name, $request->value),
                    'period' => $request->period ?? 'daily',
                    'recorded_date' => today()
                ]);
            }

            return response()->json([
                'message' => 'KPI updated successfully',
                'kpi' => [
                    'name' => $kpi->name,
                    'current_value' => $kpi->current_value,
                    'target_value' => $kpi->target_value,
                    'status' => $kpi->status
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update KPI',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get KPI summary
     */
    public function summary(): JsonResponse
    {
        try {
            $summary = [
                'total_kpis' => KPIMetric::count(),
                'on_target' => KPIMetric::where('current_value', '>=', 'target_value')->count(),
                'below_target' => KPIMetric::where('current_value', '<', 'target_value')->count(),
                'excellent_status' => KPIMetric::where('status', 'excellent')->count(),
                'good_status' => KPIMetric::where('status', 'good')->count(),
                'poor_status' => KPIMetric::where('status', 'poor')->count(),
                'today_updated' => KPIMetric::where('recorded_date', today())->count()
            ];

            return response()->json($summary);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch KPI summary',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get target value for KPI
     */
    private function getTargetValue(string $name): float
    {
        return match($name) {
            'Dispatch Accuracy Rate' => 100.0,
            'Delivery Chain Match Rate' => 95.0,
            'Proof Compliance Score' => 100.0,
            'SLA Relay Completion Rate' => 95.0,
            'Fraud Escalation Response Time' => 30.0,
            default => 100.0
        };
    }

    /**
     * Get unit for KPI
     */
    private function getUnit(string $name): string
    {
        return match($name) {
            'Fraud Escalation Response Time' => 'mins',
            default => '%'
        };
    }

    /**
     * Calculate status for KPI
     */
    private function calculateStatus(string $name, float $value): string
    {
        $target = $this->getTargetValue($name);
        $unit = $this->getUnit($name);

        if ($unit === 'mins') {
            // For time-based KPIs, lower is better
            if ($value <= $target) return 'excellent';
            if ($value <= $target * 1.5) return 'good';
            return 'poor';
        } else {
            // For percentage-based KPIs, higher is better
            if ($value >= $target) return 'excellent';
            if ($value >= $target * 0.9) return 'good';
            return 'poor';
        }
    }
}

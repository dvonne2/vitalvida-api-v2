<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\FraudAlert;
use App\Models\Consignment;
use App\Models\SystemAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class FraudDetectionController extends Controller
{
    /**
     * Get all fraud alerts
     */
    public function index(): JsonResponse
    {
        try {
            $fraudAlerts = FraudAlert::orderBy('created_at', 'desc')
                ->get()
                ->map(function ($alert) {
                    return [
                        'id' => $alert->alert_id,
                        'type' => $alert->type_display,
                        'status' => ucfirst($alert->status),
                        'description' => $alert->description,
                        'consignment_id' => $alert->consignment_id,
                        'da_id' => $alert->da_id,
                        'escalated_to' => $alert->escalated_to ?? [],
                        'auto_actions' => $alert->auto_actions ?? [],
                        'actions' => $this->getAvailableActions($alert),
                        'created_at' => $alert->created_at->format('Y-m-d H:i:s'),
                        'severity' => ucfirst($alert->severity),
                        'severity_color' => $alert->severity_color
                    ];
                });

            return response()->json(['fraud_alerts' => $fraudAlerts]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch fraud alerts',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new fraud alert
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|max:50',
                'description' => 'required|string',
                'severity' => 'required|in:low,medium,high,critical',
                'consignment_id' => 'nullable|string|max:20',
                'da_id' => 'nullable|string|max:50',
                'escalated_to' => 'nullable|array',
                'auto_actions' => 'nullable|array'
            ]);

            // Generate alert ID
            $alertId = 'VV-' . date('Y') . '-' . str_pad(FraudAlert::count() + 1, 3, '0', STR_PAD_LEFT);

            $fraudAlert = FraudAlert::create([
                'alert_id' => $alertId,
                'type' => $request->type,
                'status' => 'active',
                'description' => $request->description,
                'severity' => $request->severity,
                'consignment_id' => $request->consignment_id,
                'da_id' => $request->da_id,
                'escalated_to' => $request->escalated_to,
                'auto_actions' => $request->auto_actions
            ]);

            // Log the fraud detection
            SystemAuditLog::logFraudDetected($alertId, $request->type);

            return response()->json([
                'alert_id' => $alertId,
                'status' => 'created'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create fraud alert',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve fraud alert
     */
    public function resolve(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'action' => 'required|string',
                'notes' => 'nullable|string'
            ]);

            $fraudAlert = FraudAlert::where('alert_id', $id)->first();
            
            if (!$fraudAlert) {
                return response()->json([
                    'error' => 'Fraud alert not found'
                ], 404);
            }

            $fraudAlert->resolve(auth()->user()->name ?? 'system');

            // Add resolution action
            $fraudAlert->addAutoAction($request->action);

            return response()->json([
                'message' => 'Fraud alert resolved successfully',
                'status' => 'resolved'
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to resolve fraud alert',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Escalate fraud alert
     */
    public function escalate(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'roles' => 'required|array',
                'reason' => 'nullable|string'
            ]);

            $fraudAlert = FraudAlert::where('alert_id', $id)->first();
            
            if (!$fraudAlert) {
                return response()->json([
                    'error' => 'Fraud alert not found'
                ], 404);
            }

            $fraudAlert->escalate($request->roles);

            return response()->json([
                'message' => 'Fraud alert escalated successfully',
                'escalated_to' => $request->roles
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to escalate fraud alert',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get fraud alert statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => FraudAlert::count(),
                'active' => FraudAlert::active()->count(),
                'monitoring' => FraudAlert::monitoring()->count(),
                'resolved' => FraudAlert::resolved()->count(),
                'today' => FraudAlert::today()->count(),
                'by_type' => [
                    'quantity_mismatch' => FraudAlert::byType('QUANTITY MISMATCH')->count(),
                    'delayed_pickup' => FraudAlert::byType('DELAYED PICKUP')->count(),
                    'unscanned_waybill' => FraudAlert::byType('UNSCANNED WAYBILL')->count()
                ],
                'by_severity' => [
                    'low' => FraudAlert::bySeverity('low')->count(),
                    'medium' => FraudAlert::bySeverity('medium')->count(),
                    'high' => FraudAlert::bySeverity('high')->count(),
                    'critical' => FraudAlert::bySeverity('critical')->count()
                ]
            ];

            return response()->json($stats);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch fraud alert statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get available actions for fraud alert
     */
    private function getAvailableActions(FraudAlert $alert): array
    {
        $actions = [];

        if ($alert->status === 'active') {
            $actions[] = 'Mark Resolved';
            $actions[] = 'View Details';
            $actions[] = 'Escalate';
        }

        if ($alert->status === 'monitoring') {
            $actions[] = 'Mark Resolved';
            $actions[] = 'View Details';
        }

        if ($alert->status === 'resolved') {
            $actions[] = 'View Details';
        }

        return $actions;
    }

    /**
     * Run fraud detection checks
     */
    public function runChecks(): JsonResponse
    {
        try {
            $checks = [];

            // Check for delayed pickups
            $delayedConsignments = Consignment::pending()
                ->where('created_at', '<=', now()->subHours(4))
                ->get();

            foreach ($delayedConsignments as $consignment) {
                $checks[] = [
                    'type' => 'DELAYED PICKUP',
                    'description' => "Consignment {$consignment->consignment_id} at motor park for 4+ hours without pickup",
                    'consignment_id' => $consignment->consignment_id,
                    'severity' => 'medium'
                ];
            }

            // Check for quantity mismatches (simulated)
            $quantityMismatches = [
                [
                    'type' => 'QUANTITY MISMATCH',
                    'description' => 'Inventory dispatched 2-2-2, Logistics entered 1-1-1, DA received 1-1-1',
                    'consignment_id' => 'VV-2024-001',
                    'da_id' => 'DA_FCT-001',
                    'severity' => 'high'
                ]
            ];

            $checks = array_merge($checks, $quantityMismatches);

            return response()->json([
                'checks_run' => count($checks),
                'alerts_generated' => count($checks),
                'checks' => $checks
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to run fraud detection checks',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

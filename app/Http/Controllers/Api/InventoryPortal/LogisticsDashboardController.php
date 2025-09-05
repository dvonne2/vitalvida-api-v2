<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\FraudAlert;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LogisticsDashboardController extends Controller
{
    /**
     * Get logistics dashboard overview
     */
    public function overview(): JsonResponse
    {
        try {
            // Get today's active consignments
            $todayActiveConsignments = Consignment::today()->active()->count();
            
            // Get this week's in-transit consignments
            $thisWeekInTransit = Consignment::thisWeek()->where('status', 'in_transit')->count();
            
            // Get this week's active DAs
            $thisWeekActiveDAs = DeliveryAgent::whereHas('orders', function($query) {
                $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
            })->count();
            
            // Get this month's fraud alerts
            $thisMonthFraudAlerts = FraudAlert::whereBetween('created_at', [
                now()->startOfMonth(), 
                now()->endOfMonth()
            ])->count();
            
            // Determine fraud status
            $fraudStatus = $thisMonthFraudAlerts > 0 ? 'ALERT' : 'CLEAR';
            
            // Get total alerts count
            $alertsCount = FraudAlert::active()->count();
            
            $data = [
                'summary' => [
                    'today' => [
                        'active_consignments' => $todayActiveConsignments
                    ],
                    'this_week' => [
                        'in_transit' => $thisWeekInTransit,
                        'active_das' => $thisWeekActiveDAs
                    ],
                    'this_month' => [
                        'fraud_alerts' => $thisMonthFraudAlerts,
                        'fraud_status' => $fraudStatus
                    ]
                ],
                'alerts_count' => $alertsCount,
                'navigation' => [
                    'Dashboard',
                    'Consignments', 
                    'Bird Eye Panel',
                    'Live Activity',
                    'Fraud Alerts',
                    'Reports'
                ]
            ];
            
            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch dashboard overview',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get system audit information
     */
    public function systemAudit(): JsonResponse
    {
        try {
            $auditInfo = [
                'description' => 'All actions logged with IP addresses and timestamps',
                'last_zoho_sync' => '2 minutes ago',
                'total_events_today' => 1247,
                'ai_fraud_checks' => 856
            ];
            
            $recentLogs = [
                [
                    'timestamp' => now()->format('Y-m-d H:i:s'),
                    'action' => 'consignment_created',
                    'user' => 'logistics_manager',
                    'ip' => request()->ip()
                ]
            ];
            
            return response()->json([
                'audit_info' => $auditInfo,
                'recent_logs' => $recentLogs
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch system audit',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get daily operations report
     */
    public function dailyOperationsReport(): JsonResponse
    {
        try {
            $data = [
                'title' => 'Daily Operations Summary',
                'description' => 'Consignments, deliveries, and DA performance',
                'data' => [
                    'consignments_created' => 5,
                    'deliveries_completed' => 12,
                    'da_performance_avg' => 87.5
                ],
                'generate_report_url' => '/api/logistics/reports/daily-operations/generate'
            ];

            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch daily operations report',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get inventory audit report
     */
    public function inventoryAuditReport(): JsonResponse
    {
        try {
            $data = [
                'title' => 'Inventory Audit Trail',
                'description' => 'Full quantity tracking from dispatch to delivery',
                'audit_data' => [
                    'total_dispatched' => 245,
                    'total_delivered' => 238,
                    'discrepancies' => 3
                ],
                'generate_report_url' => '/api/logistics/reports/inventory-audit/generate'
            ];

            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch inventory audit report',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

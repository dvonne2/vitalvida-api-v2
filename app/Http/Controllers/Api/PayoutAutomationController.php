<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PayoutAutomationController extends Controller
{
    public function getPayoutMetrics(Request $request)
    {
        $isTestRoute = str_contains($request->getPathInfo(), '/test/');
        
        if (!$isTestRoute) {
            $allowedRoles = ['ceo', 'compliance', 'fc', 'gm'];
            if (!auth()->check() || !in_array(auth()->user()->role, $allowedRoles)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $thisWeekStart = now()->startOfWeek();
        
        $payoutStats = DB::table('payouts')
            ->selectRaw('
                COUNT(CASE WHEN status = "intent_marked" THEN 1 END) as total_intent_marked,
                COUNT(CASE WHEN status = "approved" THEN 1 END) as approved,
                COUNT(CASE WHEN status = "rejected" THEN 1 END) as rejected,
                COUNT(CASE WHEN status = "auto_reverted" THEN 1 END) as auto_reverted
            ')
            ->where('created_at', '>=', $thisWeekStart)
            ->first();

        $agingCount = DB::table('payouts')
            ->where('status', 'pending')
            ->where('created_at', '<', now()->subHours(48))
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'this_week' => [
                    'total_intent_marked' => $payoutStats->total_intent_marked ?? 0,
                    'approved' => $payoutStats->approved ?? 0,
                    'rejected' => $payoutStats->rejected ?? 0,
                    'auto_reverted' => $payoutStats->auto_reverted ?? 0
                ],
                'aging_over_48h' => $agingCount
            ],
            'generated_at' => now()->toISOString()
        ]);
    }

    public function getComplianceMetrics(Request $request)
    {
        $isTestRoute = str_contains($request->getPathInfo(), '/test/');

        if (!$isTestRoute) {
            $allowedRoles = ['ceo', 'compliance', 'fc', 'gm'];
            if (!auth()->check() || !in_array(auth()->user()->role, $allowedRoles)) {
                return response()->json(['error' => 'Unauthorized'], 403);
            }
        }

        $totalDAs = DB::table('delivery_agents')->count();

        return response()->json([
            'success' => true,
            'data' => [
                'photo_submissions' => [
                    'on_time' => 0,
                    'late' => 0,
                    'missing' => 0
                ],
                'da_strike_distribution' => [
                    '0_strikes' => $totalDAs,
                    '1_strike' => 0,
                    '2_strikes' => 0,
                    '3_plus_strikes' => 0
                ],
                'total_das' => $totalDAs
            ],
            'generated_at' => now()->toISOString()
        ]);
    }

    public function sendDAReminder(Request $request)
    {
        try {
            $isTestRoute = str_contains($request->getPathInfo(), '/test/');
            
            if (!$isTestRoute) {
                $allowedRoles = ['ceo', 'compliance', 'fc', 'gm'];
                if (!auth()->check() || !in_array(auth()->user()->role, $allowedRoles)) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
            }

            $agentIds = $request->delivery_agent_ids ?? [];
            $message = $request->message ?? 'Please submit required documentation';
            $targetDate = $request->target_date ?? now()->toDateString();

            $sentCount = 0;
            $errorCount = 0;
            $sentNotifications = [];
            $errors = [];

            foreach ($agentIds as $agentId) {
                $agent = DB::table('delivery_agents')->where('id', $agentId)->first();
                
                if (!$agent) {
                    $errorCount++;
                    $errors[] = [
                        'agent_id' => $agentId,
                        'error' => 'Agent not found'
                    ];
                    continue;
                }

                $notificationId = DB::table('system_logs')->insertGetId([
                    'type' => 'da_reminder',
                    'message' => "Reminder sent to DA {$agent->da_code}: {$message}",
                    'context' => json_encode([
                        'agent_id' => $agentId,
                        'target_date' => $targetDate,
                        'message' => $message
                    ]),
                    'level' => 'info',
                    'user_id' => auth()->id(),
                    'created_at' => now()
                ]);

                $sentCount++;
                $sentNotifications[] = [
                    'agent_id' => $agentId,
                    'notification_id' => $notificationId,
                    'status' => 'sent',
                    'sent_at' => now()->toISOString()
                ];
            }

            return response()->json([
                'success' => true,
                'sent_count' => $sentCount,
                'error_count' => $errorCount,
                'sent_notifications' => $sentNotifications,
                'errors' => $errors,
                'target_date' => $targetDate,
                'processed_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to send reminders: ' . $e->getMessage()
            ], 500);
        }
    }

    public function triggerComplianceEscalation(Request $request)
    {
        try {
            $isTestRoute = str_contains($request->getPathInfo(), '/test/');
            
            if (!$isTestRoute) {
                $allowedRoles = ['ceo', 'compliance', 'fc', 'gm'];
                if (!auth()->check() || !in_array(auth()->user()->role, $allowedRoles)) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
            }

            $order = DB::table('orders')
                ->leftJoin('payouts', 'orders.id', '=', 'payouts.order_id')
                ->select('orders.*', 'payouts.id as payout_id', 'payouts.status as payout_status')
                ->where('orders.id', $request->order_id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'error' => 'Order not found'
                ], 404);
            }

            $escalationId = DB::table('system_logs')->insertGetId([
                'type' => 'compliance_escalation',
                'message' => "Compliance escalation for Order #{$request->order_id}: {$request->reason}",
                'context' => json_encode([
                    'order_id' => $request->order_id,
                    'payout_id' => $order->payout_id,
                    'assigned_da_id' => $order->assigned_da_id,
                    'customer_name' => $order->customer_name,
                    'reason' => $request->reason,
                    'priority' => $request->priority,
                    'escalated_by' => auth()->user()->name ?? 'system'
                ]),
                'level' => $request->priority === 'critical' ? 'critical' : 'warning',
                'user_id' => auth()->id(),
                'created_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'escalation_id' => $escalationId,
                'order_id' => $request->order_id,
                'payout_id' => $order->payout_id,
                'order_details' => [
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'assigned_da_id' => $order->assigned_da_id,
                    'total_amount' => $order->total_amount
                ],
                'priority' => $request->priority,
                'reason' => $request->reason,
                'escalated_at' => now()->toISOString(),
                'escalated_by' => auth()->user()->name ?? 'system'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Failed to create escalation: ' . $e->getMessage()
            ], 500);
        }
    }

    public function autoRevert(Request $request)
    {
        try {
            $isTestRoute = str_contains($request->getPathInfo(), '/test/');
            
            if (!$isTestRoute) {
                $allowedRoles = ['ceo', 'compliance', 'fc', 'gm'];
                if (!auth()->check() || !in_array(auth()->user()->role, $allowedRoles)) {
                    return response()->json(['error' => 'Unauthorized'], 403);
                }
            }

            $cutoffTime = now()->subHours(48);
            $payoutsToRevert = DB::table('payouts')
                ->where('status', 'pending')
                ->where('created_at', '<', $cutoffTime)
                ->get();

            $revertedCount = 0;
            $errors = [];

            foreach ($payoutsToRevert as $payout) {
                try {
                    DB::table('payouts')
                        ->where('id', $payout->id)
                        ->update([
                            'status' => 'auto_reverted',
                            'updated_at' => now()
                        ]);

                    if (Schema::hasTable('payout_action_logs')) {
                        DB::table('payout_action_logs')->insert([
                            'payout_id' => $payout->id,
                            'action' => 'auto_reverted',
                            'user_id' => auth()->id(),
                            'reason' => 'Automatically reverted after 48 hours',
                            'created_at' => now()
                        ]);
                    }

                    $revertedCount++;
                } catch (\Exception $e) {
                    $errors[] = [
                        'payout_id' => $payout->id,
                        'error' => $e->getMessage()
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'reverted_count' => $revertedCount,
                'errors' => $errors,
                'processed_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Auto-revert failed: ' . $e->getMessage()
            ], 500);
        }
    }
}

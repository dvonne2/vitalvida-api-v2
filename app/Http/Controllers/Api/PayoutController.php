<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class PayoutController extends Controller
{
    public function getPendingConfirmations(Request $request)
    {
        $allowedRoles = ['fc', 'gm', 'accountant', 'ceo'];
        
        // Check if user is authenticated
        if (!auth()->check()) {
            return response()->json(['error' => 'Authentication required'], 401);
        }
        
        $userRole = auth()->user()->role;
        
        if (!in_array($userRole, $allowedRoles)) {
            DB::table('access_audit_logs')->insert([
                'user_id' => auth()->id(),
                'endpoint' => 'GET /api/payouts/pending-confirmations',
                'action' => 'unauthorized_access_attempt',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
            
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        DB::table('access_audit_logs')->insert([
            'user_id' => auth()->id(),
            'endpoint' => 'GET /api/payouts/pending-confirmations',
            'action' => 'access_granted',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'created_at' => now(),
        ]);

        $query = DB::table('payouts')
            ->join('delivery_agents', 'payouts.delivery_agent_id', '=', 'delivery_agents.id')
            ->join('orders', 'payouts.order_id', '=', 'orders.id')
            ->leftJoin('users as last_action_user', 'payouts.last_action_by', '=', 'last_action_user.id')
            ->select([
                'payouts.id as payout_id',
                'payouts.order_id',
                'delivery_agents.name as agent_name',
                'delivery_agents.zone',
                'payouts.status',
                'payouts.compliance_score',
                'payouts.otp_submitted',
                'payouts.photo_verified',
                'payouts.pos_matched',
                'payouts.created_at',
                'payouts.flagged',
                'last_action_user.name as last_action_by_name',
                'last_action_user.role as last_action_by_role'
            ])
            ->where('payouts.status', 'INTENT_MARKED')
            ->whereNull('payouts.locked_at')
            ->whereNull('payouts.approved_at')
            ->whereNull('payouts.rejected_at')
            ->where('delivery_agents.eligible_for_next_payout', true);

        if ($request->has('zone')) {
            $query->where('delivery_agents.zone', $request->zone);
        }

        if ($request->has('flagged')) {
            $flagged = filter_var($request->flagged, FILTER_VALIDATE_BOOLEAN);
            $query->where('payouts.flagged', $flagged);
        }

        if ($request->has('aging_min')) {
            $agingMinHours = (int) $request->aging_min;
            $cutoffTime = Carbon::now()->subHours($agingMinHours);
            $query->where('payouts.created_at', '<=', $cutoffTime);
        }

        $sortBy = $request->get('sort', 'created_at');
        $allowedSorts = ['created_at', 'compliance_score', 'zone'];
        
        if (in_array($sortBy, $allowedSorts)) {
            $query->orderBy($sortBy, 'desc');
        } else {
            $query->orderBy('payouts.created_at', 'desc');
        }

        $payouts = $query->get();

        $transformedPayouts = $payouts->map(function ($payout) {
            $createdAt = Carbon::parse($payout->created_at);
            $agingHours = $createdAt->diffInHours(Carbon::now());

            return [
                'payout_id' => $payout->payout_id,
                'order_id' => $payout->order_id,
                'delivery_agent' => [
                    'name' => $payout->agent_name,
                    'zone' => $payout->zone
                ],
                'status' => $payout->status,
                'compliance_score' => (float) $payout->compliance_score,
                'otp_submitted' => (bool) $payout->otp_submitted,
                'photo_verified' => (bool) $payout->photo_verified,
                'pos_matched' => (bool) $payout->pos_matched,
                'created_at' => $createdAt->toISOString(),
                'aging_hours' => $agingHours,
                'last_action_by' => [
                    'name' => $payout->last_action_by_name,
                    'role' => $payout->last_action_by_role
                ],
                'flagged' => (bool) $payout->flagged
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $transformedPayouts,
            'meta' => [
                'total_pending' => $transformedPayouts->count(),
                'filters_applied' => array_filter([
                    'zone' => $request->zone,
                    'flagged' => $request->flagged,
                    'aging_min' => $request->aging_min,
                    'sort' => $request->sort
                ]),
                'generated_at' => Carbon::now()->toISOString()
            ]
        ]);
    }
}

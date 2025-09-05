<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Models\Watchlist;
use App\Models\StrikeLog;
use App\Models\ExportLog;

class ComplianceController extends Controller
{

    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all non-compliant orders with categorized failure reasons
     */
    public function getNonCompliantOrders()
    {
        $orders = Order::with(['payment', 'otp', 'photo', 'deliveryAgent', 'payouts'])
            ->where('status', 'completed')
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->get();

        $nonCompliantOrders = [];

        foreach ($orders as $order) {
            $hasApprovedPayout = $order->payouts()->whereIn('status', ['paid', 'pending'])->exists();
            if ($hasApprovedPayout) {
                continue;
            }

            $failures = [];
            
            $hasPayment = $order->payment?->is_verified ?? false;
            if (!$hasPayment) {
                $failures[] = 'missing_payment';
            }

            $otpSubmitted = $order->otp?->is_submitted ?? false;
            if (!$otpSubmitted) {
                $failures[] = 'otp_not_submitted';
            }

            $photoApproved = $order->photo?->is_approved ?? false;
            if (!$photoApproved) {
                $failures[] = 'photo_not_approved';
            }

            if (!empty($failures)) {
                $nonCompliantOrders[] = [
                    'order_id' => $order->id,
                    'order_reference' => $order->reference ?? 'N/A',
                    'amount' => $order->amount,
                    'delivery_agent' => [
                        'id' => $order->deliveryAgent?->id,
                        'name' => $order->deliveryAgent?->name ?? 'Unknown',
                        'phone' => $order->deliveryAgent?->phone ?? 'N/A',
                    ],
                    'customer_info' => [
                        'name' => $order->customer_name ?? 'N/A',
                        'phone' => $order->customer_phone ?? 'N/A',
                    ],
                    'completion_date' => $order->completed_at ?? $order->updated_at,
                    'days_since_completion' => $order->completed_at ? 
                        now()->diffInDays($order->completed_at) : 
                        now()->diffInDays($order->updated_at),
                    'failure_categories' => $failures,
                    'failure_details' => [
                        'missing_payment' => !$hasPayment,
                        'otp_not_submitted' => !$otpSubmitted,
                        'photo_not_approved' => !$photoApproved,
                    ],
                    'priority' => $this->calculatePriority($failures, $order)
                ];
            }
        }

        usort($nonCompliantOrders, function ($a, $b) {
            $priorityOrder = ['high' => 3, 'medium' => 2, 'low' => 1];
            $priorityComparison = $priorityOrder[$b['priority']] - $priorityOrder[$a['priority']];
            
            if ($priorityComparison === 0) {
                return $b['days_since_completion'] - $a['days_since_completion'];
            }
            
            return $priorityComparison;
        });

        return response()->json([
            'success' => true,
            'data' => $nonCompliantOrders,
            'summary' => [
                'total_non_compliant' => count($nonCompliantOrders),
                'categories' => [
                    'missing_payment' => count(array_filter($nonCompliantOrders, fn($o) => in_array('missing_payment', $o['failure_categories']))),
                    'otp_not_submitted' => count(array_filter($nonCompliantOrders, fn($o) => in_array('otp_not_submitted', $o['failure_categories']))),
                    'photo_not_approved' => count(array_filter($nonCompliantOrders, fn($o) => in_array('photo_not_approved', $o['failure_categories']))),
                ],
                'priority_breakdown' => [
                    'high' => count(array_filter($nonCompliantOrders, fn($o) => $o['priority'] === 'high')),
                    'medium' => count(array_filter($nonCompliantOrders, fn($o) => $o['priority'] === 'medium')),
                    'low' => count(array_filter($nonCompliantOrders, fn($o) => $o['priority'] === 'low')),
                ]
            ]
        ]);
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all locked payouts with details
     */
    public function getLockedPayouts()
    {
        $lockedPayouts = Payout::with(['order.deliveryAgent', 'locker'])
            ->where('status', 'locked')
            ->orderBy('locked_at', 'desc')
            ->get();

        $payoutsData = $lockedPayouts->map(function ($payout) {
            return [
                'payout_id' => $payout->id,
                'order_id' => $payout->order_id,
                'order_reference' => $payout->order?->reference ?? 'N/A',
                'amount' => $payout->amount,
                'amount_formatted' => $payout->amount_in_naira,
                'delivery_agent' => [
                    'id' => $payout->order?->deliveryAgent?->id,
                    'name' => $payout->order?->deliveryAgent?->name ?? 'Unknown',
                    'phone' => $payout->order?->deliveryAgent?->phone ?? 'N/A',
                ],
                'lock_details' => [
                    'locked_by' => [
                        'id' => $payout->locked_by,
                        'name' => $payout->locker?->name ?? 'Unknown',
                        'role' => $payout->locker?->role ?? 'N/A',
                    ],
                    'locked_at' => $payout->locked_at,
                    'locked_at_formatted' => $payout->locked_at?->format('M d, Y H:i:s'),
                    'days_locked' => $payout->locked_at ? now()->diffInDays($payout->locked_at) : 0,
                    'lock_reason' => $payout->lock_reason,
                    'lock_type' => $payout->lock_type,
                ],
                'created_at' => $payout->created_at,
                'created_at_formatted' => $payout->created_at->format('M d, Y H:i:s'),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $payoutsData,
            'summary' => [
                'total_locked' => $lockedPayouts->count(),
                'total_amount_locked' => $lockedPayouts->sum('amount'),
                'lock_types' => [
                    'fraud' => $lockedPayouts->where('lock_type', 'fraud')->count(),
                    'dispute' => $lockedPayouts->where('lock_type', 'dispute')->count(),
                    'investigation' => $lockedPayouts->where('lock_type', 'investigation')->count(),
                    'compliance' => $lockedPayouts->where('lock_type', 'compliance')->count(),
                ],
                'average_days_locked' => $lockedPayouts->avg(function ($payout) {
                    return $payout->locked_at ? now()->diffInDays($payout->locked_at) : 0;
                })
            ]
        ]);
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Escalate a payout for GM review
     */
    public function escalatePayout($id)
    {
        $payout = Payout::find($id);

        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payout escalation logic to be implemented',
            'payout_id' => $id,
            'escalated_by' => auth()->id(),
            'escalated_at' => now()->toISOString()
        ]);
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Log a senior override action
     */
    public function logOverride($id)
    {
        $payout = Payout::find($id);

        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        return response()->json([
            'success' => true,
            'message' => 'Override logging logic to be implemented',
            'payout_id' => $id,
            'override_by' => auth()->id(),
            'override_at' => now()->toISOString()
        ]);
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Check order compliance for payout eligibility and log the check
     */
    public function checkOrderCompliance($orderId)
    {
        $order = Order::with(['payment', 'otp', 'photo', 'payouts'])->find($orderId);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $hasPayment = $order->payment?->is_verified ?? false;
        $otpSubmitted = $order->otp?->is_submitted ?? false;
        $photoApproved = $order->photo?->is_approved ?? false;

        $eligible = $hasPayment && $otpSubmitted && $photoApproved;

        $responseData = [
            'order_id' => (int) $orderId,
            'eligible' => $eligible,
            'details' => [
                'has_payment' => $hasPayment,
                'otp_submitted' => $otpSubmitted,
                'photo_approved' => $photoApproved,
            ]
        ];

        $existingPayout = $order->payouts()->first();
        
        PayoutActionLog::create([
            'payout_id' => $existingPayout?->id,
            'action' => 'manual_check',
            'performed_by' => auth()->id(),
            'role' => auth()->user()->role ?? 'unknown',
            'note' => json_encode($responseData)
        ]);

        return response()->json($responseData);
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Batch compliance check with filters, pagination, and CSV export
     */
    public function batchComplianceCheck(Request $request)
    {
        $hours = $request->query('hours', 24);
        if (!in_array($hours, [10, 24, 48])) {
            $hours = 24;
        }

        $query = Order::with(['payment', 'otp', 'photo', 'deliveryAgent'])
            ->where('delivered_at', '>=', now()->subHours($hours));

        if ($request->has('state') && !empty($request->state)) {
            $query->where('state', $request->state);
        }

        $perPage = min($request->query('per_page', 50), 100);
        $orders = $query->paginate($perPage);

        $complianceData = $orders->getCollection()->map(function ($order) {
            $hasPayment = $order->payment?->is_verified ?? false;
            $otpSubmitted = $order->otp?->is_submitted ?? false;
            $photoApproved = $order->photo?->is_approved ?? false;

            $eligible = $hasPayment && $otpSubmitted && $photoApproved;

            $nonComplianceReasons = [];
            if (!$hasPayment) $nonComplianceReasons[] = 'missing_payment';
            if (!$otpSubmitted) $nonComplianceReasons[] = 'otp_not_submitted';
            if (!$photoApproved) $nonComplianceReasons[] = 'photo_not_approved';

            return [
                'order_id' => $order->id,
                'da_name' => $order->deliveryAgent?->name ?? 'Unknown',
                'da_id' => $order->deliveryAgent?->id,
                'state' => $order->state ?? 'N/A',
                'delivered_at' => $order->delivered_at?->format('Y-m-d H:i:s'),
                'eligible' => $eligible,
                'has_payment' => $hasPayment,
                'otp_submitted' => $otpSubmitted,
                'photo_approved' => $photoApproved,
                'non_compliance_reasons' => $nonComplianceReasons,
            ];
        });

        return response()->json([
            'success' => true,
            'hours_checked' => $hours,
            'state_filter' => $request->state,
            'data' => $complianceData,
            'pagination' => [
                'current_page' => $orders->currentPage(),
                'last_page' => $orders->lastPage(),
                'per_page' => $orders->perPage(),
                'total' => $orders->total(),
            ],
            'summary' => [
                'total_orders' => $complianceData->count(),
                'eligible_count' => $complianceData->where('eligible', true)->count(),
                'non_compliant_count' => $complianceData->where('eligible', false)->count(),
                'categories' => [
                    'missing_payment' => $complianceData->filter(fn($o) => in_array('missing_payment', $o['non_compliance_reasons']))->count(),
                    'otp_not_submitted' => $complianceData->filter(fn($o) => in_array('otp_not_submitted', $o['non_compliance_reasons']))->count(),
                    'photo_not_approved' => $complianceData->filter(fn($o) => in_array('photo_not_approved', $o['non_compliance_reasons']))->count(),
                ]
            ]
        ]);
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lock all non-compliant payouts with preview mode
     */
    public function lockAllNonCompliant(Request $request)
    {
        $isPreview = $request->query('preview') === 'true';
        
        if ($isPreview && !in_array(auth()->user()->role ?? '', ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized to preview'], 403);
        }

        $hours = $request->query('hours', 24);
        if (!in_array($hours, [10, 24, 48])) {
            $hours = 24;
        }

        $query = Order::with(['payment', 'otp', 'photo', 'deliveryAgent', 'payouts'])
            ->where('delivered_at', '>=', now()->subHours($hours));

        if ($request->has('state') && !empty($request->state)) {
            $query->where('state', $request->state);
        }

        $orders = $query->get();
        $processedOrders = [];
        $lockedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($orders as $order) {
                $hasPayment = $order->payment?->is_verified ?? false;
                $otpSubmitted = $order->otp?->is_submitted ?? false;
                $photoApproved = $order->photo?->is_approved ?? false;
                $eligible = $hasPayment && $otpSubmitted && $photoApproved;

                if ($eligible) {
                    continue;
                }

                $nonComplianceReasons = [];
                if (!$hasPayment) $nonComplianceReasons[] = 'missing_payment';
                if (!$otpSubmitted) $nonComplianceReasons[] = 'otp_not_submitted';
                if (!$photoApproved) $nonComplianceReasons[] = 'photo_not_approved';

                $payout = $order->payouts()->whereNotIn('status', ['locked'])->first();
                
                if ($payout) {
                    if ($isPreview) {
                        $processedOrders[] = [
                            'order_id' => $order->id,
                            'payout_id' => $payout->id,
                            'eligible' => false,
                            'non_compliance_reasons' => $nonComplianceReasons,
                            'da_name' => $order->deliveryAgent?->name ?? 'Unknown',
                            'state' => $order->state ?? 'N/A',
                        ];
                    } else {
                        $payout->update([
                            'status' => 'locked',
                            'locked_by' => auth()->id(),
                            'locked_at' => now(),
                            'lock_reason' => 'Batch compliance enforcement',
                            'lock_type' => 'compliance'
                        ]);

                        PayoutActionLog::create([
                            'payout_id' => $payout->id,
                            'action' => 'locked',
                            'performed_by' => auth()->id(),
                            'role' => auth()->user()->role ?? 'unknown',
                            'note' => json_encode($nonComplianceReasons)
                        ]);

                        $processedOrders[] = $order->id;
                        $lockedCount++;
                    }
                }
            }

            DB::commit();

            if ($isPreview) {
                return response()->json([
                    'preview_mode' => true,
                    'would_lock' => $processedOrders
                ]);
            } else {
                return response()->json([
                    'locked_count' => $lockedCount,
                    'processed_orders' => $processedOrders
                ]);
            }

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Failed to process batch lock',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Unlock payouts in bulk (GM only)
     */
    public function unlockAll(Request $request)
    {
        if (auth()->user()->role !== 'gm') {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_ids' => 'required|array',
            'order_ids.*' => 'integer|exists:orders,id'
        ]);

        $orderIds = $request->order_ids;
        $unlockedOrders = [];
        $unlockedCount = 0;

        DB::beginTransaction();

        try {
            foreach ($orderIds as $orderId) {
                $order = Order::with('payouts')->find($orderId);
                
                if ($order) {
                    $payout = $order->payouts()->where('status', 'locked')->first();
                    
                    if ($payout) {
                        $payout->update([
                            'status' => 'pending',
                            'locked_by' => null,
                            'locked_at' => null,
                            'lock_reason' => null,
                            'lock_type' => null
                        ]);

                        PayoutActionLog::create([
                            'payout_id' => $payout->id,
                            'action' => 'unlocked_by_gm',
                            'performed_by' => auth()->id(),
                            'role' => 'gm',
                            'note' => 'GM override — unlocked for manual review'
                        ]);

                        $unlockedOrders[] = $orderId;
                        $unlockedCount++;
                    }
                }
            }

            DB::commit();

            return response()->json([
                'unlocked_count' => $unlockedCount,
                'unlocked_orders' => $unlockedOrders
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Failed to unlock payouts',
                'message' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Manually trigger OTP for an order (GM/FC/CEO only)
     */
    public function triggerOtp(Request $request)
    {
        // Ensure only authorized roles can access
        if (!in_array(auth()->user()->role ?? '', ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id'
        ]);

        $orderId = $request->order_id;
        $isDryRun = $request->query('dry_run') === 'true';

        $order = Order::with(['payment'])->find($orderId);

        if (!$order) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order not found'
            ], 404);
        }

        // Verify payment is confirmed
        $paymentVerified = $order->payment?->is_verified ?? false;
        
        if (!$paymentVerified) {
            return response()->json([
                'status' => 'error',
                'message' => 'Payment not confirmed, cannot trigger OTP'
            ], 400);
        }

        // Handle dry run mode
        if ($isDryRun) {
            $maskedPhone = $order->customer_phone ? 
                substr($order->customer_phone, 0, 6) . str_repeat('x', strlen($order->customer_phone) - 6) : 
                '+23481xxxxxxx';

            return response()->json([
                'status' => 'preview',
                'message' => 'OTP not sent (dry run)',
                'preview' => [
                    'phone' => $maskedPhone,
                    'message' => 'Your OTP is ' . rand(100000, 999999),
                    'sender_id' => 'Vitalvida'
                ]
            ]);
        }

        DB::beginTransaction();

        try {
            // Generate and send OTP
            $otpCode = rand(100000, 999999);
            
            // Update or create OTP record
            $order->otp()->updateOrCreate(
                ['order_id' => $order->id],
                [
                    'otp_code' => $otpCode,
                    'is_submitted' => false,
                    'sent_at' => now(),
                    'expires_at' => now()->addMinutes(10)
                ]
            );

            // Log the manual action
            PayoutActionLog::create([
                'payout_id' => $order->payouts()->first()?->id,
                'action' => 'otp_triggered',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'Manual OTP triggered via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP triggered for order {$orderId}"
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to trigger OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }


    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;

        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json([
                'status' => 'error',
                'message' => 'Order or OTP not found'
            ], 404);
        }

        $payout = $order->payouts()->first();

        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)
            ->where('action', 'otp_failed')
            ->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update([
                    'status' => 'locked',
                    'locked_by' => auth()->id(),
                    'locked_at' => now(),
                    'lock_reason' => 'Auto-locked due to 3 OTP failures',
                    'lock_type' => 'compliance'
                ]);

                PayoutActionLog::create([
                    'payout_id' => $payout->id,
                    'action' => 'locked',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => 'Payout auto-locked due to 3 OTP failures'
                ]);
            }

            return response()->json([
                'status' => 'locked',
                'message' => 'Payout locked after 3 failed OTP attempts'
            ], 423);
        }

        DB::beginTransaction();

        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create([
                    'payout_id' => $payout?->id,
                    'action' => 'otp_failed',
                    'performed_by' => auth()->id(),
                    'role' => auth()->user()->role ?? 'unknown',
                    'note' => "Incorrect OTP entered: {$submittedCode}"
                ]);

                $remainingAttempts = 3 - ($failedAttempts + 1);

                DB::commit();

                return response()->json([
                    'status' => 'error',
                    'message' => 'Invalid OTP code',
                    'remaining_attempts' => max(0, $remainingAttempts)
                ], 400);
            }

            $order->otp->update([
                'is_submitted' => true,
                'submitted_at' => now()
            ]);

            PayoutActionLog::create([
                'payout_id' => $payout?->id,
                'action' => 'otp_submitted',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => 'OTP verified and submitted via compliance panel'
            ]);

            DB::commit();

            return response()->json([
                'status' => 'success',
                'message' => "OTP submitted and verified for order {$orderId}",
                'remaining_attempts' => 3 - $failedAttempts
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'status' => 'error',
                'message' => 'Failed to submit OTP',
                'details' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate priority based on failure types and order details
     */
    private function calculatePriority($failures, $order)
    {
        $highPriorityFailures = ['missing_payment'];
        $mediumPriorityFailures = ['photo_not_approved'];
        $lowPriorityFailures = ['otp_not_submitted'];

        if (array_intersect($failures, $highPriorityFailures) || $order->amount > 50000) {
            return 'high';
        }

        if (array_intersect($failures, $mediumPriorityFailures) || $order->amount > 10000) {
            return 'medium';
        }

        return 'low';
    }

    /**
     * Submit and verify OTP with 3-strike lockout enforcement
     */
    public function submitOtp(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'otp_code' => 'required|string|min:6|max:6'
        ]);

        $orderId = $request->order_id;
        $submittedCode = $request->otp_code;
        $order = Order::with(['otp', 'payouts'])->find($orderId);

        if (!$order || !$order->otp) {
            return response()->json(['status' => 'error', 'message' => 'Order or OTP not found'], 404);
        }

        $payout = $order->payouts()->first();
        $failedAttempts = PayoutActionLog::where('payout_id', $payout?->id)->where('action', 'otp_failed')->count();

        if ($failedAttempts >= 3) {
            if ($payout && $payout->status !== 'locked') {
                $payout->update(['status' => 'locked', 'locked_by' => auth()->id(), 'locked_at' => now(), 'lock_reason' => 'Auto-locked due to 3 OTP failures', 'lock_type' => 'compliance']);
                PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'locked', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => 'Payout auto-locked due to 3 OTP failures']);
            }
            return response()->json(['status' => 'locked', 'message' => 'Payout locked after 3 failed OTP attempts'], 423);
        }

        DB::beginTransaction();
        try {
            if ($submittedCode !== $order->otp->otp_code) {
                PayoutActionLog::create(['payout_id' => $payout?->id, 'action' => 'otp_failed', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => "Incorrect OTP entered: {$submittedCode}"]);
                $remainingAttempts = 3 - ($failedAttempts + 1);
                DB::commit();
                return response()->json(['status' => 'error', 'message' => 'Invalid OTP code', 'remaining_attempts' => max(0, $remainingAttempts)], 400);
            }
            $order->otp->update(['is_submitted' => true, 'submitted_at' => now()]);
            PayoutActionLog::create(['payout_id' => $payout?->id, 'action' => 'otp_submitted', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => 'OTP verified and submitted via compliance panel']);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => "OTP submitted and verified for order {$orderId}", 'remaining_attempts' => 3 - $failedAttempts]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to submit OTP', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Manually approve a delivery photo and return eligibility state
     */
    public function approvePhoto(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'note' => 'nullable|string|max:500'
        ]);

        $orderId = $request->order_id;
        $customNote = $request->note;
        $order = Order::with(['photo', 'payment', 'otp', 'payouts'])->find($orderId);

        if (!$order || !$order->photo) {
            return response()->json(['status' => 'error', 'message' => 'Order or photo not found'], 404);
        }

        $wasAlreadyApproved = $order->photo->is_approved;
        DB::beginTransaction();

        try {
            if (!$wasAlreadyApproved) {
                $order->photo->update(['is_approved' => true, 'approved_at' => now()]);
                $noteText = 'Photo manually approved via compliance panel';
                if ($customNote) { $noteText .= ' - ' . $customNote; }
                PayoutActionLog::create(['payout_id' => $order->payouts()->first()?->id, 'action' => 'photo_approved', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => $noteText]);
            }

            $hasPayment = $order->payment?->is_verified ?? false;
            $otpSubmitted = $order->otp?->is_submitted ?? false;
            $photoApproved = $order->photo->is_approved;
            $eligible = $hasPayment && $otpSubmitted && $photoApproved;
            DB::commit();

            $message = $wasAlreadyApproved ? 'Photo was already approved' : "Photo approved for order {$orderId}";
            $status = $wasAlreadyApproved ? 'skipped' : 'success';

            return response()->json(['status' => $status, 'message' => $message, 'eligibility' => ['has_payment' => $hasPayment, 'otp_submitted' => $otpSubmitted, 'photo_approved' => $photoApproved, 'eligible' => $eligible]]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to approve photo', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark intent to approve payout and return eligibility state
     */
    public function markIntentToApprove(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'note' => 'nullable|string|max:500'
        ]);

        $orderId = $request->order_id;
        $customNote = $request->note;
        $order = Order::with(['payment', 'otp', 'photo', 'payouts'])->find($orderId);

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        $payout = $order->payouts()->first();
        if (!$payout) {
            return response()->json(['status' => 'error', 'message' => 'Order or payout not found'], 404);
        }

        DB::beginTransaction();
        try {
            $noteText = 'Intent to approve payout recorded by ' . (auth()->user()->name ?? 'user');
            if ($customNote) { $noteText .= ' - ' . $customNote; }

            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'intent_to_approve', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => $noteText]);

            $hasPayment = $order->payment?->is_verified ?? false;
            $otpSubmitted = $order->otp?->is_submitted ?? false;
            $photoApproved = $order->photo?->is_approved ?? false;
            $eligible = $hasPayment && $otpSubmitted && $photoApproved;
            DB::commit();

            return response()->json(['status' => 'success', 'message' => "Intent to approve payout recorded for order {$orderId}", 'eligibility' => ['has_payment' => $hasPayment, 'otp_submitted' => $otpSubmitted, 'photo_approved' => $photoApproved, 'eligible' => $eligible]]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to record intent', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirm payout receipt with final lockdown (Vitalvida optimized)
     */
    public function confirmPayoutReceipt(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'nullable|integer|exists:orders,id',
            'payout_id' => 'nullable|integer|exists:payouts,id',
            'note' => 'nullable|string|max:500'
        ]);

        if (!$request->order_id && !$request->payout_id) {
            return response()->json(['status' => 'error', 'message' => 'Either order_id or payout_id is required'], 400);
        }

        if ($request->payout_id) {
            $payout = Payout::with(['order.payment', 'order.otp', 'order.photo'])->find($request->payout_id);
            $orderId = $payout?->order_id;
        } else {
            $order = Order::with(['payouts', 'payment', 'otp', 'photo'])->find($request->order_id);
            $payout = $order?->payouts()->first();
            $orderId = $request->order_id;
        }

        if (!$payout || !$payout->order) {
            return response()->json(['status' => 'error', 'message' => 'Payout or order not found'], 404);
        }

        $order = $payout->order;
        if ($payout->is_confirmed || $payout->confirmed_at) {
            return response()->json(['status' => 'skipped', 'message' => 'Payout was already confirmed', 'confirmed_at' => $payout->confirmed_at?->toISOString(), 'confirmed_by' => $payout->confirmed_by_name ?? 'Unknown']);
        }

        $hasPayment = $order->payment?->is_verified ?? false;
        $otpSubmitted = $order->otp?->is_submitted ?? false;
        $photoApproved = $order->photo?->is_approved ?? false;
        $isPaid = $payout->status === 'paid';
        $missingRequirements = [];
        if (!$hasPayment) $missingRequirements[] = 'payment_not_verified';
        if (!$otpSubmitted) $missingRequirements[] = 'otp_not_submitted';
        if (!$photoApproved) $missingRequirements[] = 'photo_not_approved';
        if (!$isPaid) $missingRequirements[] = 'payout_not_paid';

        if (!empty($missingRequirements)) {
            return response()->json(['status' => 'blocked', 'message' => 'Cannot confirm - requirements not met', 'missing_requirements' => $missingRequirements], 400);
        }

        DB::beginTransaction();
        try {
            $payout->update(['is_confirmed' => true, 'confirmed_at' => now(), 'confirmed_by' => auth()->id(), 'status' => 'confirmed', 'is_locked_final' => true]);
            $noteText = 'Payout receipt confirmed and locked - disbursement complete';
            if ($request->note) { $noteText .= ' - ' . $request->note; }
            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'receipt_confirmed', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => $noteText]);
            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'locked_final', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => 'Payout locked permanently after confirmation']);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Payout for Order #{$orderId} has been confirmed and locked", 'confirmed_by' => auth()->user()->role ?? 'unknown', 'locked' => true, 'timestamp' => now()->toISOString(), 'payout_id' => $payout->id]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to confirm receipt', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject payout receipt and create strike log
     */
    public function rejectPayoutReceipt(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['fc', 'gm', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payout_id' => 'nullable|integer|exists:payouts,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'note' => 'required|string|max:500'
        ]);

        if (!$request->payout_id && !$request->order_id) {
            return response()->json(['error' => 'Either payout_id or order_id required'], 400);
        }

        $payout = $request->payout_id ? Payout::find($request->payout_id) : Payout::where('order_id', $request->order_id)->first();
        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        DB::beginTransaction();
        try {
            $payout->update(['status' => 'rejected', 'locked_at' => now()]);

            PayoutActionLog::create([
                'payout_id' => $payout->id,
                'action' => 'rejected',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => $request->note
            ]);

            if ($payout->order && $payout->order->delivery_agent_id) {
                StrikeLog::create([
                    'delivery_agent_id' => $payout->order->delivery_agent_id,
                    'reason' => 'Payout rejection due to compliance failure',
                    'notes' => $request->note,
                    'source' => 'payout_compliance',
                    'issued_by' => auth()->id(),
                    'payout_id' => $payout->id,
                    'severity' => 'medium'
                ]);

                $this->checkAndWatchlistDA($payout->order->delivery_agent_id);
            }

            DB::commit();
            return response()->json(['status' => 'error', 'message' => 'Payout has been rejected and locked']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to reject payout', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Check and auto-watchlist DA if 3+ strikes in 30 days
     */
    private function checkAndWatchlistDA($deliveryAgentId)
    {
        $strikeCount = StrikeLog::forAgent($deliveryAgentId)->recent(30)->count();
        if ($strikeCount >= 3 && !Watchlist::isWatchlisted($deliveryAgentId)) {
            Watchlist::create([
                'delivery_agent_id' => $deliveryAgentId,
                'reason' => "Auto-watchlisted: {$strikeCount} strikes in 30 days",
                'created_by' => null,
                'escalated_at' => now()
            ]);
            PayoutActionLog::create([
                'payout_id' => null,
                'action' => 'auto_watchlisted',
                'performed_by' => auth()->id(),
                'role' => 'system',
                'note' => "DA auto-watchlisted after {$strikeCount} strikes"
            ]);
            return true;
        }
        return false;
    }

    /**
     * Reject payout receipt and create strike log
     */
    public function rejectPayoutReceipt(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['fc', 'gm', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payout_id' => 'nullable|integer|exists:payouts,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'note' => 'required|string|max:500'
        ]);

        if (!$request->payout_id && !$request->order_id) {
            return response()->json(['error' => 'Either payout_id or order_id required'], 400);
        }

        $payout = $request->payout_id ? Payout::find($request->payout_id) : Payout::where('order_id', $request->order_id)->first();
        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        DB::beginTransaction();
        try {
            $payout->update(['status' => 'rejected', 'locked_at' => now()]);

            PayoutActionLog::create([
                'payout_id' => $payout->id,
                'action' => 'rejected',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => $request->note
            ]);

            if ($payout->order && $payout->order->delivery_agent_id) {
                StrikeLog::create([
                    'delivery_agent_id' => $payout->order->delivery_agent_id,
                    'reason' => 'Payout rejection due to compliance failure',
                    'notes' => $request->note,
                    'source' => 'payout_compliance',
                    'issued_by' => auth()->id(),
                    'payout_id' => $payout->id,
                    'severity' => 'medium'
                ]);

                $this->checkAndWatchlistDA($payout->order->delivery_agent_id);
            }

            DB::commit();
            return response()->json(['status' => 'error', 'message' => 'Payout has been rejected and locked']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to reject payout', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Check and auto-watchlist DA if 3+ strikes in 30 days
     */
    private function checkAndWatchlistDA($deliveryAgentId)
    {
        $strikeCount = StrikeLog::forAgent($deliveryAgentId)->recent(30)->count();
        if ($strikeCount >= 3 && !Watchlist::isWatchlisted($deliveryAgentId)) {
            Watchlist::create([
                'delivery_agent_id' => $deliveryAgentId,
                'reason' => "Auto-watchlisted: {$strikeCount} strikes in 30 days",
                'created_by' => null,
                'escalated_at' => now()
            ]);
            PayoutActionLog::create([
                'payout_id' => null,
                'action' => 'auto_watchlisted',
                'performed_by' => auth()->id(),
                'role' => 'system',
                'note' => "DA auto-watchlisted after {$strikeCount} strikes"
            ]);
            return true;
        }
        return false;
    }

    /**
     * Get complete action history for an order/payout
     */
    public function getPayoutActions($orderId)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['fc', 'gm', 'ceo', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order = Order::with(['payouts', 'deliveryAgent', 'payment', 'otp', 'photo'])->find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $payout = $order->payouts()->first();
        $payoutActions = [];
        $strikeHistory = [];
        $watchlistHistory = [];

        if ($payout) {
            $payoutActions = PayoutActionLog::where('payout_id', $payout->id)
                ->with(['performer'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'performed_by' => $log->performer?->name ?? 'System',
                        'role' => $log->role,
                        'note' => $log->note,
                        'timestamp' => $log->created_at->toISOString(),
                        'formatted_date' => $log->created_at->format('M d, Y H:i:s')
                    ];
                });
        }

        if ($order->delivery_agent_id) {
            $strikeHistory = StrikeLog::forAgent($order->delivery_agent_id)
                ->with(['issuer', 'payout'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($strike) {
                    return [
                        'id' => $strike->id,
                        'reason' => $strike->reason,
                        'notes' => $strike->notes,
                        'severity' => $strike->severity,
                        'source' => $strike->source,
                        'issued_by' => $strike->issuer?->name ?? 'System',
                        'payout_id' => $strike->payout_id,
                        'timestamp' => $strike->created_at->toISOString(),
                        'formatted_date' => $strike->created_at->format('M d, Y H:i:s')
                    ];
                });

            $watchlistHistory = Watchlist::where('delivery_agent_id', $order->delivery_agent_id)
                ->with(['creator', 'resolver'])
                ->orderBy('escalated_at', 'desc')
                ->get()
                ->map(function($entry) {
                    return [
                        'id' => $entry->id,
                        'reason' => $entry->reason,
                        'is_active' => $entry->is_active,
                        'created_by' => $entry->creator?->name ?? 'System',
                        'resolved_by' => $entry->resolver?->name,
                        'escalated_at' => $entry->escalated_at->toISOString(),
                        'resolved_at' => $entry->resolved_at?->toISOString(),
                        'days_since_watchlisted' => $entry->days_since_watchlisted,
                        'formatted_escalation_date' => $entry->formatted_escalation_date
                    ];
                });
        }

        $currentStatus = [
            'order_id' => $order->id,
            'delivery_agent_id' => $order->delivery_agent_id,
            'delivery_agent_name' => $order->deliveryAgent?->name ?? 'Unknown',
            'payout_status' => $payout?->status ?? 'No payout',
            'is_watchlisted' => $order->delivery_agent_id ? Watchlist::isWatchlisted($order->delivery_agent_id) : false,
            'total_strikes' => $order->delivery_agent_id ? StrikeLog::forAgent($order->delivery_agent_id)->count() : 0,
            'recent_strikes' => $order->delivery_agent_id ? StrikeLog::forAgent($order->delivery_agent_id)->recent(30)->count() : 0,
            'compliance_status' => [
                'payment_verified' => $order->payment?->is_verified ?? false,
                'otp_submitted' => $order->otp?->is_submitted ?? false,
                'photo_approved' => $order->photo?->is_approved ?? false
            ]
        ];

        return response()->json([
            'status' => 'success',
            'current_status' => $currentStatus,
            'payout_actions' => $payoutActions,
            'strike_history' => $strikeHistory,
            'watchlist_history' => $watchlistHistory,
            'action_count' => $payoutActions->count(),
            'total_records' => $payoutActions->count() + $strikeHistory->count() + $watchlistHistory->count()
        ]);
    }
}

    public function markIntentToApprove(Request $request)
    {
        try {
            $request->validate([
                'order_id' => 'required|integer|exists:orders,id',
                'note' => 'nullable|string|max:500'
            ]);

            $userRole = auth()->user()->role ?? 'unknown';
            if (!in_array($userRole, ['fc', 'gm', 'ceo'])) {
                return response()->json(['status' => 'error', 'message' => 'Unauthorized. Only FC, GM, or CEO can mark intent to approve.'], 403);
            }

            DB::beginTransaction();
            $orderId = $request->input('order_id');
            $note = $request->input('note');
            $order = Order::with(['payment', 'otp', 'photo', 'payouts'])->find($orderId);

            if (!$order) {
                DB::rollback();
                return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
            }

            $payout = $order->payouts()->first();
            if (!$payout) {
                DB::rollback();
                return response()->json(['status' => 'error', 'message' => 'Order or payout not found'], 404);
            }

            $noteText = $note ? "Intent to approve marked by {$userRole}. Note: {$note}" : "Intent to approve marked by {$userRole}";
            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'intent_to_approve', 'performed_by' => auth()->id(), 'role' => $userRole, 'note' => $noteText]);

            $hasPayment = $order->payment?->is_verified ?? false;
            $otpSubmitted = $order->otp?->is_submitted ?? false;
            $photoApproved = $order->photo?->is_approved ?? false;
            $eligible = $hasPayment && $otpSubmitted && $photoApproved;

            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Intent to approve payout recorded for order {$orderId}", 'eligibility' => ['has_payment' => $hasPayment, 'otp_submitted' => $otpSubmitted, 'photo_approved' => $photoApproved, 'eligible' => $eligible]]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to mark intent to approve', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Mark intent to approve payout and return eligibility state
     */
    public function markIntentToApprove(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'required|integer|exists:orders,id',
            'note' => 'nullable|string|max:500'
        ]);

        $orderId = $request->order_id;
        $customNote = $request->note;
        $order = Order::with(['payment', 'otp', 'photo', 'payouts'])->find($orderId);

        if (!$order) {
            return response()->json(['status' => 'error', 'message' => 'Order not found'], 404);
        }

        $payout = $order->payouts()->first();
        if (!$payout) {
            return response()->json(['status' => 'error', 'message' => 'Order or payout not found'], 404);
        }

        DB::beginTransaction();
        try {
            $noteText = 'Intent to approve payout recorded by ' . (auth()->user()->name ?? 'user');
            if ($customNote) { $noteText .= ' - ' . $customNote; }

            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'intent_to_approve', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => $noteText]);

            $hasPayment = $order->payment?->is_verified ?? false;
            $otpSubmitted = $order->otp?->is_submitted ?? false;
            $photoApproved = $order->photo?->is_approved ?? false;
            $eligible = $hasPayment && $otpSubmitted && $photoApproved;
            DB::commit();

            return response()->json(['status' => 'success', 'message' => "Intent to approve payout recorded for order {$orderId}", 'eligibility' => ['has_payment' => $hasPayment, 'otp_submitted' => $otpSubmitted, 'photo_approved' => $photoApproved, 'eligible' => $eligible]]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to record intent', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Confirm payout receipt with final lockdown (Vitalvida optimized)
     */
    public function confirmPayoutReceipt(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['gm', 'fc', 'ceo', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'order_id' => 'nullable|integer|exists:orders,id',
            'payout_id' => 'nullable|integer|exists:payouts,id',
            'note' => 'nullable|string|max:500'
        ]);

        if (!$request->order_id && !$request->payout_id) {
            return response()->json(['status' => 'error', 'message' => 'Either order_id or payout_id is required'], 400);
        }

        if ($request->payout_id) {
            $payout = Payout::with(['order.payment', 'order.otp', 'order.photo'])->find($request->payout_id);
            $orderId = $payout?->order_id;
        } else {
            $order = Order::with(['payouts', 'payment', 'otp', 'photo'])->find($request->order_id);
            $payout = $order?->payouts()->first();
            $orderId = $request->order_id;
        }

        if (!$payout || !$payout->order) {
            return response()->json(['status' => 'error', 'message' => 'Payout or order not found'], 404);
        }

        $order = $payout->order;
        if ($payout->is_confirmed || $payout->confirmed_at) {
            return response()->json(['status' => 'skipped', 'message' => 'Payout was already confirmed', 'confirmed_at' => $payout->confirmed_at?->toISOString(), 'confirmed_by' => $payout->confirmed_by_name ?? 'Unknown']);
        }

        $hasPayment = $order->payment?->is_verified ?? false;
        $otpSubmitted = $order->otp?->is_submitted ?? false;
        $photoApproved = $order->photo?->is_approved ?? false;
        $isPaid = $payout->status === 'paid';
        $missingRequirements = [];
        if (!$hasPayment) $missingRequirements[] = 'payment_not_verified';
        if (!$otpSubmitted) $missingRequirements[] = 'otp_not_submitted';
        if (!$photoApproved) $missingRequirements[] = 'photo_not_approved';
        if (!$isPaid) $missingRequirements[] = 'payout_not_paid';

        if (!empty($missingRequirements)) {
            return response()->json(['status' => 'blocked', 'message' => 'Cannot confirm - requirements not met', 'missing_requirements' => $missingRequirements], 400);
        }

        DB::beginTransaction();
        try {
            $payout->update(['is_confirmed' => true, 'confirmed_at' => now(), 'confirmed_by' => auth()->id(), 'status' => 'confirmed', 'is_locked_final' => true]);
            $noteText = 'Payout receipt confirmed and locked - disbursement complete';
            if ($request->note) { $noteText .= ' - ' . $request->note; }
            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'receipt_confirmed', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => $noteText]);
            PayoutActionLog::create(['payout_id' => $payout->id, 'action' => 'locked_final', 'performed_by' => auth()->id(), 'role' => auth()->user()->role ?? 'unknown', 'note' => 'Payout locked permanently after confirmation']);
            DB::commit();
            return response()->json(['status' => 'success', 'message' => "Payout for Order #{$orderId} has been confirmed and locked", 'confirmed_by' => auth()->user()->role ?? 'unknown', 'locked' => true, 'timestamp' => now()->toISOString(), 'payout_id' => $payout->id]);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to confirm receipt', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Reject payout receipt and create strike log
     */
    public function rejectPayoutReceipt(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['fc', 'gm', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payout_id' => 'nullable|integer|exists:payouts,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'note' => 'required|string|max:500'
        ]);

        if (!$request->payout_id && !$request->order_id) {
            return response()->json(['error' => 'Either payout_id or order_id required'], 400);
        }

        $payout = $request->payout_id ? Payout::find($request->payout_id) : Payout::where('order_id', $request->order_id)->first();
        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        DB::beginTransaction();
        try {
            $payout->update(['status' => 'rejected', 'locked_at' => now()]);

            PayoutActionLog::create([
                'payout_id' => $payout->id,
                'action' => 'rejected',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => $request->note
            ]);

            if ($payout->order && $payout->order->delivery_agent_id) {
                StrikeLog::create([
                    'delivery_agent_id' => $payout->order->delivery_agent_id,
                    'reason' => 'Payout rejection due to compliance failure',
                    'notes' => $request->note,
                    'source' => 'payout_compliance',
                    'issued_by' => auth()->id(),
                    'payout_id' => $payout->id,
                    'severity' => 'medium'
                ]);

                $this->checkAndWatchlistDA($payout->order->delivery_agent_id);
            }

            DB::commit();
            return response()->json(['status' => 'error', 'message' => 'Payout has been rejected and locked']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to reject payout', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Check and auto-watchlist DA if 3+ strikes in 30 days
     */
    private function checkAndWatchlistDA($deliveryAgentId)
    {
        $strikeCount = StrikeLog::forAgent($deliveryAgentId)->recent(30)->count();
        if ($strikeCount >= 3 && !Watchlist::isWatchlisted($deliveryAgentId)) {
            Watchlist::create([
                'delivery_agent_id' => $deliveryAgentId,
                'reason' => "Auto-watchlisted: {$strikeCount} strikes in 30 days",
                'created_by' => null,
                'escalated_at' => now()
            ]);
            PayoutActionLog::create([
                'payout_id' => null,
                'action' => 'auto_watchlisted',
                'performed_by' => auth()->id(),
                'role' => 'system',
                'note' => "DA auto-watchlisted after {$strikeCount} strikes"
            ]);
            return true;
        }
        return false;
    }

    /**
     * Reject payout receipt and create strike log
     */
    public function rejectPayoutReceipt(Request $request)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['fc', 'gm', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $request->validate([
            'payout_id' => 'nullable|integer|exists:payouts,id',
            'order_id' => 'nullable|integer|exists:orders,id',
            'note' => 'required|string|max:500'
        ]);

        if (!$request->payout_id && !$request->order_id) {
            return response()->json(['error' => 'Either payout_id or order_id required'], 400);
        }

        $payout = $request->payout_id ? Payout::find($request->payout_id) : Payout::where('order_id', $request->order_id)->first();
        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        DB::beginTransaction();
        try {
            $payout->update(['status' => 'rejected', 'locked_at' => now()]);

            PayoutActionLog::create([
                'payout_id' => $payout->id,
                'action' => 'rejected',
                'performed_by' => auth()->id(),
                'role' => auth()->user()->role ?? 'unknown',
                'note' => $request->note
            ]);

            if ($payout->order && $payout->order->delivery_agent_id) {
                StrikeLog::create([
                    'delivery_agent_id' => $payout->order->delivery_agent_id,
                    'reason' => 'Payout rejection due to compliance failure',
                    'notes' => $request->note,
                    'source' => 'payout_compliance',
                    'issued_by' => auth()->id(),
                    'payout_id' => $payout->id,
                    'severity' => 'medium'
                ]);

                $this->checkAndWatchlistDA($payout->order->delivery_agent_id);
            }

            DB::commit();
            return response()->json(['status' => 'error', 'message' => 'Payout has been rejected and locked']);
        } catch (\Exception $e) {
            DB::rollback();
            return response()->json(['status' => 'error', 'message' => 'Failed to reject payout', 'details' => $e->getMessage()], 500);
        }
    }

    /**
     * Check and auto-watchlist DA if 3+ strikes in 30 days
     */
    private function checkAndWatchlistDA($deliveryAgentId)
    {
        $strikeCount = StrikeLog::forAgent($deliveryAgentId)->recent(30)->count();
        if ($strikeCount >= 3 && !Watchlist::isWatchlisted($deliveryAgentId)) {
            Watchlist::create([
                'delivery_agent_id' => $deliveryAgentId,
                'reason' => "Auto-watchlisted: {$strikeCount} strikes in 30 days",
                'created_by' => null,
                'escalated_at' => now()
            ]);
            PayoutActionLog::create([
                'payout_id' => null,
                'action' => 'auto_watchlisted',
                'performed_by' => auth()->id(),
                'role' => 'system',
                'note' => "DA auto-watchlisted after {$strikeCount} strikes"
            ]);
            return true;
        }
        return false;
    }

    /**
     * Get complete action history for an order/payout
     */
    public function getPayoutActions($orderId)
    {
        if (!in_array(auth()->user()->role ?? ''[], ['fc', 'gm', 'ceo', 'accountant'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $order = Order::with(['payouts', 'deliveryAgent', 'payment', 'otp', 'photo'])->find($orderId);
        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $payout = $order->payouts()->first();
        $payoutActions = [];
        $strikeHistory = [];
        $watchlistHistory = [];

        if ($payout) {
            $payoutActions = PayoutActionLog::where('payout_id', $payout->id)
                ->with(['performer'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($log) {
                    return [
                        'id' => $log->id,
                        'action' => $log->action,
                        'performed_by' => $log->performer?->name ?? 'System',
                        'role' => $log->role,
                        'note' => $log->note,
                        'timestamp' => $log->created_at->toISOString(),
                        'formatted_date' => $log->created_at->format('M d, Y H:i:s')
                    ];
                });
        }

        if ($order->delivery_agent_id) {
            $strikeHistory = StrikeLog::forAgent($order->delivery_agent_id)
                ->with(['issuer', 'payout'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function($strike) {
                    return [
                        'id' => $strike->id,
                        'reason' => $strike->reason,
                        'notes' => $strike->notes,
                        'severity' => $strike->severity,
                        'source' => $strike->source,
                        'issued_by' => $strike->issuer?->name ?? 'System',
                        'payout_id' => $strike->payout_id,
                        'timestamp' => $strike->created_at->toISOString(),
                        'formatted_date' => $strike->created_at->format('M d, Y H:i:s')
                    ];
                });

            $watchlistHistory = Watchlist::where('delivery_agent_id', $order->delivery_agent_id)
                ->with(['creator', 'resolver'])
                ->orderBy('escalated_at', 'desc')
                ->get()
                ->map(function($entry) {
                    return [
                        'id' => $entry->id,
                        'reason' => $entry->reason,
                        'is_active' => $entry->is_active,
                        'created_by' => $entry->creator?->name ?? 'System',
                        'resolved_by' => $entry->resolver?->name,
                        'escalated_at' => $entry->escalated_at->toISOString(),
                        'resolved_at' => $entry->resolved_at?->toISOString(),
                        'days_since_watchlisted' => $entry->days_since_watchlisted,
                        'formatted_escalation_date' => $entry->formatted_escalation_date
                    ];
                });
        }

        $currentStatus = [
            'order_id' => $order->id,
            'delivery_agent_id' => $order->delivery_agent_id,
            'delivery_agent_name' => $order->deliveryAgent?->name ?? 'Unknown',
            'payout_status' => $payout?->status ?? 'No payout',
            'is_watchlisted' => $order->delivery_agent_id ? Watchlist::isWatchlisted($order->delivery_agent_id) : false,
            'total_strikes' => $order->delivery_agent_id ? StrikeLog::forAgent($order->delivery_agent_id)->count() : 0,
            'recent_strikes' => $order->delivery_agent_id ? StrikeLog::forAgent($order->delivery_agent_id)->recent(30)->count() : 0,
            'compliance_status' => [
                'payment_verified' => $order->payment?->is_verified ?? false,
                'otp_submitted' => $order->otp?->is_submitted ?? false,
                'photo_approved' => $order->photo?->is_approved ?? false
            ]
        ];

        return response()->json([
            'status' => 'success',
            'current_status' => $currentStatus,
            'payout_actions' => $payoutActions,
            'strike_history' => $strikeHistory,
            'watchlist_history' => $watchlistHistory,
            'action_count' => $payoutActions->count(),
            'total_records' => $payoutActions->count() + $strikeHistory->count() + $watchlistHistory->count()
        ]);
    }
}

    /**
     * Get filtered payout action logs with enhanced security
     */
    public function getFilteredPayoutActions(Request $request)
    {
        if (!in_array(auth()->user()->role ?? '', ['fc', 'gm', 'ceo', 'compliance'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = PayoutActionLog::with(['performer', 'payout.order']);

        // Apply filters
        if ($request->action) { $query->where('action', $request->action); }
        if ($request->performed_by) { $query->where('performed_by', $request->performed_by); }
        if ($request->role) { $query->where('role', $request->role); }
        if ($request->payout_id) { $query->where('payout_id', $request->payout_id); }
        if ($request->order_id) { 
            $query->whereHas('payout', fn($q) => $q->where('order_id', $request->order_id)); 
        }
        if ($request->date_from) { $query->where('created_at', '>=', $request->date_from); }
        if ($request->date_to) { $query->where('created_at', '<=', $request->date_to); }

        $logs = $query->orderBy('created_at', 'desc')->paginate($request->per_page ?? 50);

        return response()->json([
            'status' => 'success',
            'data' => $logs->items(),
            'pagination' => [
                'current_page' => $logs->currentPage(),
                'total' => $logs->total(),
                'per_page' => $logs->perPage()
            ],
            'filters_applied' => $request->only(['action', 'performed_by', 'role', 'payout_id', 'order_id', 'date_from', 'date_to'])
        ]);
    }

    /**
     * Export payout action logs with forensic integrity
     */
    public function exportPayoutActions(Request $request)
    {
        if (!in_array(auth()->user()->role ?? '', ['fc', 'gm', 'ceo', 'compliance'])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $format = $request->format ?? 'pdf';
        $exportId = ExportLog::generateExportId();
        $filters = $request->only(['action', 'performed_by', 'role', 'payout_id', 'order_id', 'date_from', 'date_to']);

        // Check for export anomalies
        $anomaly = ExportLog::detectAnomalies(auth()->id());
        if ($anomaly['is_anomaly']) {
            return response()->json([
                'error' => "Export limit exceeded: {$anomaly['recent_count']} exports in last hour"
            ], 429);
        }

        // Build query with same filters
        $query = PayoutActionLog::with(['performer', 'payout.order']);
        if ($request->action) { $query->where('action', $request->action); }
        if ($request->performed_by) { $query->where('performed_by', $request->performed_by); }
        if ($request->role) { $query->where('role', $request->role); }
        if ($request->payout_id) { $query->where('payout_id', $request->payout_id); }
        if ($request->order_id) { 
            $query->whereHas('payout', fn($q) => $q->where('order_id', $request->order_id)); 
        }
        if ($request->date_from) { $query->where('created_at', '>=', $request->date_from); }
        if ($request->date_to) { $query->where('created_at', '<=', $request->date_to); }

        $logs = $query->orderBy('created_at', 'desc')->get();
        
        // Generate integrity hash
        $dataPayload = $logs->toArray() . json_encode($filters) . auth()->id() . now()->toDateTimeString();
        $integrityHash = hash('sha256', $dataPayload);

        // Log the export
        ExportLog::create([
            'user_id' => auth()->id(),
            'export_type' => 'payout_actions',
            'format' => $format,
            'filters_applied' => $filters,
            'integrity_hash' => $integrityHash,
            'export_id' => $exportId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'records_exported' => $logs->count(),
            'downloaded_at' => now()
        ]);

        $exportData = [
            'export_id' => $exportId,
            'generated_by' => auth()->user()->name,
            'user_role' => auth()->user()->role,
            'generated_at' => now()->format('Y-m-d H:i:s'),
            'filters_applied' => $filters,
            'integrity_hash' => substr($integrityHash, 0, 16) . '...',
            'records_count' => $logs->count(),
            'logs' => $logs->map(function($log) {
                return [
                    'timestamp' => $log->created_at->format('Y-m-d H:i:s'),
                    'action' => $log->action,
                    'payout_id' => $log->payout_id,
                    'order_id' => $log->payout?->order_id,
                    'performed_by' => $log->performer?->name ?? 'System',
                    'role' => $log->role,
                    'note' => $log->note
                ];
            })
        ];

        return response()->json([
            'status' => 'success',
            'message' => "Export {$exportId} generated successfully",
            'format' => $format,
            'export_data' => $exportData
        ]);
    }
}

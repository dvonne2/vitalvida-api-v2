<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Payout;
use App\Models\PayoutActionLog;
use App\Helpers\SystemLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PayoutController extends Controller
{
    /**
     * Display a listing of payouts
     */
    public function index()
    {
        $payouts = Payout::with(['order'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $payouts
        ]);
    }

    /**
     * Store a newly created payout
     */
    public function store(Request $request)
    {
        // TODO: Add validation and payout creation logic
        return response()->json([
            'success' => true,
            'message' => 'Payout creation logic to be implemented'
        ]);
    }

    /**
     * Check if an order is eligible for payout
     */
    public function eligible($orderId)
    {
        $order = Order::with(['payment', 'otp', 'photo'])->find($orderId);

        if (!$order) {
            return response()->json(['error' => 'Order not found'], 404);
        }

        $hasPayment = $order->payment?->is_verified ?? false;
        $otpSubmitted = $order->otp?->is_submitted ?? false;
        $photoApproved = $order->photo?->is_approved ?? false;

        if ($hasPayment && $otpSubmitted && $photoApproved) {
            return response()->json([
                'eligible' => true,
                'order_id' => $order->id,
                'message' => 'Order is eligible for payout'
            ]);
        }

        return response()->json([
            'eligible' => false,
            'order_id' => $order->id,
            'reason' => [
                'payment' => $hasPayment,
                'otp' => $otpSubmitted,
                'photo' => $photoApproved,
            ],
            'message' => 'Order does not meet payout requirements'
        ]);
    }

    /**
     * Approve a payout
     */
    public function approve($id)
    {
        $payout = Payout::with('order')->find($id);

        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        if ($payout->status !== 'pending') {
            return response()->json(['error' => 'Only pending payouts can be approved'], 400);
        }

        // Check eligibility first
        $eligibilityCheck = $this->eligible($payout->order_id);
        $eligibilityData = $eligibilityCheck->getData();

        if (!$eligibilityData->eligible) {
            return response()->json([
                'error' => 'Order is not eligible for payout approval',
                'eligibility' => $eligibilityData
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Update payout status
            $payout->update([
                'status' => 'paid',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'approval_notes' => 'Approved manually'
            ]);

            // Log the approval action
            PayoutActionLog::create([
                'payout_id' => $payout->id,
                'action' => 'approved',
                'performed_by' => auth()->id(),
                'role' => 'FC',
                'note' => 'Approved manually'
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Payout approved successfully',
                'data' => $payout->fresh()
            ]);

        } catch (\Exception $e) {
            DB::rollback();
            return response()->json([
                'error' => 'Failed to approve payout',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Lock a payout due to fraud or issues
     */
    public function lock($id)
    {
        $payout = Payout::find($id);

        if (!$payout) {
            return response()->json(['error' => 'Payout not found'], 404);
        }

        // TODO: Add locking logic and log to PayoutActionLog
        return response()->json([
            'success' => true,
            'message' => 'Payout locking logic to be implemented'
        ]);
    }

    // === SECURITY VERIFICATION METHODS ===

    public function verifyOtpBeforePayout(Request $request)
    {
        try {
            $orderId = $request->order_id;
            $otpProvided = $request->otp;
            $daId = $request->da_id;

            // First check if payment was verified
            $paymentVerified = DB::table('system_logs')
                ->where('type', 'payment_verified')
                ->whereRaw("JSON_EXTRACT(context, '$.order_id') = ?", [$orderId])
                ->exists();

            if (!$paymentVerified) {
                // Log fraud attempt
                SystemLogger::logAction('fraud_attempt', auth()->id(), request()->ip(), [
                    'order_id' => $orderId,
                    'da_id' => $daId,
                    'reason' => 'OTP_WITHOUT_PAYMENT',
                    'otp_provided' => $otpProvided
                ]);

                return response()->json([
                    'success' => false,
                    'error' => 'Payment not verified. Cannot process OTP.',
                    'fraud_detected' => true
                ], 403);
            }

            // Get order and verify OTP
            $order = DB::table('orders')->where('id', $orderId)->first();
            if (!$order || $order->delivery_otp !== $otpProvided) {
                return response()->json(['success' => false, 'error' => 'Invalid OTP'], 400);
            }

            // Update order OTP status
            DB::table('orders')->where('id', $orderId)->update(['otp_verified' => true]);

            // Log successful OTP verification
            SystemLogger::logAction('otp_verified', auth()->id(), request()->ip(), [
                'order_id' => $orderId,
                'da_id' => $daId,
                'verified_at' => now()->toISOString()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'order_id' => $orderId
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function checkIMPhotoApproval($orderId)
    {
        try {
            $order = DB::table('orders')->where('id', $orderId)->first();
            if (!$order) {
                return response()->json(['success' => false, 'error' => 'Order not found'], 404);
            }

            $daId = $order->assigned_da_id;
            $currentWeek = now()->startOfWeek();

            // Check if photo was approved this week
            $photoApproved = DB::table('system_logs')
                ->where('type', 'photo_approved')
                ->whereRaw("JSON_EXTRACT(context, '$.da_id') = ?", [$daId])
                ->where('created_at', '>=', $currentWeek)
                ->exists();

            return response()->json([
                'success' => true,
                'photo_status' => $photoApproved ? 'approved' : 'missing',
                'da_id' => $daId,
                'week_start' => $currentWeek->toDateString(),
                'eligible_for_weekly_bonus' => $photoApproved
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function checkPaymentMatch($orderId)
    {
        try {
            $order = DB::table('orders')->where('id', $orderId)->first();
            if (!$order) {
                return response()->json(['success' => false, 'error' => 'Order not found'], 404);
            }

            // Simulate Moniepoint verification
            $paymentMatched = $this->simulateMoniepoint($order);

            if ($paymentMatched) {
                // Log payment verification
                SystemLogger::logAction('payment_verified', auth()->id(), request()->ip(), [
                    'order_id' => $orderId,
                    'amount' => $order->total_amount,
                    'customer_phone' => $order->customer_phone,
                    'payment_reference' => $order->payment_reference
                ]);
            }

            return response()->json([
                'success' => true,
                'payment_matched' => $paymentMatched,
                'order_id' => $orderId,
                'amount' => $order->total_amount
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    public function validateFullPayoutEligibility(Request $request)
    {
        try {
            $orderId = $request->order_id;
            $order = DB::table('orders')->where('id', $orderId)->first();

            if (!$order) {
                return response()->json(['success' => false, 'error' => 'Order not found'], 404);
            }

            $reasons = [];
            $eligible = true;

            // 1. Check payment verification
            $paymentVerified = DB::table('system_logs')
                ->where('type', 'payment_verified')
                ->whereRaw("JSON_EXTRACT(context, '$.order_id') = ?", [$orderId])
                ->exists();

            if (!$paymentVerified) {
                $eligible = false;
                $reasons[] = 'Payment not verified';
            }

            // 2. Check OTP verification
            if (!$order->otp_verified) {
                $eligible = false;
                $reasons[] = 'OTP not verified';
            }

            // 3. Check IM photo approval for weekly bonus
            $daId = $order->assigned_da_id;
            $currentWeek = now()->startOfWeek();

            $photoApproved = DB::table('system_logs')
                ->where('type', 'photo_approved')
                ->whereRaw("JSON_EXTRACT(context, '$.da_id') = ?", [$daId])
                ->where('created_at', '>=', $currentWeek)
                ->exists();

            if (!$photoApproved) {
                $eligible = false;
                $reasons[] = 'Photo not approved';
            }

            // Log the eligibility result
            if ($eligible) {
                SystemLogger::logAction('payout_approved', auth()->id(), request()->ip(), [
                    'order_id' => $orderId,
                    'amount' => $order->total_amount,
                    'da_id' => $order->assigned_da_id
                ]);
            } else {
                SystemLogger::logAction('payout_blocked', auth()->id(), request()->ip(), [
                    'order_id' => $orderId,
                    'da_id' => $order->assigned_da_id,
                    'reasons' => $reasons
                ]);
            }

            return response()->json([
                'success' => true,
                'eligible' => $eligible,
                'order_id' => $orderId,
                'reasons' => $reasons,
                'da_id' => $order->assigned_da_id,
                'amount' => $order->total_amount,
                'checked_at' => now()->toISOString()
            ]);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    // === PAYOUT WORKFLOW METHODS ===

    public function markIntent($orderId)
    {
        try {
            $order = DB::table('orders')->where('id', $orderId)->first();
            if (!$order) {
                return response()->json(['error' => 'Order not found'], 404);
            }

            // Create or update payout record
            $payoutId = DB::table('payouts')->insertGetId([
                'order_id' => $orderId,
                'amount' => $order->total_amount,
                'status' => 'intent_marked',
                'created_at' => now(),
                'updated_at' => now()
            ]);

            SystemLogger::logAction('payout_intent_marked', auth()->id(), request()->ip(), [
                'order_id' => $orderId,
                'payout_id' => $payoutId,
                'amount' => $order->total_amount,
                'da_id' => $order->assigned_da_id
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout intent marked',
                'payout_id' => $payoutId,
                'order_id' => $orderId,
                'status' => 'intent_marked',
                'amount' => $order->total_amount
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function confirmReceipt($orderId)
    {
        try {
            // This method only runs if middleware passed eligibility check
            $payout = DB::table('payouts')->where('order_id', $orderId)->first();
            
            if (!$payout) {
                return response()->json(['error' => 'Payout not found'], 404);
            }

            // Update payout status
            DB::table('payouts')->where('id', $payout->id)->update([
                'status' => 'receipt_confirmed',
                'updated_at' => now()
            ]);

            // Trigger notification event
            $this->notifyFinancialController($orderId, $payout->id);

            SystemLogger::logAction('payout_receipt_confirmed', auth()->id(), request()->ip(), [
                'order_id' => $orderId,
                'payout_id' => $payout->id,
                'amount' => $payout->amount,
                'da_id' => auth()->user()->delivery_agent_id ?? null
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Receipt confirmed - Financial Controller notified',
                'payout_id' => $payout->id,
                'status' => 'receipt_confirmed'
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function markPaid($orderId)
    {
        try {
            $payout = DB::table('payouts')->where('order_id', $orderId)->first();
            
            if (!$payout) {
                return response()->json(['error' => 'Payout not found'], 404);
            }

            // Final payout approval
            DB::table('payouts')->where('id', $payout->id)->update([
                'status' => 'paid',
                'approved_by' => auth()->id(),
                'approved_at' => now(),
                'updated_at' => now()
            ]);

            SystemLogger::logAction('payout_marked_paid', auth()->id(), request()->ip(), [
                'order_id' => $orderId,
                'payout_id' => $payout->id,
                'amount' => $payout->amount,
                'approved_by' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Payout marked as paid',
                'payout_id' => $payout->id,
                'status' => 'paid',
                'amount' => $payout->amount
            ]);

        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    private function notifyFinancialController($orderId, $payoutId)
    {
        // Log notification to FC and Compliance
        SystemLogger::logAction('fc_notification_sent', auth()->id(), request()->ip(), [
            'order_id' => $orderId,
            'payout_id' => $payoutId,
            'notification_type' => 'receipt_confirmed',
            'recipients' => ['financial_controller', 'compliance_officer']
        ]);

        // TODO: Add email notification, WebSocket, or Slack integration
    }

    private function simulateMoniepoint($order)
    {
        // TODO: Replace with actual Moniepoint API call
        // For now, return true if payment_reference exists
        return !empty($order->payment_reference);
    }
}

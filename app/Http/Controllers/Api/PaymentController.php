<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentMismatch;
use App\Models\Order;
use App\Models\OrderHistory;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class PaymentController extends Controller
{
    /**
     * Get payment reconciliation data
     */
    public function reconciliation(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $startDate = $period === 'today' ? today() : now()->startOfWeek();
        $endDate = $period === 'today' ? today() : now();

        $payments = Payment::whereBetween('created_at', [$startDate, $endDate])
            ->with(['order', 'verifiedBy'])
            ->get();

        $mismatches = PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->with(['order', 'staff'])
            ->get();

        $reconciliationData = [
            'total_payments' => $payments->count(),
            'confirmed_payments' => $payments->where('status', 'confirmed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'total_amount' => $payments->where('status', 'confirmed')->sum('amount'),
            'pending_amount' => $payments->where('status', 'pending')->sum('amount'),
            'mismatches_count' => $mismatches->count(),
            'mismatches_amount' => $mismatches->sum('amount_difference'),
            'auto_frozen_count' => $mismatches->where('auto_frozen', true)->count(),
            'pending_resolution' => $mismatches->where('resolution_status', 'pending')->count(),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $reconciliationData,
            'payments' => $payments->take(50),
            'mismatches' => $mismatches->take(50),
            'period' => $period,
        ]);
    }

    /**
     * Hold payout for payment
     */
    public function holdPayout(Request $request, $id): JsonResponse
    {
        $request->validate([
            'reason' => 'required|string|max:500',
            'hold_duration' => 'nullable|integer|min:1|max:30', // days
        ]);

        $payment = Payment::with('order')->findOrFail($id);
        
        $payment->update([
            'status' => 'on_hold',
            'hold_reason' => $request->reason,
            'hold_until' => $request->hold_duration ? now()->addDays($request->hold_duration) : null,
        ]);

        // Log the hold action
        OrderHistory::create([
            'order_id' => $payment->order_id,
            'staff_id' => auth()->id(),
            'action' => 'payout_held',
            'previous_status' => $payment->getOriginal('status'),
            'new_status' => 'on_hold',
            'timestamp' => now(),
            'notes' => "Payout held for payment {$payment->payment_id}. Reason: {$request->reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payout held successfully',
            'data' => $payment->fresh(),
        ]);
    }

    /**
     * Investigate payment
     */
    public function investigate(Request $request, $id): JsonResponse
    {
        $request->validate([
            'investigation_type' => 'required|in:fraud,suspicious,verification,dispute',
            'notes' => 'required|string|max:500',
        ]);

        $payment = Payment::with('order')->findOrFail($id);
        
        $payment->update([
            'status' => 'under_investigation',
            'investigation_type' => $request->investigation_type,
            'investigation_notes' => $request->notes,
            'investigation_started_at' => now(),
        ]);

        // Log the investigation
        OrderHistory::create([
            'order_id' => $payment->order_id,
            'staff_id' => auth()->id(),
            'action' => 'payment_investigation',
            'previous_status' => $payment->getOriginal('status'),
            'new_status' => 'under_investigation',
            'timestamp' => now(),
            'notes' => "Payment investigation started. Type: {$request->investigation_type}. Notes: {$request->notes}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Payment investigation started',
            'data' => $payment->fresh(),
        ]);
    }

    /**
     * Accept partial payment
     */
    public function acceptPartial(Request $request, $id): JsonResponse
    {
        $request->validate([
            'accepted_amount' => 'required|numeric|min:0',
            'reason' => 'required|string|max:500',
        ]);

        $payment = Payment::with('order')->findOrFail($id);
        
        if ($request->accepted_amount > $payment->amount) {
            return response()->json([
                'status' => 'error',
                'message' => 'Accepted amount cannot exceed original amount',
            ], 400);
        }

        $payment->update([
            'status' => 'partial_accepted',
            'accepted_amount' => $request->accepted_amount,
            'partial_reason' => $request->reason,
            'accepted_at' => now(),
        ]);

        // Log the partial acceptance
        OrderHistory::create([
            'order_id' => $payment->order_id,
            'staff_id' => auth()->id(),
            'action' => 'partial_payment_accepted',
            'previous_status' => $payment->getOriginal('status'),
            'new_status' => 'partial_accepted',
            'timestamp' => now(),
            'notes' => "Partial payment accepted. Amount: ₦{$request->accepted_amount}. Reason: {$request->reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Partial payment accepted',
            'data' => $payment->fresh(),
        ]);
    }

    /**
     * Get payment statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $startDate = $period === 'today' ? today() : now()->startOfWeek();
        $endDate = $period === 'today' ? today() : now();

        $payments = Payment::whereBetween('created_at', [$startDate, $endDate]);
        $mismatches = PaymentMismatch::whereBetween('created_at', [$startDate, $endDate]);

        $stats = [
            'total_payments' => $payments->count(),
            'confirmed_payments' => $payments->where('status', 'confirmed')->count(),
            'pending_payments' => $payments->where('status', 'pending')->count(),
            'failed_payments' => $payments->where('status', 'failed')->count(),
            'on_hold_payments' => $payments->where('status', 'on_hold')->count(),
            'under_investigation' => $payments->where('status', 'under_investigation')->count(),
            'total_amount' => $payments->where('status', 'confirmed')->sum('amount'),
            'pending_amount' => $payments->where('status', 'pending')->sum('amount'),
            'mismatches_count' => $mismatches->count(),
            'mismatches_amount' => $mismatches->sum('amount_difference'),
            'auto_frozen_count' => $mismatches->where('auto_frozen', true)->count(),
            'resolution_rate' => $this->calculateResolutionRate($startDate, $endDate),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
            'period' => $period,
        ]);
    }

    /**
     * Get payment details
     */
    public function show($id): JsonResponse
    {
        $payment = Payment::with(['order', 'verifiedBy'])->findOrFail($id);
        
        $relatedMismatches = PaymentMismatch::where('payment_id', $payment->id)->get();
        $paymentHistory = OrderHistory::where('order_id', $payment->order_id)
            ->where('action', 'like', '%payment%')
            ->orderBy('timestamp', 'desc')
            ->get();

        return response()->json([
            'status' => 'success',
            'data' => [
                'payment' => $payment,
                'related_mismatches' => $relatedMismatches,
                'payment_history' => $paymentHistory,
                'verification_status' => $this->getVerificationStatus($payment),
            ],
        ]);
    }

    /**
     * Calculate resolution rate for given period
     */
    private function calculateResolutionRate($startDate, $endDate): float
    {
        $totalMismatches = PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])->count();
        $resolvedMismatches = PaymentMismatch::whereBetween('created_at', [$startDate, $endDate])
            ->where('resolution_status', 'resolved')
            ->count();
        
        return $totalMismatches > 0 ? round(($resolvedMismatches / $totalMismatches) * 100, 2) : 0;
    }

    /**
     * Get verification status for payment
     */
    private function getVerificationStatus($payment): array
    {
        return [
            'is_verified' => $payment->status === 'confirmed',
            'verified_by' => $payment->verifiedBy?->name ?? 'Not verified',
            'verified_at' => $payment->verified_at?->format('Y-m-d H:i:s'),
            'processing_time' => $payment->created_at && $payment->verified_at ? 
                $payment->created_at->diffInMinutes($payment->verified_at) : null,
            'verification_method' => $payment->verification_method ?? 'manual',
            'confidence_score' => $this->calculateConfidenceScore($payment),
        ];
    }

    /**
     * Calculate confidence score for payment
     */
    private function calculateConfidenceScore($payment): float
    {
        $score = 0;
        
        // Base score for confirmed payments
        if ($payment->status === 'confirmed') {
            $score += 50;
        }
        
        // Bonus for quick verification
        if ($payment->created_at && $payment->verified_at) {
            $processingTime = $payment->created_at->diffInMinutes($payment->verified_at);
            if ($processingTime <= 5) $score += 20;
            elseif ($processingTime <= 15) $score += 10;
        }
        
        // Bonus for verified by system
        if ($payment->verification_method === 'auto') {
            $score += 15;
        }
        
        // Penalty for mismatches
        $mismatches = PaymentMismatch::where('payment_id', $payment->id)->count();
        $score -= ($mismatches * 10);
        
        return max(0, min(100, $score));
    }
}

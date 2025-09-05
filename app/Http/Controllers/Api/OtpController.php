<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OtpVerification;
use App\Models\DeliveryAgent;
use App\Models\Sale;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class OtpController extends Controller
{
    /**
     * Generate OTP for a transaction.
     */
    public function generateOtp(Request $request): JsonResponse
    {
        $request->validate([
            'delivery_agent_id' => 'required|exists:delivery_agents,id',
            'action_type' => 'required|in:sale,stock_deduction,transfer,adjustment,count',
            'reference_id' => 'required|integer',
            'reference_type' => 'required|string'
        ]);

        // Check if agent has active OTP
        $activeOtp = OtpVerification::where('delivery_agent_id', $request->delivery_agent_id)
            ->where('action_type', $request->action_type)
            ->where('reference_id', $request->reference_id)
            ->where('status', 'pending')
            ->where('expires_at', '>', now())
            ->first();

        if ($activeOtp) {
            return response()->json([
                'success' => true,
                'message' => 'OTP already generated',
                'data' => [
                    'otp_id' => $activeOtp->id,
                    'expires_at' => $activeOtp->expires_at,
                    'attempts_remaining' => $activeOtp->attempts_remaining
                ]
            ]);
        }

        // Create new OTP
        $otp = OtpVerification::create([
            'delivery_agent_id' => $request->delivery_agent_id,
            'action_type' => $request->action_type,
            'reference_id' => $request->reference_id,
            'reference_type' => $request->reference_type,
            'otp_code' => str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT),
            'generated_at' => now(),
            'expires_at' => now()->addMinutes(10),
            'status' => 'pending',
            'attempts' => 0,
            'max_attempts' => 3
        ]);

        Log::info("OTP generated for agent {$request->delivery_agent_id} for {$request->action_type}");

        return response()->json([
            'success' => true,
            'message' => 'OTP generated successfully',
            'data' => [
                'otp_id' => $otp->id,
                'expires_at' => $otp->expires_at,
                'attempts_remaining' => $otp->attempts_remaining
            ]
        ], 201);
    }

    /**
     * Verify OTP.
     */
    public function verifyOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp_id' => 'required|exists:otp_verifications,id',
            'otp_code' => 'required|string|size:6'
        ]);

        $otp = OtpVerification::findOrFail($request->otp_id);

        if (!$otp->canVerify()) {
            return response()->json([
                'success' => false,
                'message' => 'OTP cannot be verified',
                'error' => $this->getOtpError($otp)
            ], 400);
        }

        if ($otp->verify($request->otp_code)) {
            // Process the action based on action_type
            $this->processVerifiedAction($otp);

            Log::info("OTP verified successfully for agent {$otp->delivery_agent_id}");

            return response()->json([
                'success' => true,
                'message' => 'OTP verified successfully',
                'data' => [
                    'verified_at' => $otp->verified_at,
                    'action_type' => $otp->action_type,
                    'reference_id' => $otp->reference_id
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid OTP code',
            'data' => [
                'attempts_remaining' => $otp->attempts_remaining,
                'status' => $otp->status
            ]
        ], 400);
    }

    /**
     * Resend OTP.
     */
    public function resendOtp(Request $request): JsonResponse
    {
        $request->validate([
            'otp_id' => 'required|exists:otp_verifications,id'
        ]);

        $otp = OtpVerification::findOrFail($request->otp_id);

        if ($otp->isMaxAttemptsReached()) {
            return response()->json([
                'success' => false,
                'message' => 'Maximum attempts reached. Cannot resend OTP.'
            ], 400);
        }

        if ($otp->resend()) {
            Log::info("OTP resent for agent {$otp->delivery_agent_id}");

            return response()->json([
                'success' => true,
                'message' => 'OTP resent successfully',
                'data' => [
                    'otp_id' => $otp->id,
                    'expires_at' => $otp->expires_at,
                    'attempts_remaining' => $otp->attempts_remaining
                ]
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Failed to resend OTP'
        ], 400);
    }

    /**
     * Get OTP statistics.
     */
    public function getOtpStats(Request $request): JsonResponse
    {
        $startDate = $request->get('start_date', now()->subDays(30)->toDateString());
        $endDate = $request->get('end_date', now()->toDateString());
        $agentId = $request->get('delivery_agent_id');

        $query = OtpVerification::whereBetween('generated_at', [$startDate, $endDate]);

        if ($agentId) {
            $query->where('delivery_agent_id', $agentId);
        }

        $stats = $query->selectRaw('
                action_type,
                COUNT(*) as total_generated,
                COUNT(CASE WHEN status = "verified" THEN 1 END) as successful,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
                COUNT(CASE WHEN status = "expired" THEN 1 END) as expired,
                AVG(CASE WHEN status = "verified" THEN attempts END) as avg_attempts_success,
                AVG(CASE WHEN status = "failed" THEN attempts END) as avg_attempts_failed
            ')
            ->groupBy('action_type')
            ->get()
            ->map(function ($stat) {
                $successRate = $stat->total_generated > 0 ? 
                    round(($stat->successful / $stat->total_generated) * 100, 2) : 0;

                return [
                    'action_type' => $stat->action_type,
                    'total_generated' => $stat->total_generated,
                    'successful' => $stat->successful,
                    'failed' => $stat->failed,
                    'expired' => $stat->expired,
                    'success_rate' => $successRate,
                    'avg_attempts_success' => round($stat->avg_attempts_success ?? 0, 2),
                    'avg_attempts_failed' => round($stat->avg_attempts_failed ?? 0, 2)
                ];
            });

        $overallStats = [
            'total_generated' => $stats->sum('total_generated'),
            'total_successful' => $stats->sum('successful'),
            'total_failed' => $stats->sum('failed'),
            'total_expired' => $stats->sum('expired'),
            'overall_success_rate' => $stats->sum('total_generated') > 0 ? 
                round(($stats->sum('successful') / $stats->sum('total_generated')) * 100, 2) : 0
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'overall_stats' => $overallStats,
                'by_action_type' => $stats
            ],
            'filters' => [
                'start_date' => $startDate,
                'end_date' => $endDate,
                'delivery_agent_id' => $agentId
            ]
        ]);
    }

    /**
     * Get OTP error message.
     */
    private function getOtpError(OtpVerification $otp): string
    {
        if ($otp->isExpired()) {
            return 'OTP has expired';
        }

        if ($otp->isMaxAttemptsReached()) {
            return 'Maximum attempts reached';
        }

        if ($otp->status !== 'pending') {
            return 'OTP is not in pending status';
        }

        return 'OTP cannot be verified';
    }

    /**
     * Process verified action.
     */
    private function processVerifiedAction(OtpVerification $otp): void
    {
        switch ($otp->action_type) {
            case 'sale':
                $this->processSaleVerification($otp);
                break;
            case 'stock_deduction':
                $this->processStockDeduction($otp);
                break;
            case 'transfer':
                $this->processTransferVerification($otp);
                break;
            case 'adjustment':
                $this->processAdjustmentVerification($otp);
                break;
            case 'count':
                $this->processCountVerification($otp);
                break;
        }
    }

    /**
     * Process sale verification.
     */
    private function processSaleVerification(OtpVerification $otp): void
    {
        $sale = Sale::find($otp->reference_id);
        if ($sale && $sale->canVerify()) {
            $sale->verify($otp->delivery_agent_id);
        }
    }

    /**
     * Process stock deduction.
     */
    private function processStockDeduction(OtpVerification $otp): void
    {
        // Handle stock deduction logic
        Log::info("Stock deduction verified for OTP {$otp->id}");
    }

    /**
     * Process transfer verification.
     */
    private function processTransferVerification(OtpVerification $otp): void
    {
        // Handle transfer verification logic
        Log::info("Transfer verified for OTP {$otp->id}");
    }

    /**
     * Process adjustment verification.
     */
    private function processAdjustmentVerification(OtpVerification $otp): void
    {
        // Handle adjustment verification logic
        Log::info("Adjustment verified for OTP {$otp->id}");
    }

    /**
     * Process count verification.
     */
    private function processCountVerification(OtpVerification $otp): void
    {
        // Handle count verification logic
        Log::info("Count verified for OTP {$otp->id}");
    }
} 
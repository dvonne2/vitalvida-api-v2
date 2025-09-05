<?php

namespace App\Services;

use App\Models\SalaryDeduction;
use App\Models\EscalationRequest;
use App\Models\ThresholdViolation;
use App\Models\User;
use App\Notifications\SalaryDeductionNotification;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class SalaryDeductionService
{
    /**
     * Deduction rules for different violation types
     */
    private const DEDUCTION_RULES = [
        'unauthorized_payment' => [
            'percentage' => 100, // 100% of overage amount
            'minimum' => 1000, // Minimum ₦1,000 deduction
            'maximum' => 50000, // Maximum ₦50,000 per incident
            'delay_days' => 30 // Applied in next salary cycle (30 days)
        ],
        'rejected_escalation' => [
            'percentage' => 50, // 50% of overage amount
            'minimum' => 500, // Minimum ₦500 deduction
            'maximum' => 25000, // Maximum ₦25,000 per incident
            'delay_days' => 15 // Applied in 15 days
        ],
        'expired_escalation' => [
            'percentage' => 75, // 75% of overage amount
            'minimum' => 750, // Minimum ₦750 deduction
            'maximum' => 37500, // Maximum ₦37,500 per incident
            'delay_days' => 7 // Applied in 7 days (immediate)
        ]
    ];

    /**
     * Create salary deduction for unauthorized payment
     */
    public function createDeductionForUnauthorizedPayment(ThresholdViolation $violation): SalaryDeduction
    {
        $deductionAmount = $this->calculateDeductionAmount(
            $violation->overage_amount,
            'unauthorized_payment'
        );

        $deduction = SalaryDeduction::create([
            'user_id' => $violation->created_by,
            'violation_id' => $violation->id,
            'amount' => $deductionAmount,
            'reason' => 'unauthorized_payment',
            'description' => "Unauthorized payment exceeding threshold by ₦" . number_format($violation->overage_amount, 2),
            'deduction_date' => now()->addDays(self::DEDUCTION_RULES['unauthorized_payment']['delay_days']),
            'status' => 'pending',
            'metadata' => [
                'violation_type' => 'unauthorized_payment',
                'original_amount' => $violation->amount,
                'threshold_limit' => $violation->threshold_limit,
                'overage_amount' => $violation->overage_amount,
                'cost_type' => $violation->cost_type,
                'cost_category' => $violation->cost_category,
                'created_at' => now(),
                'rule_applied' => self::DEDUCTION_RULES['unauthorized_payment']
            ]
        ]);

        // Notify the user
        $this->notifyUser($deduction, 'unauthorized_payment');

        Log::info('Salary deduction created for unauthorized payment', [
            'deduction_id' => $deduction->id,
            'user_id' => $violation->created_by,
            'violation_id' => $violation->id,
            'amount' => $deductionAmount,
            'overage_amount' => $violation->overage_amount,
            'deduction_date' => $deduction->deduction_date
        ]);

        return $deduction;
    }

    /**
     * Create salary deduction for rejected escalation
     */
    public function createDeductionForRejectedEscalation(EscalationRequest $escalation): SalaryDeduction
    {
        $deductionAmount = $this->calculateDeductionAmount(
            $escalation->overage_amount,
            'rejected_escalation'
        );

        $deduction = SalaryDeduction::create([
            'user_id' => $escalation->created_by,
            'violation_id' => $escalation->thresholdViolation->id,
            'amount' => $deductionAmount,
            'reason' => 'rejected_escalation',
            'description' => "Escalation rejected - attempted expense exceeding threshold by ₦" . number_format($escalation->overage_amount, 2),
            'deduction_date' => now()->addDays(self::DEDUCTION_RULES['rejected_escalation']['delay_days']),
            'status' => 'pending',
            'metadata' => [
                'violation_type' => 'rejected_escalation',
                'escalation_id' => $escalation->id,
                'original_amount' => $escalation->amount_requested,
                'threshold_limit' => $escalation->threshold_limit,
                'overage_amount' => $escalation->overage_amount,
                'escalation_type' => $escalation->escalation_type,
                'rejection_reason' => $escalation->rejection_reason,
                'approvers_rejected' => $escalation->approvalDecisions()
                    ->where('decision', 'rejected')
                    ->with('approver')
                    ->get()
                    ->pluck('approver.name')
                    ->toArray(),
                'created_at' => now(),
                'rule_applied' => self::DEDUCTION_RULES['rejected_escalation']
            ]
        ]);

        // Notify the user
        $this->notifyUser($deduction, 'rejected_escalation');

        Log::info('Salary deduction created for rejected escalation', [
            'deduction_id' => $deduction->id,
            'user_id' => $escalation->created_by,
            'escalation_id' => $escalation->id,
            'amount' => $deductionAmount,
            'overage_amount' => $escalation->overage_amount,
            'deduction_date' => $deduction->deduction_date
        ]);

        return $deduction;
    }

    /**
     * Create salary deduction for expired escalation
     */
    public function createDeductionForExpiredEscalation(EscalationRequest $escalation): SalaryDeduction
    {
        $deductionAmount = $this->calculateDeductionAmount(
            $escalation->overage_amount,
            'expired_escalation'
        );

        $deduction = SalaryDeduction::create([
            'user_id' => $escalation->created_by,
            'violation_id' => $escalation->thresholdViolation->id,
            'amount' => $deductionAmount,
            'reason' => 'expired_escalation',
            'description' => "Escalation expired without approval - attempted expense exceeding threshold by ₦" . number_format($escalation->overage_amount, 2),
            'deduction_date' => now()->addDays(self::DEDUCTION_RULES['expired_escalation']['delay_days']),
            'status' => 'pending',
            'metadata' => [
                'violation_type' => 'expired_escalation',
                'escalation_id' => $escalation->id,
                'original_amount' => $escalation->amount_requested,
                'threshold_limit' => $escalation->threshold_limit,
                'overage_amount' => $escalation->overage_amount,
                'escalation_type' => $escalation->escalation_type,
                'expired_at' => $escalation->expires_at,
                'business_justification' => $escalation->business_justification,
                'created_at' => now(),
                'rule_applied' => self::DEDUCTION_RULES['expired_escalation']
            ]
        ]);

        // Notify the user
        $this->notifyUser($deduction, 'expired_escalation');

        Log::info('Salary deduction created for expired escalation', [
            'deduction_id' => $deduction->id,
            'user_id' => $escalation->created_by,
            'escalation_id' => $escalation->id,
            'amount' => $deductionAmount,
            'overage_amount' => $escalation->overage_amount,
            'deduction_date' => $deduction->deduction_date,
            'expired_at' => $escalation->expires_at
        ]);

        return $deduction;
    }

    /**
     * Process all pending salary deductions
     */
    public function processPendingDeductions(): array
    {
        $pendingDeductions = SalaryDeduction::where('status', 'pending')
            ->where('deduction_date', '<=', now())
            ->with(['user', 'violation'])
            ->get();

        $results = [
            'total_processed' => 0,
            'total_amount' => 0,
            'successful' => [],
            'failed' => []
        ];

        foreach ($pendingDeductions as $deduction) {
            try {
                $this->processDeduction($deduction);
                
                $results['successful'][] = [
                    'deduction_id' => $deduction->id,
                    'user_id' => $deduction->user_id,
                    'user_name' => $deduction->user->name,
                    'amount' => $deduction->amount,
                    'reason' => $deduction->reason
                ];
                
                $results['total_processed']++;
                $results['total_amount'] += $deduction->amount;
                
            } catch (\Exception $e) {
                $results['failed'][] = [
                    'deduction_id' => $deduction->id,
                    'user_id' => $deduction->user_id,
                    'user_name' => $deduction->user->name ?? 'Unknown',
                    'amount' => $deduction->amount,
                    'error' => $e->getMessage()
                ];
                
                Log::error('Failed to process salary deduction', [
                    'deduction_id' => $deduction->id,
                    'user_id' => $deduction->user_id,
                    'amount' => $deduction->amount,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Salary deductions processing completed', [
            'total_found' => $pendingDeductions->count(),
            'successful' => count($results['successful']),
            'failed' => count($results['failed']),
            'total_amount' => $results['total_amount']
        ]);

        return $results;
    }

    /**
     * Get salary deduction statistics
     */
    public function getDeductionStatistics(array $filters = []): array
    {
        $query = SalaryDeduction::query();

        // Apply filters
        if (isset($filters['user_id'])) {
            $query->where('user_id', $filters['user_id']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        // Get base statistics
        $totalDeductions = $query->count();
        $totalAmount = $query->sum('amount');
        $pendingDeductions = $query->clone()->where('status', 'pending')->count();
        $processedDeductions = $query->clone()->where('status', 'processed')->count();
        $cancelledDeductions = $query->clone()->where('status', 'cancelled')->count();

        // Get amount by status
        $pendingAmount = $query->clone()->where('status', 'pending')->sum('amount');
        $processedAmount = $query->clone()->where('status', 'processed')->sum('amount');
        $cancelledAmount = $query->clone()->where('status', 'cancelled')->sum('amount');

        // Get deductions by reason
        $byReason = $query->clone()
            ->selectRaw('reason, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('reason')
            ->get()
            ->map(function($item) {
                return [
                    'reason' => $item->reason,
                    'count' => $item->count,
                    'total_amount' => $item->total_amount,
                    'average_amount' => $item->count > 0 ? round($item->total_amount / $item->count, 2) : 0
                ];
            });

        // Get upcoming deductions (next 30 days)
        $upcomingDeductions = SalaryDeduction::where('status', 'pending')
            ->where('deduction_date', '>', now())
            ->where('deduction_date', '<=', now()->addDays(30))
            ->count();

        $upcomingAmount = SalaryDeduction::where('status', 'pending')
            ->where('deduction_date', '>', now())
            ->where('deduction_date', '<=', now()->addDays(30))
            ->sum('amount');

        return [
            'overview' => [
                'total_deductions' => $totalDeductions,
                'total_amount' => $totalAmount,
                'average_deduction' => $totalDeductions > 0 ? round($totalAmount / $totalDeductions, 2) : 0,
                'pending_count' => $pendingDeductions,
                'processed_count' => $processedDeductions,
                'cancelled_count' => $cancelledDeductions
            ],
            'amounts_by_status' => [
                'pending' => $pendingAmount,
                'processed' => $processedAmount,
                'cancelled' => $cancelledAmount
            ],
            'by_reason' => $byReason,
            'upcoming_deductions' => [
                'count' => $upcomingDeductions,
                'amount' => $upcomingAmount
            ],
            'processing_rates' => [
                'processing_rate' => $totalDeductions > 0 ? round(($processedDeductions / $totalDeductions) * 100, 1) : 0,
                'cancellation_rate' => $totalDeductions > 0 ? round(($cancelledDeductions / $totalDeductions) * 100, 1) : 0
            ]
        ];
    }

    /**
     * Get user-specific salary deductions
     */
    public function getUserDeductions(int $userId, array $filters = []): array
    {
        $query = SalaryDeduction::where('user_id', $userId)
            ->with(['violation']);

        // Apply filters
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['reason'])) {
            $query->where('reason', $filters['reason']);
        }

        if (isset($filters['date_from'])) {
            $query->where('created_at', '>=', $filters['date_from']);
        }

        if (isset($filters['date_to'])) {
            $query->where('created_at', '<=', $filters['date_to']);
        }

        $deductions = $query->orderBy('created_at', 'desc')
            ->get()
            ->map(function($deduction) {
                return [
                    'id' => $deduction->id,
                    'amount' => $deduction->amount,
                    'reason' => $deduction->reason,
                    'description' => $deduction->description,
                    'status' => $deduction->status,
                    'deduction_date' => $deduction->deduction_date,
                    'processed_date' => $deduction->processed_date,
                    'created_at' => $deduction->created_at,
                    'days_until_deduction' => $deduction->status === 'pending' ? 
                        max(0, now()->diffInDays($deduction->deduction_date, false)) : null,
                    'violation_details' => $deduction->violation ? [
                        'cost_type' => $deduction->violation->cost_type,
                        'cost_category' => $deduction->violation->cost_category,
                        'original_amount' => $deduction->violation->amount,
                        'threshold_limit' => $deduction->violation->threshold_limit,
                        'overage_amount' => $deduction->violation->overage_amount
                    ] : null
                ];
            });

        // Get user summary
        $totalDeductions = $deductions->count();
        $totalAmount = $deductions->sum('amount');
        $pendingAmount = $deductions->where('status', 'pending')->sum('amount');
        $processedAmount = $deductions->where('status', 'processed')->sum('amount');

        return [
            'deductions' => $deductions,
            'summary' => [
                'total_deductions' => $totalDeductions,
                'total_amount' => $totalAmount,
                'pending_amount' => $pendingAmount,
                'processed_amount' => $processedAmount,
                'next_deduction_date' => $deductions->where('status', 'pending')->min('deduction_date')
            ]
        ];
    }

    /**
     * Cancel a salary deduction
     */
    public function cancelDeduction(int $deductionId, ?string $reason = null): bool
    {
        try {
            $deduction = SalaryDeduction::findOrFail($deductionId);

            if ($deduction->status !== 'pending') {
                throw new \Exception('Only pending deductions can be cancelled');
            }

            $deduction->update([
                'status' => 'cancelled',
                'metadata' => array_merge($deduction->metadata ?? [], [
                    'cancelled_at' => now(),
                    'cancellation_reason' => $reason,
                    'cancelled_by' => auth()->id()
                ])
            ]);

            // Notify the user
            $this->notifyUser($deduction, 'cancelled');

            Log::info('Salary deduction cancelled', [
                'deduction_id' => $deduction->id,
                'user_id' => $deduction->user_id,
                'amount' => $deduction->amount,
                'reason' => $reason,
                'cancelled_by' => auth()->id()
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to cancel salary deduction', [
                'deduction_id' => $deductionId,
                'reason' => $reason,
                'error' => $e->getMessage()
            ]);

            return false;
        }
    }

    /**
     * Calculate deduction amount based on rules
     */
    private function calculateDeductionAmount(float $overageAmount, string $violationType): float
    {
        $rules = self::DEDUCTION_RULES[$violationType];
        
        $calculatedAmount = $overageAmount * ($rules['percentage'] / 100);
        
        // Apply minimum and maximum limits
        $amount = max($rules['minimum'], $calculatedAmount);
        $amount = min($rules['maximum'], $amount);
        
        return round($amount, 2);
    }

    /**
     * Process a single salary deduction
     */
    private function processDeduction(SalaryDeduction $deduction): void
    {
        DB::transaction(function() use ($deduction) {
            // Update deduction status
            $deduction->update([
                'status' => 'processed',
                'processed_date' => now(),
                'metadata' => array_merge($deduction->metadata ?? [], [
                    'processed_at' => now(),
                    'processed_by' => 'system'
                ])
            ]);

            // Here you would integrate with your payroll system
            // For now, we'll just log the processing
            Log::info('Salary deduction processed', [
                'deduction_id' => $deduction->id,
                'user_id' => $deduction->user_id,
                'amount' => $deduction->amount,
                'reason' => $deduction->reason
            ]);

            // Notify the user
            $this->notifyUser($deduction, 'processed');
        });
    }

    /**
     * Notify user about salary deduction
     */
    private function notifyUser(SalaryDeduction $deduction, string $notificationType): void
    {
        try {
            if ($deduction->user) {
                $deduction->user->notify(new SalaryDeductionNotification($deduction, $notificationType));
            }
        } catch (\Exception $e) {
            Log::error('Failed to send salary deduction notification', [
                'deduction_id' => $deduction->id,
                'user_id' => $deduction->user_id,
                'notification_type' => $notificationType,
                'error' => $e->getMessage()
            ]);
        }
    }
} 
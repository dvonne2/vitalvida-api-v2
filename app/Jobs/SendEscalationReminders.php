<?php

namespace App\Jobs;

use App\Models\EscalationRequest;
use App\Models\User;
use App\Notifications\EscalationReminderNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SendEscalationReminders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Create a new job instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        Log::info('Sending escalation reminders job started');

        // Get escalations that are expiring soon (within 6 hours)
        $expiringEscalations = EscalationRequest::with(['thresholdViolation', 'creator', 'approvalDecisions'])
            ->where('status', 'pending_approval')
            ->where('expires_at', '>', now())
            ->where('expires_at', '<=', now()->addHours(6))
            ->get();

        $remindersUrgent = 0;
        $remindersNormal = 0;
        $failedReminders = 0;

        foreach ($expiringEscalations as $escalation) {
            try {
                $this->sendReminderForEscalation($escalation);

                $hoursRemaining = $escalation->expires_at->diffInHours(now());
                if ($hoursRemaining <= 2) {
                    $remindersUrgent++;
                } else {
                    $remindersNormal++;
                }

                Log::info('Escalation reminder sent', [
                    'escalation_id' => $escalation->id,
                    'amount' => $escalation->amount_requested,
                    'hours_remaining' => $hoursRemaining,
                    'pending_approvers' => $escalation->getPendingApprovers()
                ]);

            } catch (\Exception $e) {
                $failedReminders++;
                Log::error('Failed to send escalation reminder', [
                    'escalation_id' => $escalation->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        Log::info('Escalation reminders job completed', [
            'total_escalations' => $expiringEscalations->count(),
            'urgent_reminders' => $remindersUrgent,
            'normal_reminders' => $remindersNormal,
            'failed_reminders' => $failedReminders
        ]);
    }

    /**
     * Send reminder for a specific escalation
     */
    private function sendReminderForEscalation(EscalationRequest $escalation): void
    {
        $pendingApprovers = $escalation->getPendingApprovers();

        foreach ($pendingApprovers as $approverRole) {
            // Find users with this role
            $approvers = User::where('role', $approverRole)->get();

            foreach ($approvers as $approver) {
                try {
                    $approver->notify(new EscalationReminderNotification($escalation));
                    
                    Log::info('Escalation reminder sent to approver', [
                        'escalation_id' => $escalation->id,
                        'approver_id' => $approver->id,
                        'approver_name' => $approver->name,
                        'approver_role' => $approverRole,
                        'hours_remaining' => $escalation->expires_at->diffInHours(now())
                    ]);

                } catch (\Exception $e) {
                    Log::error('Failed to send reminder to specific approver', [
                        'escalation_id' => $escalation->id,
                        'approver_id' => $approver->id,
                        'approver_name' => $approver->name,
                        'approver_role' => $approverRole,
                        'error' => $e->getMessage()
                    ]);
                }
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('SendEscalationReminders job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString()
        ]);
    }
}

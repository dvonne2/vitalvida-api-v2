<?php

namespace App\Notifications;

use App\Models\EscalationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ApprovalDecisionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $escalation;

    /**
     * Create a new notification instance.
     */
    public function __construct(EscalationRequest $escalation)
    {
        $this->escalation = $escalation;
    }

    /**
     * Get the notification's delivery channels.
     */
    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail($notifiable): MailMessage
    {
        return match($this->escalation->final_outcome) {
            'approved' => $this->approvedMail($notifiable),
            'rejected' => $this->rejectedMail($notifiable),
            default => $this->defaultMail($notifiable)
        };
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'approval_decision',
            'escalation_id' => $this->escalation->id,
            'amount' => $this->escalation->amount_requested,
            'overage_amount' => $this->escalation->overage_amount,
            'decision' => $this->escalation->final_outcome,
            'decision_at' => $this->escalation->final_decision_at,
            'escalation_type' => $this->escalation->escalation_type,
            'priority' => $this->escalation->priority,
            'violation_id' => $this->escalation->thresholdViolation->id ?? null,
            'message' => $this->escalation->final_outcome === 'approved' ? 
                'Your expense escalation has been approved' : 
                'Your expense escalation has been rejected'
        ];
    }

    /**
     * Generate approved mail
     */
    private function approvedMail($notifiable): MailMessage
    {
        $approvalDecisions = $this->escalation->approvalDecisions;
        $approvedBy = $approvalDecisions->map(function($decision) {
            return $decision->approver->name . ' (' . strtoupper($decision->approver_role) . ')';
        })->implode(', ');

        return (new MailMessage)
            ->subject('✅ ESCALATION APPROVED: Payment Authorized')
            ->greeting("Dear {$notifiable->name},")
            ->line("Great news! Your expense escalation has been approved and your payment is now authorized.")
            ->line("**Escalation Details:**")
            ->line("• Amount Requested: ₦" . number_format($this->escalation->amount_requested, 2))
            ->line("• Threshold Limit: ₦" . number_format($this->escalation->threshold_limit, 2))
            ->line("• Overage Amount: ₦" . number_format($this->escalation->overage_amount, 2))
            ->line("• Escalation Type: {$this->escalation->escalation_type}")
            ->line("• Approved Date: {$this->escalation->final_decision_at->format('M j, Y g:i A')}")
            ->line('')
            ->line("**Approval Details:**")
            ->line("• Approved By: {$approvedBy}")
            ->line("• Decision Time: {$this->escalation->created_at->diffForHumans($this->escalation->final_decision_at, true)}")
            ->line('')
            ->line("**What This Means:**")
            ->line("• Your payment request has been authorized")
            ->line("• You can proceed with the expense processing")
            ->line("• The threshold violation has been officially approved")
            ->line("• No salary deduction will be applied")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Proceed with your authorized payment")
            ->line("2. Keep records of this approval for future reference")
            ->line("3. Continue following proper expense procedures")
            ->line("4. Notify finance team if immediate processing is needed")
            ->action('View Escalation Details', url("/employee/escalations/{$this->escalation->id}"))
            ->line("**Important:** This approval is specific to this expense. Future expenses must still comply with established thresholds or require separate approval.");
    }

    /**
     * Generate rejected mail
     */
    private function rejectedMail($notifiable): MailMessage
    {
        $rejectionDecisions = $this->escalation->approvalDecisions()
            ->where('decision', 'rejected')
            ->with('approver')
            ->get();

        $rejectedBy = $rejectionDecisions->map(function($decision) {
            return $decision->approver->name . ' (' . strtoupper($decision->approver_role) . ')';
        })->implode(', ');

        $rejectionReasons = $rejectionDecisions->map(function($decision) {
            return "• {$decision->approver->name}: {$decision->decision_reason}";
        })->implode("\n");

        return (new MailMessage)
            ->subject('❌ ESCALATION REJECTED: Payment Denied')
            ->greeting("Dear {$notifiable->name},")
            ->line("We regret to inform you that your expense escalation has been rejected.")
            ->line("**Escalation Details:**")
            ->line("• Amount Requested: ₦" . number_format($this->escalation->amount_requested, 2))
            ->line("• Threshold Limit: ₦" . number_format($this->escalation->threshold_limit, 2))
            ->line("• Overage Amount: ₦" . number_format($this->escalation->overage_amount, 2))
            ->line("• Escalation Type: {$this->escalation->escalation_type}")
            ->line("• Rejected Date: {$this->escalation->final_decision_at->format('M j, Y g:i A')}")
            ->line('')
            ->line("**Rejection Details:**")
            ->line("• Rejected By: {$rejectedBy}")
            ->line("• Decision Time: {$this->escalation->created_at->diffForHumans($this->escalation->final_decision_at, true)}")
            ->line('')
            ->line("**Rejection Reasons:**")
            ->line($rejectionReasons)
            ->line('')
            ->line("**What This Means:**")
            ->line("• Your payment request has been denied")
            ->line("• The expense cannot be processed as requested")
            ->line("• A salary deduction will be applied for the unauthorized attempt")
            ->line("• The deduction amount will be calculated based on company policy")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Review the rejection reasons carefully")
            ->line("2. Ensure future expenses comply with threshold limits")
            ->line("3. Contact your supervisor if you need clarification")
            ->line("4. Check your salary deduction details in the system")
            ->action('View Escalation Details', url("/employee/escalations/{$this->escalation->id}"))
            ->line("**Important:** Future threshold violations may result in additional disciplinary action. Please ensure all expenses comply with company policies.");
    }

    /**
     * Generate default mail
     */
    private function defaultMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📋 ESCALATION UPDATE: Decision Made')
            ->greeting("Dear {$notifiable->name},")
            ->line("A decision has been made on your expense escalation.")
            ->line("**Escalation Details:**")
            ->line("• Amount Requested: ₦" . number_format($this->escalation->amount_requested, 2))
            ->line("• Threshold Limit: ₦" . number_format($this->escalation->threshold_limit, 2))
            ->line("• Overage Amount: ₦" . number_format($this->escalation->overage_amount, 2))
            ->line("• Final Status: " . strtoupper($this->escalation->status))
            ->line("• Decision Date: {$this->escalation->final_decision_at->format('M j, Y g:i A')}")
            ->action('View Escalation Details', url("/employee/escalations/{$this->escalation->id}"))
            ->line('Please review the escalation details for more information.');
    }
}

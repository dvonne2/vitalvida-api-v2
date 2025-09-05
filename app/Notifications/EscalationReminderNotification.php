<?php

namespace App\Notifications;

use App\Models\EscalationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EscalationReminderNotification extends Notification implements ShouldQueue
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
        $hoursRemaining = $this->escalation->expires_at->diffInHours(now());
        $isUrgent = $hoursRemaining <= 2;

        return (new MailMessage)
            ->subject($isUrgent ?
                '🚨 URGENT: Escalation Expires in ' . $hoursRemaining . ' hours' :
                '⏰ REMINDER: Escalation Requires Your Approval')
            ->greeting("Hello {$notifiable->name},")
            ->line($isUrgent ?
                "**URGENT**: An escalation requiring your approval will expire in {$hoursRemaining} hours." :
                "This is a reminder that an escalation is waiting for your approval.")
            ->line('')
            ->line("**Escalation Details:**")
            ->line("• Escalation ID: #{$this->escalation->id}")
            ->line("• Amount Requested: ₦" . number_format($this->escalation->amount_requested, 2))
            ->line("• Threshold Limit: ₦" . number_format($this->escalation->threshold_limit, 2))
            ->line("• Overage Amount: ₦" . number_format($this->escalation->overage_amount, 2))
            ->line("• Overage Percentage: " . ($this->escalation->threshold_limit > 0 ?
                round(($this->escalation->overage_amount / $this->escalation->threshold_limit) * 100, 1) : 0) . "%")
            ->line("• Escalation Type: {$this->escalation->escalation_type}")
            ->line("• Priority: " . strtoupper($this->escalation->priority))
            ->line("• Created: {$this->escalation->created_at->format('M j, Y g:i A')}")
            ->line("• Expires: {$this->escalation->expires_at->format('M j, Y g:i A')}")
            ->line("• Time Remaining: {$this->escalation->expires_at->diffForHumans()}")
            ->line('')
            ->line("**Requester Information:**")
            ->line("• Name: {$this->escalation->creator->name}")
            ->line("• Email: {$this->escalation->creator->email}")
            ->line("• Role: {$this->escalation->creator->role}")
            ->line('')
            ->line("**Business Justification:**")
            ->line($this->escalation->business_justification ?: 'No business justification provided')
            ->line('')
            ->line("**Escalation Reason:**")
            ->line($this->escalation->escalation_reason)
            ->line('')
            ->line("**Required Approvals:**")
            ->line("• Required Approvers: " . implode(', ', array_map('strtoupper', $this->escalation->approval_required)))
            ->line("• Pending Approvers: " . implode(', ', array_map('strtoupper', $this->escalation->getPendingApprovers())))
            ->line("• Approval Status: {$this->escalation->approvalDecisions->count()} of " . count($this->escalation->approval_required) . " decisions received")
            ->line('')
            ->line($isUrgent ?
                "**⚠️ URGENT ACTION REQUIRED:**" :
                "**Action Required:**")
            ->line($isUrgent ?
                "• This escalation expires in {$hoursRemaining} hours - immediate action needed!" :
                "• Please review and make a decision on this escalation")
            ->line("• Both FC and GM approval are required for this amount")
            ->line("• If no decision is made before expiration, the escalation will be auto-rejected")
            ->line("• Auto-rejection will result in automatic salary deduction for the requester")
            ->line('')
            ->line("**Decision Options:**")
            ->line("• **APPROVE**: Authorize the payment and clear the violation")
            ->line("• **REJECT**: Deny the payment and apply salary deduction penalty")
            ->line('')
            ->line("**Important Notes:**")
            ->line("• Dual approval is required - both FC and GM must approve")
            ->line("• If either approver rejects, the entire escalation is rejected")
            ->line("• Rejection or expiration triggers automatic salary deduction")
            ->line("• Deduction amount varies based on violation type and outcome")
            ->line("")
            ->line("**Salary Deduction Schedule:**")
            ->line("• Approved: No deduction")
            ->line("• Rejected: 50% of overage amount")
            ->line("• Expired: 75% of overage amount")
            ->line("• Unauthorized: 100% of overage amount")
            ->action('Review & Approve Now', url("/admin/escalations/{$this->escalation->id}"))
            ->line($isUrgent ?
                "**This escalation expires in {$hoursRemaining} hours. Please take immediate action to prevent auto-rejection and salary deduction.**" :
                'Thank you for your prompt attention to this matter.');
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        $hoursRemaining = $this->escalation->expires_at->diffInHours(now());
        $isUrgent = $hoursRemaining <= 2;

        return [
            'type' => 'escalation_reminder',
            'escalation_id' => $this->escalation->id,
            'amount_requested' => $this->escalation->amount_requested,
            'threshold_limit' => $this->escalation->threshold_limit,
            'overage_amount' => $this->escalation->overage_amount,
            'escalation_type' => $this->escalation->escalation_type,
            'priority' => $this->escalation->priority,
            'expires_at' => $this->escalation->expires_at,
            'hours_remaining' => $hoursRemaining,
            'is_urgent' => $isUrgent,
            'urgency_level' => $isUrgent ? 'urgent' : 'normal',
            'requester_name' => $this->escalation->creator->name,
            'requester_email' => $this->escalation->creator->email,
            'approver_role' => $notifiable->role,
            'pending_approvers' => $this->escalation->getPendingApprovers(),
            'approval_progress' => [
                'received' => $this->escalation->approvalDecisions->count(),
                'required' => count($this->escalation->approval_required),
                'percentage' => count($this->escalation->approval_required) > 0 ?
                    round(($this->escalation->approvalDecisions->count() / count($this->escalation->approval_required)) * 100, 1) : 0
            ],
            'business_justification' => $this->escalation->business_justification,
            'escalation_reason' => $this->escalation->escalation_reason,
            'message' => $isUrgent ?
                "URGENT: Escalation expires in {$hoursRemaining} hours - immediate action required!" :
                "Escalation reminder: Your approval is needed for expense exceeding threshold"
        ];
    }
}

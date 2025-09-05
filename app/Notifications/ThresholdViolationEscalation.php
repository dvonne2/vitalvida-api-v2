<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\EscalationRequest;

class ThresholdViolationEscalation extends Notification
{
    public function __construct(
        private EscalationRequest $escalation
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🚨 URGENT: Threshold Violation Requires Your Approval')
            ->greeting("Hello {$notifiable->name},")
            ->line("A payment exceeding business thresholds requires your immediate approval.")
            ->line("**Amount Requested**: ₦" . number_format($this->escalation->amount_requested, 2))
            ->line("**Threshold Limit**: ₦" . number_format($this->escalation->threshold_limit, 2))
            ->line("**Overage Amount**: ₦" . number_format($this->escalation->overage_amount, 2))
            ->line("**Reason**: {$this->escalation->escalation_reason}")
            ->line("**Priority**: " . strtoupper($this->escalation->priority))
            ->line("**Expires**: {$this->escalation->expires_at->format('M j, Y g:i A')}")
            ->action('Review & Approve', url("/admin/escalations/{$this->escalation->id}"))
            ->line('⚠️ This escalation will auto-reject if not approved within 48 hours.')
            ->line('Dual approval from both FC and GM is required for this amount.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'threshold_violation',
            'escalation_id' => $this->escalation->id,
            'amount' => $this->escalation->amount_requested,
            'overage' => $this->escalation->overage_amount,
            'priority' => $this->escalation->priority,
            'expires_at' => $this->escalation->expires_at
        ];
    }
}

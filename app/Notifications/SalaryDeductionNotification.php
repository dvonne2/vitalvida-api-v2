<?php

namespace App\Notifications;

use App\Models\SalaryDeduction;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SalaryDeductionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $deduction;
    protected $type;

    /**
     * Create a new notification instance.
     */
    public function __construct(SalaryDeduction $deduction, string $type)
    {
        $this->deduction = $deduction;
        $this->type = $type;
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
        return match($this->type) {
            'unauthorized_payment' => $this->unauthorizedPaymentMail($notifiable),
            'rejected_escalation' => $this->rejectedEscalationMail($notifiable),
            'expired_escalation' => $this->expiredEscalationMail($notifiable),
            'processed' => $this->processedMail($notifiable),
            'cancelled' => $this->cancelledMail($notifiable),
            default => $this->defaultMail($notifiable)
        };
    }

    /**
     * Get the array representation of the notification.
     */
    public function toArray($notifiable): array
    {
        return [
            'type' => 'salary_deduction',
            'deduction_id' => $this->deduction->id,
            'amount' => $this->deduction->amount,
            'reason' => $this->deduction->reason,
            'status' => $this->deduction->status,
            'deduction_date' => $this->deduction->deduction_date,
            'processed_date' => $this->deduction->processed_date,
            'violation_id' => $this->deduction->violation_id,
            'notification_type' => $this->type,
            'message' => $this->getNotificationMessage()
        ];
    }

    /**
     * Generate unauthorized payment mail
     */
    private function unauthorizedPaymentMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⚠️ SALARY DEDUCTION SCHEDULED: Unauthorized Payment')
            ->greeting("Dear {$notifiable->name},")
            ->line("A salary deduction has been scheduled due to an unauthorized payment that exceeded business thresholds.")
            ->line("**Deduction Details:**")
            ->line("• Deduction Amount: ₦" . number_format($this->deduction->amount, 2))
            ->line("• Reason: Unauthorized payment exceeding threshold limits")
            ->line("• Scheduled Date: {$this->deduction->deduction_date->format('M j, Y')}")
            ->line("• Status: {$this->deduction->status}")
            ->line('')
            ->line("**Violation Details:**")
            ->line("• Original Amount: ₦" . number_format($this->getViolationProperty('amount', 0), 2))
            ->line("• Threshold Limit: ₦" . number_format($this->getViolationProperty('threshold_limit', 0), 2))
            ->line("• Overage Amount: ₦" . number_format($this->getViolationProperty('overage_amount', 0), 2))
            ->line("• Cost Type: " . $this->getViolationProperty('cost_type', 'N/A'))
            ->line('')
            ->line("**What This Means:**")
            ->line("• You processed a payment that exceeded approved limits")
            ->line("• The full overage amount will be deducted from your salary")
            ->line("• This deduction is based on company policy enforcement")
            ->line("• Future violations may result in additional disciplinary action")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Review the company threshold policies")
            ->line("2. Ensure all future payments comply with limits")
            ->line("3. Contact your supervisor if you need clarification")
            ->line("4. Use the escalation process for amounts exceeding thresholds")
            ->action('View Deduction Details', url("/employee/deductions/{$this->deduction->id}"))
            ->line("**Important:** This deduction is final and will be processed on the scheduled date. Please ensure compliance with all expense policies going forward.");
    }

    /**
     * Generate rejected escalation mail
     */
    private function rejectedEscalationMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('💰 SALARY DEDUCTION SCHEDULED: Rejected Escalation')
            ->greeting("Dear {$notifiable->name},")
            ->line("A salary deduction has been scheduled following the rejection of your expense escalation.")
            ->line("**Deduction Details:**")
            ->line("• Deduction Amount: ₦" . number_format($this->deduction->amount, 2))
            ->line("• Reason: Escalation rejected by required approvers")
            ->line("• Scheduled Date: {$this->deduction->deduction_date->format('M j, Y')}")
            ->line("• Status: {$this->deduction->status}")
            ->line('')
            ->line("**Escalation Details:**")
            ->line("• Requested Amount: ₦" . number_format($this->getViolationProperty('amount', 0), 2))
            ->line("• Threshold Limit: ₦" . number_format($this->getViolationProperty('threshold_limit', 0), 2))
            ->line("• Overage Amount: ₦" . number_format($this->getViolationProperty('overage_amount', 0), 2))
            ->line("• Cost Type: " . $this->getViolationProperty('cost_type', 'N/A'))
            ->line('')
            ->line("**What This Means:**")
            ->line("• Your escalation was reviewed and rejected by approvers")
            ->line("• 50% of the overage amount will be deducted from your salary")
            ->line("• This serves as a penalty for attempting unauthorized expenses")
            ->line("• The deduction is less than unauthorized payment penalties")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Review the rejection reasons from your escalation")
            ->line("2. Understand why the expense was not approved")
            ->line("3. Follow proper procedures for future expenses")
            ->line("4. Seek guidance on expense policy compliance")
            ->action('View Deduction Details', url("/employee/deductions/{$this->deduction->id}"))
            ->line("**Important:** This deduction reflects the penalty for attempting expenses outside approved limits. Please ensure all future expenses comply with company policies.");
    }

    /**
     * Generate expired escalation mail
     */
    private function expiredEscalationMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('⏰ SALARY DEDUCTION SCHEDULED: Expired Escalation')
            ->greeting("Dear {$notifiable->name},")
            ->line("A salary deduction has been scheduled because your expense escalation expired without receiving approval.")
            ->line("**Deduction Details:**")
            ->line("• Deduction Amount: ₦" . number_format($this->deduction->amount, 2))
            ->line("• Reason: Escalation expired without approval")
            ->line("• Scheduled Date: {$this->deduction->deduction_date->format('M j, Y')}")
            ->line("• Status: {$this->deduction->status}")
            ->line('')
            ->line("**Escalation Details:**")
            ->line("• Requested Amount: ₦" . number_format($this->getViolationProperty('amount', 0), 2))
            ->line("• Threshold Limit: ₦" . number_format($this->getViolationProperty('threshold_limit', 0), 2))
            ->line("• Overage Amount: ₦" . number_format($this->getViolationProperty('overage_amount', 0), 2))
            ->line("• Cost Type: " . $this->getViolationProperty('cost_type', 'N/A'))
            ->line('')
            ->line("**What This Means:**")
            ->line("• Your escalation was not approved within the required timeframe")
            ->line("• 75% of the overage amount will be deducted from your salary")
            ->line("• This is a higher penalty than rejected escalations")
            ->line("• The system automatically processes expired escalations")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Monitor all future escalations to ensure timely approval")
            ->line("2. Follow up with approvers if escalations are pending")
            ->line("3. Submit escalations with adequate time for review")
            ->line("4. Ensure all supporting documentation is complete")
            ->action('View Deduction Details', url("/employee/deductions/{$this->deduction->id}"))
            ->line("**Important:** This deduction is higher than other penalties because expired escalations create administrative burden. Please ensure timely processing of all escalations.");
    }

    /**
     * Generate processed mail
     */
    private function processedMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('✅ SALARY DEDUCTION PROCESSED: Payment Completed')
            ->greeting("Dear {$notifiable->name},")
            ->line("Your scheduled salary deduction has been processed and applied to your salary.")
            ->line("**Deduction Details:**")
            ->line("• Deduction Amount: ₦" . number_format($this->deduction->amount, 2))
            ->line("• Reason: {$this->deduction->reason}")
            ->line("• Processed Date: {$this->deduction->processed_date->format('M j, Y')}")
            ->line("• Status: {$this->deduction->status}")
            ->line('')
            ->line("**What This Means:**")
            ->line("• The deduction has been applied to your current salary")
            ->line("• You will see this reflected in your next payslip")
            ->line("• The penalty for the violation has been fully processed")
            ->line("• No further action is required from you")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Review your payslip to confirm the deduction")
            ->line("2. Contact HR if you have questions about the deduction")
            ->line("3. Ensure future compliance with expense policies")
            ->line("4. Use proper escalation procedures for threshold exceptions")
            ->action('View Deduction Details', url("/employee/deductions/{$this->deduction->id}"))
            ->line("**Important:** This deduction is final and has been processed. Please ensure all future expenses comply with company policies to avoid similar penalties.");
    }

    /**
     * Generate cancelled mail
     */
    private function cancelledMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 SALARY DEDUCTION CANCELLED: Good News!')
            ->greeting("Dear {$notifiable->name},")
            ->line("Great news! Your scheduled salary deduction has been cancelled by an administrator.")
            ->line("**Deduction Details:**")
            ->line("• Original Amount: ₦" . number_format($this->deduction->amount, 2))
            ->line("• Reason: {$this->deduction->reason}")
            ->line("• Cancelled Date: " . now()->format('M j, Y'))
            ->line("• Status: {$this->deduction->status}")
            ->line('')
            ->line("**What This Means:**")
            ->line("• The deduction will NOT be applied to your salary")
            ->line("• You will not see this amount deducted from your payslip")
            ->line("• An administrator has reviewed and cancelled the penalty")
            ->line("• This is typically done for valid business reasons")
            ->line('')
            ->line("**Next Steps:**")
            ->line("1. Continue following proper expense procedures")
            ->line("2. Ensure all future payments comply with thresholds")
            ->line("3. Use escalation processes when needed")
            ->line("4. Contact your supervisor if you need clarification")
            ->action('View Deduction Details', url("/employee/deductions/{$this->deduction->id}"))
            ->line("**Important:** While this deduction has been cancelled, please ensure all future expenses comply with company policies to avoid similar situations.");
    }

    /**
     * Generate default mail
     */
    private function defaultMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('📋 SALARY DEDUCTION UPDATE: Status Change')
            ->greeting("Dear {$notifiable->name},")
            ->line("There has been an update to your salary deduction status.")
            ->line("**Deduction Details:**")
            ->line("• Amount: ₦" . number_format($this->deduction->amount, 2))
            ->line("• Reason: {$this->deduction->reason}")
            ->line("• Status: {$this->deduction->status}")
            ->line("• Scheduled Date: {$this->deduction->deduction_date->format('M j, Y')}")
            ->action('View Deduction Details', url("/employee/deductions/{$this->deduction->id}"))
            ->line('Please review the deduction details for more information.');
    }

    /**
     * Get violation property safely
     */
    private function getViolationProperty(string $property, $default = null)
    {
        return $this->deduction->violation ? $this->deduction->violation->$property : $default;
    }

    /**
     * Get notification message based on type
     */
    private function getNotificationMessage(): string
    {
        return match($this->type) {
            'unauthorized_payment' => 'Salary deduction scheduled for unauthorized payment',
            'rejected_escalation' => 'Salary deduction scheduled for rejected escalation',
            'expired_escalation' => 'Salary deduction scheduled for expired escalation',
            'processed' => 'Salary deduction has been processed',
            'cancelled' => 'Salary deduction has been cancelled',
            default => 'Salary deduction status updated'
        };
    }
}

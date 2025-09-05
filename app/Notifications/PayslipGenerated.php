<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class PayslipGenerated extends Notification
{
    public function __construct(
        private $payslip
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Payslip is Ready')
            ->greeting("Hello {$this->payslip->employee_name},")
            ->line("Your payslip for {$this->payslip->pay_period_month->format('F Y')} is now available.")
            ->line("**Summary:**")
            ->line("- Gross Pay: ₦" . number_format($this->payslip->gross_pay, 2))
            ->line("- Total Deductions: ₦" . number_format($this->payslip->total_deductions, 2))
            ->line("- Net Pay: ₦" . number_format($this->payslip->net_pay, 2))
            ->action('View Payslip', url("/employee/payslips/{$this->payslip->id}"))
            ->line('You can download your payslip from the employee portal.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'payslip_generated',
            'payslip_id' => $this->payslip->id,
            'month' => $this->payslip->pay_period_month->format('Y-m'),
            'net_pay' => $this->payslip->net_pay
        ];
    }
} 
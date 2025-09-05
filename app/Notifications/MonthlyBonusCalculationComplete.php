<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class MonthlyBonusCalculationComplete extends Notification
{
    public function __construct(
        private array $results
    ) {}

    public function via($notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail($notifiable): MailMessage
    {
        $summary = $this->results['summary'];
        
        return (new MailMessage)
            ->subject('Monthly Bonus Calculation Complete')
            ->greeting("Hello {$notifiable->name},")
            ->line("The monthly bonus calculation has been completed.")
            ->line("**Summary:**")
            ->line("- Employees Processed: {$summary['total_employees']}")
            ->line("- Total Bonus Amount: ₦" . number_format($summary['total_amount'], 2))
            ->line("- Requires Approval: " . ($summary['requires_approval'] ? 'Yes' : 'No'))
            ->action('Review Bonus Calculations', url('/admin/bonuses'))
            ->line('Please review and approve bonuses that require management approval.');
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'bonus_calculation_complete',
            'month' => $this->results['month']->format('Y-m'),
            'summary' => $this->results['summary']
        ];
    }
} 
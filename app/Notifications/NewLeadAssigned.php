<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Lead;

class NewLeadAssigned extends Notification implements ShouldQueue
{
    use Queueable;

    public $lead;

    /**
     * Create a new notification instance.
     */
    public function __construct(Lead $lead)
    {
        $this->lead = $lead;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $formName = $this->lead->form ? $this->lead->form->name : 'Direct Entry';
        $totalValue = number_format($this->lead->total_value);

        return (new MailMessage)
            ->subject('New Lead Assigned - ' . $this->lead->customer_name)
            ->greeting('Hello ' . $notifiable->name . '!')
            ->line('A new lead has been assigned to you.')
            ->line('**Customer:** ' . $this->lead->customer_name)
            ->line('**Phone:** ' . $this->lead->formatted_phone)
            ->line('**Product:** ' . $this->lead->product)
            ->line('**Total Value:** ₦' . $totalValue)
            ->line('**Form Source:** ' . $formName)
            ->action('View Lead Details', url('/telesales/leads/' . $this->lead->id))
            ->line('Please contact the customer as soon as possible.')
            ->salutation('Best regards, VitalVida Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'lead_id' => $this->lead->id,
            'customer_name' => $this->lead->customer_name,
            'customer_phone' => $this->lead->customer_phone,
            'product' => $this->lead->product,
            'total_value' => $this->lead->total_value,
            'form_name' => $this->lead->form ? $this->lead->form->name : 'Direct Entry',
            'message' => 'New lead assigned: ' . $this->lead->customer_name,
            'action_url' => '/telesales/leads/' . $this->lead->id,
        ];
    }
} 
<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Order;

class OrderAssignedNotification extends Notification
{
    use Queueable;

    private $order;
    
    /**
     * Create a new notification instance.
     */
    public function __construct($order)
    {
        $this->order = $order;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $items = [];
        foreach ($this->order->product_details as $item => $quantity) {
            $items[] = "{$quantity}x " . ucfirst($item);
        }
        
        return (new MailMessage)
                    ->subject('New Order Assigned')
                    ->line("You've been assigned a new delivery order.")
                    ->line("Customer: {$this->order->customer_name}")
                    ->line("Location: {$this->order->customer_location}")
                    ->line("Products: " . implode(', ', $items))
                    ->action('View Order Details', url('/delivery-agent/orders/' . $this->order->id))
                    ->line('Please ensure you have the required stock before delivery.');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $items = [];
        foreach ($this->order->product_details as $item => $quantity) {
            $items[] = "{$quantity}x " . ucfirst($item);
        }
        
        return [
            'title' => 'New Order Assigned',
            'message' => "You've been assigned a new delivery: " . implode(', ', $items) 
                       . " for {$this->order->customer_name} in {$this->order->customer_location}.",
            'order_id' => $this->order->id,
            'customer_name' => $this->order->customer_name,
            'customer_location' => $this->order->customer_location,
            'customer_phone' => $this->order->customer_phone,
            'product_details' => $this->order->product_details,
            'total_amount' => $this->order->total_amount,
            'type' => 'order_assigned',
            'created_at' => now()->toISOString()
        ];
    }
} 
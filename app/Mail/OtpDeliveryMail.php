<?php

namespace App\Mail;

use App\Models\Order;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpDeliveryMail extends Mailable
{
    use Queueable, SerializesModels;

    public Order $order;
    public string $otp;

    public function __construct(Order $order, string $otp)
    {
        $this->order = $order;
        $this->otp = $otp;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🎉 Payment Confirmed - Order {$this->order->order_number} Ready for Delivery!"
        );
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.otp-delivery',
            with: [
                'order' => $this->order,
                'otp' => $this->otp,
                'customerName' => $this->order->customer_name,
                'orderNumber' => $this->order->order_number
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

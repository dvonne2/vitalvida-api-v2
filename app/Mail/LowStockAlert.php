<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LowStockAlert extends Mailable
{
    use Queueable, SerializesModels;

    public $lowStockProducts;
    public $totalProducts;
    public $state;

    public function __construct($lowStockProducts, $totalProducts = null, $state = 'All States')
    {
        $this->lowStockProducts = $lowStockProducts;
        $this->totalProducts = $totalProducts ?? count($lowStockProducts);
        $this->state = $state;
    }

    public function envelope(): Envelope
    {
        $count = count($this->lowStockProducts);
        $subject = "⚠️ VitalVida: {$count} Products Low Stock - {$this->state}";
        
        return new Envelope(subject: $subject);
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.low-stock-alert',
            with: [
                'lowStockProducts' => $this->lowStockProducts,
                'totalProducts' => $this->totalProducts,
                'state' => $this->state,
                'alertCount' => count($this->lowStockProducts)
            ]
        );
    }

    public function attachments(): array
    {
        return [];
    }
}

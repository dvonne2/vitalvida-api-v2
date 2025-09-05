<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Receipt extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'receipt_number',
        'generated_at',
        'printed_at',
        'email_sent_at',
        'email_address',
        'content',
        'format'
    ];

    protected $casts = [
        'generated_at' => 'datetime',
        'printed_at' => 'datetime',
        'email_sent_at' => 'datetime'
    ];

    // Relationships
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    // Business Logic Methods
    public function generateReceiptNumber(): string
    {
        $lastReceipt = self::latest('id')->first();
        $nextId = $lastReceipt ? $lastReceipt->id + 1 : 1;
        return 'RCP-' . date('Y') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function generateContent(): string
    {
        $sale = $this->sale;
        $content = "RECEIPT\n";
        $content .= "Receipt #: {$this->receipt_number}\n";
        $content .= "Sale #: {$sale->sale_number}\n";
        $content .= "Date: {$sale->date}\n";
        $content .= "Customer: {$sale->customer->name}\n\n";
        
        $content .= "ITEMS:\n";
        foreach ($sale->items as $item) {
            $content .= "{$item->quantity}x {$item->item->name} @ ₦{$item->unit_price} = ₦{$item->total}\n";
        }
        
        $content .= "\n";
        $content .= "Subtotal: ₦{$sale->subtotal}\n";
        if ($sale->tax_amount > 0) {
            $content .= "Tax: ₦{$sale->tax_amount}\n";
        }
        if ($sale->discount_amount > 0) {
            $content .= "Discount: -₦{$sale->discount_amount}\n";
        }
        $content .= "TOTAL: ₦{$sale->total}\n";
        $content .= "Payment Method: {$sale->payment_method}\n";
        $content .= "Status: {$sale->payment_status}\n";
        
        return $content;
    }

    public function markAsPrinted(): void
    {
        $this->update(['printed_at' => now()]);
    }

    public function markAsEmailSent(): void
    {
        $this->update(['email_sent_at' => now()]);
    }

    public function isPrinted(): bool
    {
        return !is_null($this->printed_at);
    }

    public function isEmailSent(): bool
    {
        return !is_null($this->email_sent_at);
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($receipt) {
            if (empty($receipt->receipt_number)) {
                $receipt->receipt_number = $receipt->generateReceiptNumber();
            }
            if (empty($receipt->generated_at)) {
                $receipt->generated_at = now();
            }
            if (empty($receipt->content)) {
                $receipt->content = $receipt->generateContent();
            }
        });
    }
} 
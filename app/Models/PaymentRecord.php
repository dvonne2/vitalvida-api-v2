<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PaymentRecord extends Model
{
    protected $fillable = [
        'order_id', 'customer_payment_received', 'da_name', 'da_phone',
        'delivery_amount', 'payment_method', 'verification_status', 'zoho_status',
        'im_says', 'da_says', 'zoho_shows', 'processed_by', 'processed_at',
        'receipt_uploaded', 'receipt_path'
    ];

    protected $casts = [
        'customer_payment_received' => 'boolean',
        'delivery_amount' => 'decimal:2',
        'processed_at' => 'datetime',
        'receipt_uploaded' => 'boolean'
    ];

    public function processor()
    {
        return $this->belongsTo(Accountant::class, 'processed_by');
    }

    public function scopeThreeWayMatch($query)
    {
        return $query->where('verification_status', '3_way_match');
    }

    public function scopeMismatched($query)
    {
        return $query->where('verification_status', 'mismatch');
    }

    public function scopeReadyForPayment($query)
    {
        return $query->where('customer_payment_received', true)
                    ->where('verification_status', '3_way_match');
    }

    public function scopePending($query)
    {
        return $query->where('verification_status', 'pending');
    }

    public function scopeThisWeek($query)
    {
        return $query->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]);
    }

    public function processThreeWayVerification()
    {
        // Simulate 3-way match verification
        $imAmount = $this->parseAmount($this->im_says);
        $daAmount = $this->parseAmount($this->da_says);
        $zohoAmount = $this->parseAmount($this->zoho_shows);

        if ($imAmount === $daAmount && $daAmount === $zohoAmount) {
            $this->update([
                'verification_status' => '3_way_match',
                'processed_at' => now()
            ]);
            return true;
        } else {
            $this->update([
                'verification_status' => 'mismatch',
                'processed_at' => now()
            ]);
            
            // Create strike for mismatch
            if ($this->processor) {
                $this->processor->addStrike(
                    'payment_mismatch',
                    "Payment mismatch on Order {$this->order_id}: IM={$this->im_says}, DA={$this->da_says}, Zoho={$this->zoho_shows}",
                    20000, // ₦20,000 penalty
                    $this->order_id
                );
            }
            
            return false;
        }
    }

    private function parseAmount($amountString)
    {
        // Extract numeric value from strings like "₦ shampoos", "8 shampoos"
        preg_match('/\d+/', $amountString, $matches);
        return isset($matches[0]) ? (int)$matches[0] : 0;
    }

    public function getReceiptUrl()
    {
        return $this->receipt_path ? asset('storage/' . $this->receipt_path) : null;
    }

    public function uploadReceipt($file)
    {
        $fileName = time() . '_' . $this->order_id . '_receipt.' . $file->getClientOriginalExtension();
        $filePath = $file->storeAs('receipts/' . $this->order_id, $fileName, 'public');
        
        $this->update([
            'receipt_uploaded' => true,
            'receipt_path' => $filePath
        ]);

        return $filePath;
    }

    public function getVerificationStatusColor()
    {
        return match($this->verification_status) {
            '3_way_match' => 'green',
            'mismatch' => 'red',
            'confirmed' => 'blue',
            default => 'yellow'
        };
    }

    public function getVerificationStatusText()
    {
        return match($this->verification_status) {
            '3_way_match' => '3-Way Match',
            'mismatch' => 'Mismatch',
            'confirmed' => 'Confirmed',
            default => 'Pending'
        };
    }

    public function getPaymentMethodIcon()
    {
        return match($this->payment_method) {
            'cash' => '💵',
            'transfer' => '🏦',
            'pos' => '💳',
            'online' => '🌐',
            default => '💰'
        };
    }

    public function isProcessed()
    {
        return $this->verification_status !== 'pending';
    }

    public function hasReceipt()
    {
        return $this->receipt_uploaded && $this->receipt_path;
    }
} 
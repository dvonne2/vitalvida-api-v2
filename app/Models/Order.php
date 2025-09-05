<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_number',
        'zoho_order_id',
        'customer_id',
        'customer_name',
        'customer_phone',
        'customer_email',
        'delivery_address',
        'items',
        'total_amount',
        'status',
        'payment_status',
        'payment_reference',
        'assigned_da_id',
        'assigned_at',
        'delivery_date',
        'delivery_otp',
        'otp_verified',
        'otp_verified_at',
        'delivery_notes'
    ];

    protected $casts = [
        'items' => 'array',
        'total_amount' => 'decimal:2',
        'otp_verified' => 'boolean',
        'assigned_at' => 'datetime',
        'delivery_date' => 'datetime',
        'otp_verified_at' => 'datetime'
    ];

    // Helper methods for your Moniepoint system
    public function isPaid()
    {
        return $this->payment_status === 'paid';
    }

    public function isDelivered()
    {
        return $this->status === 'delivered';
    }

    public function hasValidOtp()
    {
        return !empty($this->delivery_otp) && !$this->otp_verified;
    }

    public function canBeDelivered()
    {
        return $this->isPaid() && $this->hasValidOtp();
    }
}

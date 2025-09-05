<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReferralReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'referrer_id',
        'referee_order_id',
        'cash_naira',
        'free_delivery',
        'status',
        'expires_at'
    ];

    protected $casts = [
        'free_delivery' => 'boolean',
        'expires_at' => 'datetime'
    ];

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'referrer_id');
    }

    public function refereeOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'referee_order_id');
    }
}

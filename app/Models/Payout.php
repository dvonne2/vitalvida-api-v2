<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payout extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'order_id',
        'amount',
        'status'
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    /**
     * Get the order that owns the payout
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get formatted amount in Naira
     */
    public function getAmountInNairaAttribute()
    {
        return '₦' . number_format($this->amount, 2);
    }

    /**
     * Scope to filter by status
     */
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    /**
     * Scope to get pending payouts
     */
    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    /**
     * Scope to get paid payouts
     */
    public function scopePaid($query)
    {
        return $query->where('status', 'paid');
    }
}

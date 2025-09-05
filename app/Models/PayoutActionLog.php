<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PayoutActionLog extends Model

    /**
     * Get the user who performed the action
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }
{
    use HasFactory;

    protected $fillable = [
        'payout_id',
        'action',
        'performed_by',
        'role',
        'note'
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime'
    ];

    /**
     * Get the payout that owns the action log
     */
    public function payout()
    {
        return $this->belongsTo(Payout::class);
    }

    /**
     * Get the user who performed the action
     */
    public function performer()
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    /**
     * Scope to filter by action type
     */
    public function scopeByAction($query, $action)
    {
        return $query->where('action', $action);
    }

    /**
     * Scope to filter by payout
     */
    public function scopeForPayout($query, $payoutId)
    {
        return $query->where('payout_id', $payoutId);
    }

    /**
     * Get formatted timestamp for display
     */
    public function getFormattedTimestampAttribute()
    {
        return $this->created_at->format('M d, Y H:i:s');
    }
}

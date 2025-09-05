<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'item_id',
        'quantity_before',
        'quantity_after',
        'change_quantity',
        'reason',
        'reference_type',
        'reference_id',
        'user_id',
        'location',
        'notes'
    ];

    protected $casts = [
        'quantity_before' => 'integer',
        'quantity_after' => 'integer',
        'change_quantity' => 'integer',
        'reference_id' => 'integer',
        'user_id' => 'integer'
    ];

    // Relationships
    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reference()
    {
        return $this->morphTo();
    }

    // Scopes
    public function scopeByReason($query, $reason)
    {
        return $query->where('reason', $reason);
    }

    public function scopeByLocation($query, $location)
    {
        return $query->where('location', $location);
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('created_at', [$startDate, $endDate]);
    }

    public function scopeIncreases($query)
    {
        return $query->where('change_quantity', '>', 0);
    }

    public function scopeDecreases($query)
    {
        return $query->where('change_quantity', '<', 0);
    }

    // Business Logic Methods
    public function getChangeTypeAttribute(): string
    {
        return $this->change_quantity > 0 ? 'increase' : 'decrease';
    }

    public function getChangePercentageAttribute(): float
    {
        if ($this->quantity_before == 0) return 0;
        return round(($this->change_quantity / $this->quantity_before) * 100, 2);
    }

    public function getFormattedReasonAttribute(): string
    {
        return ucfirst(str_replace('_', ' ', $this->reason));
    }

    public function getValueChangeAttribute(): float
    {
        // This would need to be calculated based on item cost
        return $this->change_quantity * ($this->item->unit_price ?? 0);
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class InventoryCountItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'inventory_count_id',
        'item_id',
        'expected_quantity',
        'actual_quantity',
        'variance',
        'notes'
    ];

    protected $casts = [
        'expected_quantity' => 'integer',
        'actual_quantity' => 'integer',
        'variance' => 'integer'
    ];

    // Relationships
    public function inventoryCount()
    {
        return $this->belongsTo(InventoryCount::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Business Logic Methods
    public function calculateVariance(): int
    {
        $this->variance = $this->actual_quantity - $this->expected_quantity;
        return $this->variance;
    }

    public function hasDiscrepancy(): bool
    {
        return $this->variance != 0;
    }

    public function isOverstock(): bool
    {
        return $this->variance > 0;
    }

    public function isUnderstock(): bool
    {
        return $this->variance < 0;
    }

    public function getVariancePercentageAttribute(): float
    {
        if ($this->expected_quantity == 0) return 0;
        return round(($this->variance / $this->expected_quantity) * 100, 2);
    }

    public function getDiscrepancyTypeAttribute(): string
    {
        if ($this->variance == 0) return 'none';
        return $this->variance > 0 ? 'overstock' : 'understock';
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if (isset($item->actual_quantity) && isset($item->expected_quantity)) {
                $item->calculateVariance();
            }
        });
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ProductionItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'production_id',
        'finished_item_id',
        'raw_item_id',
        'quantity_used',
        'quantity_produced',
        'unit_cost',
        'total_cost',
        'notes'
    ];

    protected $casts = [
        'quantity_used' => 'integer',
        'quantity_produced' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2'
    ];

    // Relationships
    public function production()
    {
        return $this->belongsTo(Production::class);
    }

    public function finishedItem()
    {
        return $this->belongsTo(Item::class, 'finished_item_id');
    }

    public function rawItem()
    {
        return $this->belongsTo(Item::class, 'raw_item_id');
    }

    // Business Logic Methods
    public function calculateTotalCost(): float
    {
        return $this->quantity_used * $this->unit_cost;
    }

    public function getYieldPercentageAttribute(): float
    {
        if ($this->quantity_used == 0) return 0;
        return round(($this->quantity_produced / $this->quantity_used) * 100, 2);
    }

    public function isRawMaterial(): bool
    {
        return !is_null($this->raw_item_id);
    }

    public function isFinishedGood(): bool
    {
        return !is_null($this->finished_item_id);
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if (empty($item->total_cost)) {
                $item->total_cost = $item->calculateTotalCost();
            }
        });
    }
} 
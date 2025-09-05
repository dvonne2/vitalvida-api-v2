<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TransferOrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'transfer_order_id',
        'item_id',
        'quantity',
        'unit_cost',
        'total_cost',
        'description',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_cost' => 'decimal:2',
        'total_cost' => 'decimal:2'
    ];

    // Relationships
    public function transferOrder()
    {
        return $this->belongsTo(TransferOrder::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    // Business Logic Methods
    public function calculateTotalCost(): float
    {
        return $this->quantity * $this->unit_cost;
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
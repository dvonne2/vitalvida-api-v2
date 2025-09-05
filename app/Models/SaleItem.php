<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SaleItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'sale_id',
        'item_id',
        'quantity',
        'unit_price',
        'discount',
        'total',
        'modifier_id',
        'notes'
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount' => 'decimal:2',
        'total' => 'decimal:2'
    ];

    // Relationships
    public function sale()
    {
        return $this->belongsTo(Sale::class);
    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function modifier()
    {
        return $this->belongsTo(Modifier::class);
    }

    // Business Logic Methods
    public function calculateTotal(): float
    {
        $subtotal = $this->quantity * $this->unit_price;
        $discount = $this->discount ?? 0;
        return $subtotal - $discount;
    }

    public function getDiscountPercentageAttribute(): float
    {
        if ($this->unit_price == 0) return 0;
        return round(($this->discount / ($this->quantity * $this->unit_price)) * 100, 2);
    }

    public function getProfitAttribute(): float
    {
        $costPrice = $this->item->cost_price ?? 0;
        return ($this->unit_price - $costPrice) * $this->quantity;
    }

    public function getProfitMarginAttribute(): float
    {
        if ($this->unit_price == 0) return 0;
        $costPrice = $this->item->cost_price ?? 0;
        return round((($this->unit_price - $costPrice) / $this->unit_price) * 100, 2);
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($item) {
            if (empty($item->total)) {
                $item->total = $item->calculateTotal();
            }
        });
    }
} 
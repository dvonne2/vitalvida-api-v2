<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Discount extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'value',
        'start_date',
        'end_date',
        'conditions',
        'usage_count',
        'max_usage',
        'is_active',
        'description'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'start_date' => 'date',
        'end_date' => 'date',
        'conditions' => 'array',
        'usage_count' => 'integer',
        'max_usage' => 'integer',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function sales()
    {
        return $this->hasMany(Sale::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeByType($query, $type)
    {
        return $query->where('type', $type);
    }

    public function scopeValid($query)
    {
        $now = now()->toDateString();
        return $query->where('start_date', '<=', $now)
                    ->where('end_date', '>=', $now)
                    ->where('is_active', true);
    }

    public function scopeExpired($query)
    {
        return $query->where('end_date', '<', now()->toDateString());
    }

    // Business Logic Methods
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'percentage' => 'Percentage',
            'fixed' => 'Fixed Amount',
            'free_shipping' => 'Free Shipping',
            'buy_one_get_one' => 'Buy One Get One',
            default => ucfirst($this->type)
        };
    }

    public function calculateDiscount(float $subtotal): float
    {
        return match($this->type) {
            'percentage' => $subtotal * ($this->value / 100),
            'fixed' => min($this->value, $subtotal),
            'free_shipping' => 0, // Handled separately
            'buy_one_get_one' => 0, // Handled separately
            default => 0
        };
    }

    public function isActive(): bool
    {
        $now = now()->toDateString();
        return $this->is_active && 
               $this->start_date <= $now && 
               $this->end_date >= $now &&
               ($this->max_usage === null || $this->usage_count < $this->max_usage);
    }

    public function incrementUsage(): void
    {
        $this->increment('usage_count');
    }

    public function getUsagePercentageAttribute(): float
    {
        if ($this->max_usage === null || $this->max_usage == 0) return 0;
        return round(($this->usage_count / $this->max_usage) * 100, 2);
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->sales()->sum('total');
    }

    public function getDiscountValueAttribute(): float
    {
        return $this->sales()->sum('discount_amount');
    }
} 
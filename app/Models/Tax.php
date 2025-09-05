<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Tax extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'rate',
        'type',
        'applicable_categories',
        'is_active',
        'description'
    ];

    protected $casts = [
        'rate' => 'decimal:2',
        'applicable_categories' => 'array',
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

    public function scopeByCategory($query, $categoryId)
    {
        return $query->whereJsonContains('applicable_categories', $categoryId);
    }

    // Business Logic Methods
    public function getTypeLabelAttribute(): string
    {
        return match($this->type) {
            'percentage' => 'Percentage',
            'fixed' => 'Fixed Amount',
            'tiered' => 'Tiered',
            default => ucfirst($this->type)
        };
    }

    public function calculateTax(float $subtotal): float
    {
        return match($this->type) {
            'percentage' => $subtotal * ($this->rate / 100),
            'fixed' => $this->rate,
            'tiered' => $this->calculateTieredTax($subtotal),
            default => 0
        };
    }

    private function calculateTieredTax(float $subtotal): float
    {
        // Example tiered tax calculation
        if ($subtotal <= 1000) {
            return $subtotal * 0.05; // 5% for first 1000
        } elseif ($subtotal <= 5000) {
            return 50 + ($subtotal - 1000) * 0.10; // 10% for next 4000
        } else {
            return 450 + ($subtotal - 5000) * 0.15; // 15% for rest
        }
    }

    public function isApplicableToCategory(int $categoryId): bool
    {
        return in_array($categoryId, $this->applicable_categories ?? []);
    }

    public function getTotalCollectedAttribute(): float
    {
        return $this->sales()->sum('tax_amount');
    }

    public function getUsageCountAttribute(): int
    {
        return $this->sales()->count();
    }
} 
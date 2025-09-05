<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Modifier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'type',
        'value',
        'applicable_categories',
        'is_active',
        'description'
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'applicable_categories' => 'array',
        'is_active' => 'boolean',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function saleItems()
    {
        return $this->hasMany(SaleItem::class);
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
            'multiplier' => 'Multiplier',
            default => ucfirst($this->type)
        };
    }

    public function calculateModification(float $basePrice): float
    {
        return match($this->type) {
            'percentage' => $basePrice * ($this->value / 100),
            'fixed' => $this->value,
            'multiplier' => $basePrice * $this->value,
            default => 0
        };
    }

    public function isApplicableToCategory(int $categoryId): bool
    {
        return in_array($categoryId, $this->applicable_categories ?? []);
    }

    public function getUsageCountAttribute(): int
    {
        return $this->saleItems()->count();
    }

    public function getTotalRevenueAttribute(): float
    {
        return $this->saleItems()->sum('total');
    }
} 
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DaInventory extends Model
{
    use HasFactory;

    protected $fillable = [
        'da_id',
        'product_type',
        'quantity',
        'last_updated',
        'days_stagnant',
        'min_stock_level',
        'max_stock_level',
        'reorder_point',
        'last_restock_date',
        'notes',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'days_stagnant' => 'integer',
        'min_stock_level' => 'integer',
        'max_stock_level' => 'integer',
        'reorder_point' => 'integer',
        'last_updated' => 'datetime',
        'last_restock_date' => 'datetime',
    ];

    // Relationships
    public function deliveryAgent(): BelongsTo
    {
        return $this->belongsTo(DeliveryAgent::class, 'da_id');
    }

    // Scopes
    public function scopeByProductType($query, $productType)
    {
        return $query->where('product_type', $productType);
    }

    public function scopeLowStock($query)
    {
        return $query->whereRaw('quantity <= min_stock_level');
    }

    public function scopeStagnant($query, $days = 5)
    {
        return $query->where('days_stagnant', '>=', $days);
    }

    public function scopeNeedsRestock($query)
    {
        return $query->whereRaw('quantity <= reorder_point');
    }

    public function scopeByDa($query, $daId)
    {
        return $query->where('da_id', $daId);
    }

    // Accessors
    public function getStockLevelAttribute(): string
    {
        if ($this->quantity <= $this->min_stock_level) return 'low';
        if ($this->quantity <= $this->reorder_point) return 'medium';
        return 'high';
    }

    public function getStockLevelColorAttribute(): string
    {
        return match($this->stock_level) {
            'low' => 'red',
            'medium' => 'yellow',
            'high' => 'green',
            default => 'gray'
        };
    }

    public function getStockPercentageAttribute(): float
    {
        if ($this->max_stock_level <= 0) return 0;
        return round(($this->quantity / $this->max_stock_level) * 100, 2);
    }

    public function getIsStagnantAttribute(): bool
    {
        return $this->days_stagnant >= 5;
    }

    public function getNeedsRestockAttribute(): bool
    {
        return $this->quantity <= $this->reorder_point;
    }

    public function getProductNameAttribute(): string
    {
        return match($this->product_type) {
            'shampoo' => 'Vitalvida Shampoo',
            'pomade' => 'Vitalvida Pomade',
            'conditioner' => 'Vitalvida Conditioner',
            default => ucfirst($this->product_type)
        };
    }

    public function getFormattedQuantityAttribute(): string
    {
        return $this->quantity . ' units';
    }

    // Business Methods
    public function updateQuantity(int $newQuantity): void
    {
        $oldQuantity = $this->quantity;
        $this->update([
            'quantity' => $newQuantity,
            'last_updated' => now(),
        ]);

        // Log inventory change
        if ($oldQuantity !== $newQuantity) {
            $this->logInventoryChange($oldQuantity, $newQuantity);
        }
    }

    public function addStock(int $amount): void
    {
        $this->updateQuantity($this->quantity + $amount);
    }

    public function removeStock(int $amount): bool
    {
        if ($this->quantity >= $amount) {
            $this->updateQuantity($this->quantity - $amount);
            return true;
        }
        return false;
    }

    public function restock(int $amount): void
    {
        $this->update([
            'quantity' => $this->max_stock_level,
            'last_restock_date' => now(),
            'days_stagnant' => 0,
        ]);
    }

    public function updateStagnantDays(): void
    {
        $lastActivity = $this->last_updated ?? $this->created_at;
        $stagnantDays = $lastActivity ? $lastActivity->diffInDays(now()) : 0;
        
        $this->update(['days_stagnant' => $stagnantDays]);
    }

    public function getInventorySummary(): array
    {
        return [
            'da_id' => $this->da_id,
            'da_name' => $this->deliveryAgent->user->name ?? 'Unknown',
            'product_type' => $this->product_type,
            'product_name' => $this->product_name,
            'quantity' => $this->quantity,
            'min_stock_level' => $this->min_stock_level,
            'max_stock_level' => $this->max_stock_level,
            'reorder_point' => $this->reorder_point,
            'stock_level' => $this->stock_level,
            'stock_percentage' => $this->stock_percentage,
            'days_stagnant' => $this->days_stagnant,
            'is_stagnant' => $this->is_stagnant,
            'needs_restock' => $this->needs_restock,
            'last_updated' => $this->last_updated?->format('M d, Y H:i'),
            'last_restock_date' => $this->last_restock_date?->format('M d, Y'),
        ];
    }

    private function logInventoryChange(int $oldQuantity, int $newQuantity): void
    {
        // This could be expanded to log inventory changes for audit purposes
        $change = $newQuantity - $oldQuantity;
        $action = $change > 0 ? 'stock_added' : 'stock_removed';
        
        // You could create an InventoryLog model to track these changes
        // For now, we'll just update the last_updated timestamp
    }
}

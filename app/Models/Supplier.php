<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Supplier extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'code',
        'contact_person',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'country',
        'products',
        'status',
        'payment_terms',
        'credit_limit',
        'tax_id',
        'bank_details',
        'notes',
        'rating',
        'total_orders',
        'total_spent',
        'last_order_date'
    ];

    protected $casts = [
        'products' => 'array',
        'bank_details' => 'array',
        'rating' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'total_spent' => 'decimal:2',
        'last_order_date' => 'date',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function purchaseOrders()
    {
        return $this->hasMany(PurchaseOrder::class);
    }

    public function items()
    {
        return $this->hasMany(Item::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopeByLocation($query, $state)
    {
        return $query->where('state', $state);
    }

    // Business Logic Methods
    public function generateSupplierCode(): string
    {
        $lastSupplier = self::latest('id')->first();
        $nextId = $lastSupplier ? $lastSupplier->id + 1 : 1;
        return 'SUP-' . str_pad($nextId, 4, '0', STR_PAD_LEFT);
    }

    public function updateOrderStats(): void
    {
        $totalOrders = $this->purchaseOrders()->count();
        $totalSpent = $this->purchaseOrders()->sum('total_amount');
        $lastOrder = $this->purchaseOrders()->latest('created_at')->first();
        
        $this->update([
            'total_orders' => $totalOrders,
            'total_spent' => $totalSpent,
            'last_order_date' => $lastOrder ? $lastOrder->created_at->toDateString() : null
        ]);
    }

    public function calculateRating(): float
    {
        $orders = $this->purchaseOrders()->whereNotNull('rating')->get();
        
        if ($orders->isEmpty()) {
            return 0.0;
        }
        
        return round($orders->avg('rating'), 2);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function canOrder(): bool
    {
        return $this->isActive() && $this->credit_limit > $this->total_spent;
    }

    public function getRemainingCreditAttribute(): float
    {
        return max(0, $this->credit_limit - $this->total_spent);
    }

    public function getPerformanceRatingAttribute(): string
    {
        if ($this->rating >= 4.5) return 'excellent';
        if ($this->rating >= 4.0) return 'good';
        if ($this->rating >= 3.0) return 'average';
        return 'poor';
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($supplier) {
            if (empty($supplier->code)) {
                $supplier->code = $supplier->generateSupplierCode();
            }
        });
    }
} 
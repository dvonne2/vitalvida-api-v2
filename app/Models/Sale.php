<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Sale extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'sale_number',
        'customer_id',
        'delivery_agent_id',
        'employee_id',
        'date',
        'subtotal',
        'tax_amount',
        'discount_amount',
        'total',
        'payment_method',
        'payment_status',
        'notes',
        'reference',
        'otp_verified',
        'verified_at',
        'verified_by'
    ];

    protected $casts = [
        'date' => 'date',
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total' => 'decimal:2',
        'otp_verified' => 'boolean',
        'verified_at' => 'datetime',
        'deleted_at' => 'datetime'
    ];

    // Relationships
    public function customer()
    {
        return $this->belongsTo(Customer::class);
    }

    public function deliveryAgent()
    {
        return $this->belongsTo(DeliveryAgent::class);
    }

    public function employee()
    {
        return $this->belongsTo(Employee::class);
    }

    public function items()
    {
        return $this->hasMany(SaleItem::class);
    }

    public function receipt()
    {
        return $this->hasOne(Receipt::class);
    }

    public function verifiedBy()
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    // Scopes
    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('date', [$startDate, $endDate]);
    }

    public function scopeByPaymentStatus($query, $status)
    {
        return $query->where('payment_status', $status);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    public function scopeByAgent($query, $agentId)
    {
        return $query->where('delivery_agent_id', $agentId);
    }

    public function scopeByEmployee($query, $employeeId)
    {
        return $query->where('employee_id', $employeeId);
    }

    public function scopeVerified($query)
    {
        return $query->where('otp_verified', true);
    }

    public function scopeUnverified($query)
    {
        return $query->where('otp_verified', false);
    }

    public function scopePaid($query)
    {
        return $query->where('payment_status', 'paid');
    }

    public function scopePending($query)
    {
        return $query->where('payment_status', 'pending');
    }

    // Business Logic Methods
    public function generateSaleNumber(): string
    {
        $lastSale = self::latest('id')->first();
        $nextId = $lastSale ? $lastSale->id + 1 : 1;
        return 'SALE-' . date('Y') . '-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function calculateTotals(): void
    {
        $subtotal = $this->items()->sum('total');
        $taxAmount = $this->tax_amount ?? 0;
        $discountAmount = $this->discount_amount ?? 0;
        
        $this->subtotal = $subtotal;
        $this->total = $subtotal + $taxAmount - $discountAmount;
        
        $this->save();
    }

    public function canVerify(): bool
    {
        return !$this->otp_verified && $this->payment_status === 'paid';
    }

    public function verify(int $userId): void
    {
        if (!$this->canVerify()) {
            throw new \Exception('Sale cannot be verified');
        }

        // Deduct stock for all items
        foreach ($this->items as $item) {
            $inventoryItem = Item::find($item->item_id);
            if ($inventoryItem) {
                $inventoryItem->decreaseStock($item->quantity, 'sale', $userId);
            }
        }

        $this->update([
            'otp_verified' => true,
            'verified_at' => now(),
            'verified_by' => $userId
        ]);
    }

    public function getPaymentStatusColorAttribute(): string
    {
        return match($this->payment_status) {
            'paid' => 'success',
            'pending' => 'warning',
            'failed' => 'danger',
            'refunded' => 'info',
            default => 'secondary'
        };
    }

    public function getVerificationStatusAttribute(): string
    {
        return $this->otp_verified ? 'verified' : 'unverified';
    }

    public function getItemsCountAttribute(): int
    {
        return $this->items()->count();
    }

    public function getTotalQuantityAttribute(): int
    {
        return $this->items()->sum('quantity');
    }

    // Events
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($sale) {
            if (empty($sale->sale_number)) {
                $sale->sale_number = $sale->generateSaleNumber();
            }
        });

        static::saved(function ($sale) {
            $sale->calculateTotals();
        });
    }
} 
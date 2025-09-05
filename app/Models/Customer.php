<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Customer extends Model
{
    use HasFactory;

    protected $fillable = [
        'customer_id',
        'name',
        'phone',
        'email',
        'address',
        'city',
        'state',
        'lga',
        'customer_type',
        'status',
        'zoho_contact_id',
        'lifetime_value',
        'total_orders',
        'last_order_date',
        'preferences',
        // AI Command Room additions
        'whatsapp_id',
        'meta_pixel_id',
        'total_spent',
        'orders_count',
        'last_purchase_date',
        'churn_probability',
        'lifetime_value_prediction',
        'preferred_contact_time',
        'persona_tag',
        'acquisition_source',
        'age',
        // Risk Management additions
        'abandoned_orders',
        'completed_orders',
        'risk_level',
        'risk_score',
        'requires_prepayment',
        'recovery_orders'
    ];

    protected $casts = [
        'lifetime_value' => 'decimal:2',
        'last_order_date' => 'date',
        'preferences' => 'array',
        'last_purchase_date' => 'datetime',
        'churn_probability' => 'decimal:2',
        'lifetime_value_prediction' => 'decimal:2',
        'preferred_contact_time' => 'array',
        'requires_prepayment' => 'boolean'
    ];

    // Relationships
    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function retargetingCampaigns(): HasMany
    {
        return $this->hasMany(RetargetingCampaign::class);
    }

    public function aiInteractions(): HasMany
    {
        return $this->hasMany(AIInteraction::class);
    }

    // Business Logic Methods
    public function generateCustomerId(): string
    {
        $lastCustomer = self::latest('id')->first();
        $nextId = $lastCustomer ? $lastCustomer->id + 1 : 1;
        return 'VV-CUST-' . str_pad($nextId, 6, '0', STR_PAD_LEFT);
    }

    public function updateLifetimeValue(): void
    {
        $totalValue = $this->orders()->sum('total_amount');
        $this->update(['lifetime_value' => $totalValue]);
    }

    public function updateOrderCount(): void
    {
        $orderCount = $this->orders()->count();
        $lastOrder = $this->orders()->latest('created_at')->first();
        
        $this->update([
            'total_orders' => $orderCount,
            'last_order_date' => $lastOrder ? $lastOrder->created_at->toDateString() : null
        ]);
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function getCustomerTier(): string
    {
        return match(true) {
            $this->lifetime_value >= 100000 => 'platinum',
            $this->lifetime_value >= 50000 => 'gold',
            $this->lifetime_value >= 20000 => 'silver',
            default => 'bronze'
        };
    }

    // AI-Powered Methods for Command Room
    public function predictNextPurchaseDate(): ?string
    {
        if (!$this->last_purchase_date) return null;
        
        // AI logic for predicting next purchase
        $daysSinceLastPurchase = $this->last_purchase_date->diffInDays(now());
        $averagePurchaseCycle = $this->orders()
            ->orderBy('created_at')
            ->get()
            ->sliding(2)
            ->map(fn($pair) => $pair[1]->created_at->diffInDays($pair[0]->created_at))
            ->average();

        return now()->addDays($averagePurchaseCycle ?? 25)->toDateString();
    }

    public function shouldTriggerReorderFlow(): bool
    {
        $daysSinceLastOrder = $this->last_purchase_date?->diffInDays(now()) ?? 999;
        return $daysSinceLastOrder >= 20 && $daysSinceLastOrder <= 35;
    }

    public function getOptimalContactTime(): array
    {
        // AI analyzes past engagement times
        return $this->preferred_contact_time ?? ['10:00', '14:00', '19:00'];
    }

    public function calculateChurnRisk(): float
    {
        $daysSinceLastOrder = $this->last_purchase_date?->diffInDays(now()) ?? 999;
        $averageOrderFrequency = $this->orders_count > 1 ? 
            $this->created_at->diffInDays($this->last_purchase_date) / $this->orders_count : 30;
        
        return min(($daysSinceLastOrder / $averageOrderFrequency) * 0.5, 1.0);
    }

    public function getAcquisitionSource(): string
    {
        return $this->acquisition_source ?? 'organic';
    }

    public function getPersonaTag(): string
    {
        return $this->persona_tag ?? 'general';
    }

    public function shouldReceiveHighValueCampaign(): bool
    {
        return $this->lifetime_value_prediction >= 50000 || $this->total_spent >= 30000;
    }

    public function getOptimalPlatforms(): array
    {
        $platforms = ['meta', 'whatsapp']; // Always include these
        
        // AI-based platform selection
        if ($this->age >= 18 && $this->age <= 34) {
            $platforms[] = 'tiktok';
        }
        
        if ($this->total_spent > 50000) {
            $platforms[] = 'google';
            $platforms[] = 'youtube';
        }
        
        if ($this->orders_count >= 2) {
            $platforms[] = 'email';
        }
        
        return $platforms;
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByState($query, $state)
    {
        return $query->where('state', $state);
    }

    public function scopeHighChurnRisk($query)
    {
        return $query->where('churn_probability', '>', 0.7);
    }

    public function scopeHighValue($query)
    {
        return $query->where('lifetime_value_prediction', '>=', 50000);
    }

    public function scopeReadyForReorder($query)
    {
        return $query->whereRaw('DATEDIFF(NOW(), last_purchase_date) BETWEEN 20 AND 35');
    }

    // Risk Management Methods
    public function calculateRiskLevel(): string
    {
        if ($this->abandoned_orders == 0) {
            return 'TRUSTED';
        } elseif ($this->abandoned_orders == 1) {
            return 'RISK1';
        } elseif ($this->abandoned_orders == 2) {
            return 'RISK2';
        } else {
            return 'RISK3';
        }
    }

    public function getRiskNotation(): string
    {
        $risk = $this->calculateRiskLevel();
        if ($risk === 'TRUSTED') return '✅ TRUSTED';
        if ($risk === 'RISK1') return '⚠️ RISK¹';
        if ($risk === 'RISK2') return '🚨 RISK²';
        return '🔴 RISK³';
    }

    public function getRepeatNotation(): string
    {
        if ($this->completed_orders < 2) return '';
        
        $superscripts = ['', '¹', '²', '³', '⁴', '⁵', '⁶', '⁷', '⁸', '⁹'];
        $count = min($this->completed_orders, 9);
        return '🔄 REPEAT' . ($superscripts[$count] ?? '⁹⁺');
    }

    public function shouldRequirePrepayment(): bool
    {
        return $this->abandoned_orders >= 3 && $this->recovery_orders < 3;
    }

    public function canRestorePayOnDelivery(): bool
    {
        return $this->abandoned_orders >= 3 && $this->recovery_orders >= 3;
    }
}

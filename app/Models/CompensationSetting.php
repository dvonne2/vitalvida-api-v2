<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CompensationSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'pickup_return_amount', 'maximum_per_delivery', 'minimum_per_delivery',
        'payment_frequency', 'payment_method', 'payment_threshold',
        'base_commission_rate', 'bonus_commission_rate', 'bonus_delivery_threshold',
        'on_time_delivery_bonus', 'customer_satisfaction_bonus', 'referral_bonus',
        'late_delivery_penalty', 'customer_complaint_penalty', 'damage_penalty',
        'active', 'effective_from', 'effective_until', 'created_by', 'notes'
    ];

    protected $casts = [
        'pickup_return_amount' => 'decimal:2',
        'maximum_per_delivery' => 'decimal:2',
        'minimum_per_delivery' => 'decimal:2',
        'payment_threshold' => 'decimal:2',
        'base_commission_rate' => 'decimal:2',
        'bonus_commission_rate' => 'decimal:2',
        'bonus_delivery_threshold' => 'integer',
        'on_time_delivery_bonus' => 'decimal:2',
        'customer_satisfaction_bonus' => 'decimal:2',
        'referral_bonus' => 'decimal:2',
        'late_delivery_penalty' => 'decimal:2',
        'customer_complaint_penalty' => 'decimal:2',
        'damage_penalty' => 'decimal:2',
        'active' => 'boolean',
        'effective_from' => 'datetime',
        'effective_until' => 'datetime'
    ];

    public function getPaymentFrequencyTextAttribute()
    {
        return match($this->payment_frequency) {
            'daily' => 'Daily',
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
            default => 'Unknown'
        };
    }

    public function getPaymentMethodTextAttribute()
    {
        return match($this->payment_method) {
            'portal' => 'Portal Payment',
            'bank_transfer' => 'Bank Transfer',
            'mobile_money' => 'Mobile Money',
            'cash' => 'Cash',
            default => 'Unknown'
        };
    }

    public function calculateDeliveryCompensation($deliveryAmount, $isOnTime = true, $customerSatisfaction = 5)
    {
        $compensation = $this->pickup_return_amount;
        
        // Add delivery commission
        $commission = ($deliveryAmount * $this->base_commission_rate) / 100;
        $compensation += $commission;
        
        // Apply minimum and maximum limits
        $compensation = max($this->minimum_per_delivery, $compensation);
        $compensation = min($this->maximum_per_delivery, $compensation);
        
        // Add bonuses
        if ($isOnTime) {
            $compensation += $this->on_time_delivery_bonus;
        }
        
        if ($customerSatisfaction >= 4) {
            $compensation += $this->customer_satisfaction_bonus;
        }
        
        return round($compensation, 2);
    }

    public function calculateBonusCommission($totalDeliveries)
    {
        if ($totalDeliveries >= $this->bonus_delivery_threshold) {
            return ($totalDeliveries * $this->bonus_commission_rate) / 100;
        }
        return 0;
    }

    public function calculatePenalties($lateDeliveries = 0, $complaints = 0, $damages = 0)
    {
        $totalPenalties = 0;
        
        $totalPenalties += $lateDeliveries * $this->late_delivery_penalty;
        $totalPenalties += $complaints * $this->customer_complaint_penalty;
        $totalPenalties += $damages * $this->damage_penalty;
        
        return round($totalPenalties, 2);
    }

    public function calculateNetCompensation($grossCompensation, $penalties = 0)
    {
        return max(0, $grossCompensation - $penalties);
    }

    public function isCurrentlyActive()
    {
        $now = now();
        return $this->active && 
               $this->effective_from <= $now && 
               (!$this->effective_until || $this->effective_until >= $now);
    }

    public function getEffectivePeriodAttribute()
    {
        $from = $this->effective_from->format('M d, Y');
        $until = $this->effective_until ? $this->effective_until->format('M d, Y') : 'Ongoing';
        
        return "{$from} - {$until}";
    }

    public function getTotalBonusPotentialAttribute()
    {
        return $this->on_time_delivery_bonus + 
               $this->customer_satisfaction_bonus + 
               $this->referral_bonus;
    }

    public function getTotalPenaltyPotentialAttribute()
    {
        return $this->late_delivery_penalty + 
               $this->customer_complaint_penalty + 
               $this->damage_penalty;
    }

    public function getCommissionRangeAttribute()
    {
        $minCommission = ($this->minimum_per_delivery * $this->base_commission_rate) / 100;
        $maxCommission = ($this->maximum_per_delivery * $this->base_commission_rate) / 100;
        
        return [
            'min' => round($minCommission, 2),
            'max' => round($maxCommission, 2)
        ];
    }

    public function getPaymentThresholdTextAttribute()
    {
        return '₦' . number_format($this->payment_threshold, 2);
    }

    public function getPickupReturnAmountTextAttribute()
    {
        return '₦' . number_format($this->pickup_return_amount, 2);
    }

    public function getMaximumPerDeliveryTextAttribute()
    {
        return '₦' . number_format($this->maximum_per_delivery, 2);
    }

    public function getMinimumPerDeliveryTextAttribute()
    {
        return '₦' . number_format($this->minimum_per_delivery, 2);
    }

    public function getBaseCommissionRateTextAttribute()
    {
        return $this->base_commission_rate . '%';
    }

    public function getBonusCommissionRateTextAttribute()
    {
        return $this->bonus_commission_rate . '%';
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeCurrentlyEffective($query)
    {
        $now = now();
        return $query->where('active', true)
                    ->where('effective_from', '<=', $now)
                    ->where(function($q) use ($now) {
                        $q->whereNull('effective_until')
                          ->orWhere('effective_until', '>=', $now);
                    });
    }

    public function scopeByPaymentFrequency($query, $frequency)
    {
        return $query->where('payment_frequency', $frequency);
    }

    public function scopeByPaymentMethod($query, $method)
    {
        return $query->where('payment_method', $method);
    }

    // Static methods
    public static function getCurrentSettings()
    {
        return self::currentlyEffective()->first();
    }

    public static function createNewSettings($data)
    {
        // Deactivate current settings
        self::currentlyEffective()->update(['active' => false]);
        
        // Create new settings
        return self::create(array_merge($data, [
            'effective_from' => now(),
            'active' => true
        ]));
    }

    public static function getSettingsForDate($date)
    {
        return self::where('active', true)
                  ->where('effective_from', '<=', $date)
                  ->where(function($q) use ($date) {
                      $q->whereNull('effective_until')
                        ->orWhere('effective_until', '>=', $date);
                  })
                  ->first();
    }
}

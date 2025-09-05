<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BankAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'bank_name',
        'account_number',
        'account_name',
        'account_code',
        'wallet_type',
        'allocation_percentage',
        'current_balance',
        'status',
        'purpose_description',
        'api_key',
        'webhook_url',
        'transaction_limits',
        'created_by',
    ];

    protected $casts = [
        'allocation_percentage' => 'decimal:2',
        'current_balance' => 'decimal:2',
        'transaction_limits' => 'array',
    ];

    // Relationships
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByWalletType($query, $type)
    {
        return $query->where('wallet_type', $type);
    }

    public function scopeByBank($query, $bankName)
    {
        return $query->where('bank_name', $bankName);
    }

    // Accessors
    public function getFormattedBalanceAttribute()
    {
        return '₦' . number_format($this->current_balance, 2);
    }

    public function getFormattedAllocationAttribute()
    {
        return number_format($this->allocation_percentage, 1) . '%';
    }

    public function getWalletTypeColorAttribute()
    {
        return match($this->wallet_type) {
            'marketing' => 'text-blue-600',
            'opex' => 'text-orange-600',
            'inventory' => 'text-green-600',
            'profit' => 'text-purple-600',
            'bonus' => 'text-yellow-600',
            'tax' => 'text-red-600',
            'main' => 'text-gray-600',
            default => 'text-gray-600',
        };
    }

    public function getWalletTypeIconAttribute()
    {
        return match($this->wallet_type) {
            'marketing' => '📢',
            'opex' => '💼',
            'inventory' => '📦',
            'profit' => '💰',
            'bonus' => '🎁',
            'tax' => '🏛️',
            'main' => '🏦',
            default => '💳',
        };
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'active' => 'text-green-600',
            'locked' => 'text-red-600',
            'restricted' => 'text-yellow-600',
            default => 'text-gray-600',
        };
    }

    // Methods
    public function updateBalance($amount)
    {
        if ($this->status === 'active') {
            $this->current_balance += $amount;
            $this->save();
        }
    }

    public function lockAccount()
    {
        $this->status = 'locked';
        $this->save();
    }

    public function unlockAccount()
    {
        $this->status = 'active';
        $this->save();
    }

    public function restrictAccount()
    {
        $this->status = 'restricted';
        $this->save();
    }

    public function getDailyLimit()
    {
        return $this->transaction_limits['daily'] ?? null;
    }

    public function getMonthlyLimit()
    {
        return $this->transaction_limits['monthly'] ?? null;
    }

    public function canProcessTransaction($amount)
    {
        if ($this->status !== 'active') {
            return false;
        }

        // Check daily limit
        $dailyLimit = $this->getDailyLimit();
        if ($dailyLimit && $this->getTodayTransactions() + $amount > $dailyLimit) {
            return false;
        }

        // Check monthly limit
        $monthlyLimit = $this->getMonthlyLimit();
        if ($monthlyLimit && $this->getMonthTransactions() + $amount > $monthlyLimit) {
            return false;
        }

        return true;
    }

    private function getTodayTransactions()
    {
        // This would be implemented with actual transaction tracking
        return 0;
    }

    private function getMonthTransactions()
    {
        // This would be implemented with actual transaction tracking
        return 0;
    }

    // Static methods for Profit First
    public static function getProfitFirstAccounts()
    {
        return static::whereIn('wallet_type', [
            'marketing',
            'opex', 
            'inventory',
            'profit',
            'bonus',
            'tax'
        ])->active()->get();
    }

    public static function getMainAccount()
    {
        return static::where('wallet_type', 'main')->active()->first();
    }

    public static function getTaxAccount()
    {
        return static::where('wallet_type', 'tax')->active()->first();
    }

    public static function getProfitAccount()
    {
        return static::where('wallet_type', 'profit')->active()->first();
    }

    public static function getMarketingAccount()
    {
        return static::where('wallet_type', 'marketing')->active()->first();
    }

    public static function getOpexAccount()
    {
        return static::where('wallet_type', 'opex')->active()->first();
    }

    public static function getInventoryAccount()
    {
        return static::where('wallet_type', 'inventory')->active()->first();
    }

    public static function getBonusAccount()
    {
        return static::where('wallet_type', 'bonus')->active()->first();
    }

    // Profit First allocation methods
    public static function allocateRevenue($amount)
    {
        $accounts = static::getProfitFirstAccounts();
        
        foreach ($accounts as $account) {
            $allocation = ($account->allocation_percentage / 100) * $amount;
            $account->updateBalance($allocation);
        }
    }

    public static function getTotalAllocationPercentage()
    {
        return static::getProfitFirstAccounts()->sum('allocation_percentage');
    }
}

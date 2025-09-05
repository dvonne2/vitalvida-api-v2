<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ChartOfAccount extends Model
{
    use HasFactory;

    protected $fillable = [
        'account_code',
        'account_name',
        'account_type',
        'reporting_group',
        'current_balance',
        'is_locked',
        'parent_account_id',
        'description',
        'status',
    ];

    protected $casts = [
        'current_balance' => 'decimal:2',
        'is_locked' => 'boolean',
    ];

    // Relationships
    public function parentAccount(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'parent_account_id');
    }

    public function childAccounts(): HasMany
    {
        return $this->hasMany(ChartOfAccount::class, 'parent_account_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeByType($query, $type)
    {
        return $query->where('account_type', $type);
    }

    public function scopeByReportingGroup($query, $group)
    {
        return $query->where('reporting_group', $group);
    }

    // Accessors
    public function getFormattedBalanceAttribute()
    {
        return '₦' . number_format($this->current_balance, 2);
    }

    public function getAccountTypeColorAttribute()
    {
        return match($this->account_type) {
            'Asset' => 'text-green-600',
            'Liability' => 'text-red-600',
            'Income' => 'text-blue-600',
            'Expense' => 'text-orange-600',
            'Equity' => 'text-purple-600',
            default => 'text-gray-600',
        };
    }

    public function getIsLockedStatusAttribute()
    {
        return $this->is_locked ? 'Locked' : 'Active';
    }

    // Methods
    public function updateBalance($amount)
    {
        if (!$this->is_locked) {
            $this->current_balance += $amount;
            $this->save();
        }
    }

    public function lockAccount()
    {
        $this->is_locked = true;
        $this->save();
    }

    public function unlockAccount()
    {
        $this->is_locked = false;
        $this->save();
    }

    public function getHierarchyLevel()
    {
        $level = 0;
        $current = $this;
        
        while ($current->parentAccount) {
            $level++;
            $current = $current->parentAccount;
        }
        
        return $level;
    }

    public function getFullAccountPath()
    {
        $path = [$this->account_name];
        $current = $this;
        
        while ($current->parentAccount) {
            $current = $current->parentAccount;
            array_unshift($path, $current->account_name);
        }
        
        return implode(' > ', $path);
    }

    // Static methods for Nigerian compliance
    public static function getTaxAccounts()
    {
        return static::whereIn('account_code', [
            'VAT-PAYABLE',
            'PAYE-PAYABLE',
            'CIT-PAYABLE',
            'EDT-PAYABLE',
            'WHT-PAYABLE'
        ])->get();
    }

    public static function getRevenueAccounts()
    {
        return static::where('account_type', 'Income')
            ->where('reporting_group', 'Revenue')
            ->get();
    }

    public static function getExpenseAccounts()
    {
        return static::where('account_type', 'Expense')
            ->where('status', 'active')
            ->get();
    }
}

<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProfitAllocation extends Model
{
    use HasFactory;

    protected $fillable = [
        'payment_reference',
        'amount_received',
        'allocated_to',
        'amount_allocated',
        'bank_account_id',
        'allocation_status',
        'allocated_at',
    ];

    protected $casts = [
        'amount_received' => 'decimal:2',
        'amount_allocated' => 'decimal:2',
        'allocated_at' => 'datetime',
    ];

    // Relationships
    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(BankAccount::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('allocation_status', $status);
    }

    public function scopePending($query)
    {
        return $query->where('allocation_status', 'pending');
    }

    public function scopeCompleted($query)
    {
        return $query->where('allocation_status', 'completed');
    }

    public function scopeFailed($query)
    {
        return $query->where('allocation_status', 'failed');
    }

    public function scopeByWalletType($query, $walletType)
    {
        return $query->where('allocated_to', $walletType);
    }

    public function scopeByPaymentReference($query, $reference)
    {
        return $query->where('payment_reference', $reference);
    }

    // Accessors
    public function getFormattedAmountReceivedAttribute()
    {
        return '₦' . number_format($this->amount_received, 2);
    }

    public function getFormattedAmountAllocatedAttribute()
    {
        return '₦' . number_format($this->amount_allocated, 2);
    }

    public function getAllocationPercentageAttribute()
    {
        if ($this->amount_received > 0) {
            return round(($this->amount_allocated / $this->amount_received) * 100, 2);
        }
        return 0;
    }

    public function getStatusColorAttribute()
    {
        return match($this->allocation_status) {
            'pending' => 'text-yellow-600',
            'completed' => 'text-green-600',
            'failed' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->allocation_status) {
            'pending' => '⏳',
            'completed' => '✅',
            'failed' => '❌',
            default => '📄',
        };
    }

    public function getWalletTypeIconAttribute()
    {
        return match($this->allocated_to) {
            'marketing' => '📢',
            'opex' => '💼',
            'inventory' => '📦',
            'profit' => '💰',
            'bonus' => '🎁',
            'tax' => '🏛️',
            default => '💳',
        };
    }

    public function getWalletTypeColorAttribute()
    {
        return match($this->allocated_to) {
            'marketing' => 'text-blue-600',
            'opex' => 'text-orange-600',
            'inventory' => 'text-green-600',
            'profit' => 'text-purple-600',
            'bonus' => 'text-yellow-600',
            'tax' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    // Methods
    public function markAsCompleted()
    {
        $this->allocation_status = 'completed';
        $this->allocated_at = now();
        $this->save();

        // Update bank account balance
        if ($this->bankAccount) {
            $this->bankAccount->updateBalance($this->amount_allocated);
        }
    }

    public function markAsFailed($reason = null)
    {
        $this->allocation_status = 'failed';
        $this->save();
    }

    public function retryAllocation()
    {
        if ($this->allocation_status === 'failed') {
            $this->allocation_status = 'pending';
            $this->save();
        }
    }

    // Static methods
    public static function allocateRevenue($amount, $paymentRef)
    {
        $allocations = [];
        $profitFirstAccounts = BankAccount::getProfitFirstAccounts();
        
        foreach ($profitFirstAccounts as $account) {
            $allocationAmount = ($account->allocation_percentage / 100) * $amount;
            
            if ($allocationAmount > 0) {
                $allocation = static::create([
                    'payment_reference' => $paymentRef,
                    'amount_received' => $amount,
                    'allocated_to' => $account->wallet_type,
                    'amount_allocated' => $allocationAmount,
                    'bank_account_id' => $account->id,
                    'allocation_status' => 'pending',
                ]);

                $allocations[] = $allocation;
            }
        }

        return $allocations;
    }

    public static function getWalletBalances()
    {
        $balances = [];
        $walletTypes = ['marketing', 'opex', 'inventory', 'profit', 'bonus', 'tax'];

        foreach ($walletTypes as $walletType) {
            $completedAllocations = static::where('allocated_to', $walletType)
                ->where('allocation_status', 'completed')
                ->sum('amount_allocated');

            $balances[$walletType] = [
                'total_allocated' => $completedAllocations,
                'formatted_total' => '₦' . number_format($completedAllocations, 2),
                'account' => BankAccount::where('wallet_type', $walletType)->first(),
            ];
        }

        return $balances;
    }

    public static function getAllocationSummary($startDate = null, $endDate = null)
    {
        $query = static::with('bankAccount');

        if ($startDate) {
            $query->where('created_at', '>=', $startDate);
        }

        if ($endDate) {
            $query->where('created_at', '<=', $endDate);
        }

        return $query->get()
            ->groupBy('allocated_to')
            ->map(function ($allocations) {
                return [
                    'total_allocated' => $allocations->sum('amount_allocated'),
                    'total_received' => $allocations->sum('amount_received'),
                    'count' => $allocations->count(),
                    'completed_count' => $allocations->where('allocation_status', 'completed')->count(),
                    'failed_count' => $allocations->where('allocation_status', 'failed')->count(),
                    'pending_count' => $allocations->where('allocation_status', 'pending')->count(),
                ];
            });
    }

    public static function getPendingAllocations()
    {
        return static::with('bankAccount')
            ->where('allocation_status', 'pending')
            ->orderBy('created_at')
            ->get();
    }

    public static function getFailedAllocations()
    {
        return static::with('bankAccount')
            ->where('allocation_status', 'failed')
            ->orderBy('created_at')
            ->get();
    }

    public static function getTotalAllocatedByPeriod($startDate, $endDate)
    {
        return static::where('allocation_status', 'completed')
            ->whereBetween('allocated_at', [$startDate, $endDate])
            ->sum('amount_allocated');
    }

    public static function getProfitFirstCompliance()
    {
        $totalAllocation = static::where('allocation_status', 'completed')
            ->sum('amount_allocated');

        $expectedAllocation = BankAccount::getTotalAllocationPercentage();

        return [
            'total_allocated' => $totalAllocation,
            'expected_percentage' => $expectedAllocation,
            'compliance_percentage' => $expectedAllocation > 0 ? ($totalAllocation / $expectedAllocation) * 100 : 0,
            'is_compliant' => $expectedAllocation > 0 && $totalAllocation >= $expectedAllocation,
        ];
    }
}

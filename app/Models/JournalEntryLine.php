<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JournalEntryLine extends Model
{
    use HasFactory;

    protected $fillable = [
        'journal_entry_id',
        'account_id',
        'debit_amount',
        'credit_amount',
        'line_description',
    ];

    protected $casts = [
        'debit_amount' => 'decimal:2',
        'credit_amount' => 'decimal:2',
    ];

    // Relationships
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(ChartOfAccount::class, 'account_id');
    }

    // Scopes
    public function scopeDebits($query)
    {
        return $query->where('debit_amount', '>', 0);
    }

    public function scopeCredits($query)
    {
        return $query->where('credit_amount', '>', 0);
    }

    public function scopeByAccount($query, $accountId)
    {
        return $query->where('account_id', $accountId);
    }

    // Accessors
    public function getFormattedDebitAmountAttribute()
    {
        return $this->debit_amount > 0 ? '₦' . number_format($this->debit_amount, 2) : '';
    }

    public function getFormattedCreditAmountAttribute()
    {
        return $this->credit_amount > 0 ? '₦' . number_format($this->credit_amount, 2) : '';
    }

    public function getNetAmountAttribute()
    {
        return $this->debit_amount - $this->credit_amount;
    }

    public function getFormattedNetAmountAttribute()
    {
        $net = $this->net_amount;
        $prefix = $net >= 0 ? '₦' : '-₦';
        return $prefix . number_format(abs($net), 2);
    }

    public function getAccountNameAttribute()
    {
        return $this->account->account_name ?? 'Unknown Account';
    }

    public function getAccountCodeAttribute()
    {
        return $this->account->account_code ?? 'N/A';
    }

    public function getAccountTypeAttribute()
    {
        return $this->account->account_type ?? 'Unknown';
    }

    // Methods
    public function isDebit()
    {
        return $this->debit_amount > 0;
    }

    public function isCredit()
    {
        return $this->credit_amount > 0;
    }

    public function getBalanceImpact()
    {
        if ($this->account) {
            $accountType = $this->account->account_type;
            
            // For assets and expenses, debits increase balance
            if (in_array($accountType, ['Asset', 'Expense'])) {
                return $this->debit_amount - $this->credit_amount;
            }
            
            // For liabilities, income, and equity, credits increase balance
            if (in_array($accountType, ['Liability', 'Income', 'Equity'])) {
                return $this->credit_amount - $this->debit_amount;
            }
        }
        
        return 0;
    }

    public function validateAccount()
    {
        if (!$this->account) {
            throw new \Exception('Invalid account ID: ' . $this->account_id);
        }

        if ($this->account->is_locked) {
            throw new \Exception('Account is locked: ' . $this->account->account_name);
        }

        return true;
    }

    // Static methods
    public static function getAccountBalance($accountId, $asOfDate = null)
    {
        $query = static::with('journalEntry')
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', 'posted');
            });

        if ($asOfDate) {
            $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('entry_date', '<=', $asOfDate);
            });
        }

        $debits = $query->sum('debit_amount');
        $credits = $query->sum('credit_amount');

        return $debits - $credits;
    }

    public static function getAccountMovements($accountId, $startDate = null, $endDate = null)
    {
        $query = static::with(['journalEntry', 'account'])
            ->where('account_id', $accountId)
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', 'posted');
            });

        if ($startDate) {
            $query->whereHas('journalEntry', function ($q) use ($startDate) {
                $q->where('entry_date', '>=', $startDate);
            });
        }

        if ($endDate) {
            $query->whereHas('journalEntry', function ($q) use ($endDate) {
                $q->where('entry_date', '<=', $endDate);
            });
        }

        return $query->orderBy('created_at')->get();
    }

    public static function getTrialBalance($asOfDate = null)
    {
        $query = static::with('account')
            ->whereHas('journalEntry', function ($q) {
                $q->where('status', 'posted');
            });

        if ($asOfDate) {
            $query->whereHas('journalEntry', function ($q) use ($asOfDate) {
                $q->where('entry_date', '<=', $asOfDate);
            });
        }

        $balances = $query->get()
            ->groupBy('account_id')
            ->map(function ($lines) {
                $debits = $lines->sum('debit_amount');
                $credits = $lines->sum('credit_amount');
                $netBalance = $debits - $credits;
                
                return [
                    'account' => $lines->first()->account,
                    'debits' => $debits,
                    'credits' => $credits,
                    'net_balance' => $netBalance,
                    'formatted_balance' => '₦' . number_format(abs($netBalance), 2),
                ];
            })
            ->filter(function ($balance) {
                return abs($balance['net_balance']) > 0.01; // Only show accounts with balances
            });

        return $balances;
    }
}

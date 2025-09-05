<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class JournalEntry extends Model
{
    use HasFactory;

    protected $fillable = [
        'reference_number',
        'entry_date',
        'description',
        'total_amount',
        'status',
        'created_by',
        'approved_by',
        'posted_at',
    ];

    protected $casts = [
        'entry_date' => 'date',
        'total_amount' => 'decimal:2',
        'posted_at' => 'datetime',
    ];

    // Relationships
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }

    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }

    public function scopePosted($query)
    {
        return $query->where('status', 'posted');
    }

    public function scopeDraft($query)
    {
        return $query->where('status', 'draft');
    }

    public function scopeReversed($query)
    {
        return $query->where('status', 'reversed');
    }

    public function scopeByDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('entry_date', [$startDate, $endDate]);
    }

    // Accessors
    public function getFormattedTotalAmountAttribute()
    {
        return '₦' . number_format($this->total_amount, 2);
    }

    public function getStatusColorAttribute()
    {
        return match($this->status) {
            'draft' => 'text-gray-600',
            'posted' => 'text-green-600',
            'reversed' => 'text-red-600',
            default => 'text-gray-600',
        };
    }

    public function getStatusIconAttribute()
    {
        return match($this->status) {
            'draft' => '📝',
            'posted' => '✅',
            'reversed' => '🔄',
            default => '📄',
        };
    }

    public function getIsBalancedAttribute()
    {
        $debits = $this->lines->sum('debit_amount');
        $credits = $this->lines->sum('credit_amount');
        return abs($debits - $credits) < 0.01; // Allow for rounding differences
    }

    public function getDebitTotalAttribute()
    {
        return $this->lines->sum('debit_amount');
    }

    public function getCreditTotalAttribute()
    {
        return $this->lines->sum('credit_amount');
    }

    // Methods
    public function addLine($accountId, $debitAmount = 0, $creditAmount = 0, $description = null)
    {
        return $this->lines()->create([
            'account_id' => $accountId,
            'debit_amount' => $debitAmount,
            'credit_amount' => $creditAmount,
            'line_description' => $description,
        ]);
    }

    public function validateDoubleEntry()
    {
        $debits = $this->lines->sum('debit_amount');
        $credits = $this->lines->sum('credit_amount');
        
        return abs($debits - $credits) < 0.01;
    }

    public function post()
    {
        if (!$this->validateDoubleEntry()) {
            throw new \Exception('Journal entry is not balanced. Debits must equal credits.');
        }

        if ($this->status !== 'draft') {
            throw new \Exception('Only draft entries can be posted.');
        }

        // Update account balances
        foreach ($this->lines as $line) {
            $account = $line->account;
            if ($account && !$account->is_locked) {
                $netChange = $line->debit_amount - $line->credit_amount;
                $account->updateBalance($netChange);
            }
        }

        $this->status = 'posted';
        $this->posted_at = now();
        $this->save();
    }

    public function reverse()
    {
        if ($this->status !== 'posted') {
            throw new \Exception('Only posted entries can be reversed.');
        }

        // Create reversal entry
        $reversal = static::create([
            'reference_number' => $this->reference_number . '-REV',
            'entry_date' => now()->toDateString(),
            'description' => 'Reversal of ' . $this->description,
            'total_amount' => $this->total_amount,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        // Create reversal lines with opposite amounts
        foreach ($this->lines as $line) {
            $reversal->addLine(
                $line->account_id,
                $line->credit_amount, // Swap debit and credit
                $line->debit_amount,
                'Reversal of ' . $line->line_description
            );
        }

        $this->status = 'reversed';
        $this->save();

        return $reversal;
    }

    public function approve($approvedBy = null)
    {
        $this->approved_by = $approvedBy ?? auth()->id();
        $this->save();
    }

    // Static methods
    public static function generateReferenceNumber()
    {
        $prefix = 'JE';
        $date = now()->format('Ymd');
        $sequence = static::whereDate('created_at', today())->count() + 1;
        
        return $prefix . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    public static function createEntry($data)
    {
        $entry = static::create([
            'reference_number' => $data['reference_number'] ?? static::generateReferenceNumber(),
            'entry_date' => $data['entry_date'] ?? now()->toDateString(),
            'description' => $data['description'],
            'total_amount' => $data['total_amount'] ?? 0,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        // Add lines
        if (isset($data['lines']) && is_array($data['lines'])) {
            foreach ($data['lines'] as $line) {
                $entry->addLine(
                    $line['account_id'],
                    $line['debit_amount'] ?? 0,
                    $line['credit_amount'] ?? 0,
                    $line['description'] ?? null
                );
            }
        }

        return $entry;
    }

    public static function getUnbalancedEntries()
    {
        return static::with('lines')
            ->where('status', 'draft')
            ->get()
            ->filter(function ($entry) {
                return !$entry->is_balanced;
            });
    }

    public static function getPostedEntriesByDateRange($startDate, $endDate)
    {
        return static::with(['lines.account', 'creator', 'approver'])
            ->where('status', 'posted')
            ->whereBetween('entry_date', [$startDate, $endDate])
            ->orderBy('entry_date')
            ->get();
    }
}

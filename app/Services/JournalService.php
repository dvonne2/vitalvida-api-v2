<?php

namespace App\Services;

use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\ChartOfAccount;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class JournalService
{
    /**
     * Create a new journal entry with lines
     */
    public function createEntry(array $data): JournalEntry
    {
        DB::beginTransaction();

        try {
            // Validate the entry data
            $this->validateEntryData($data);

            // Create the journal entry
            $entry = JournalEntry::create([
                'reference_number' => $data['reference_number'] ?? JournalEntry::generateReferenceNumber(),
                'entry_date' => $data['entry_date'] ?? now()->toDateString(),
                'description' => $data['description'],
                'total_amount' => $data['total_amount'] ?? 0,
                'status' => 'draft',
                'created_by' => auth()->id() ?? $data['created_by'] ?? 1, // Fallback to user ID 1 if not authenticated
            ]);

            // Add journal entry lines
            if (isset($data['lines']) && is_array($data['lines'])) {
                foreach ($data['lines'] as $lineData) {
                    $entry->addLine(
                        $lineData['account_id'],
                        $lineData['debit_amount'] ?? 0,
                        $lineData['credit_amount'] ?? 0,
                        $lineData['line_description'] ?? null
                    );
                }
            }

            // Validate double-entry balance
            if (!$this->validateDoubleEntry($entry->lines->toArray())) {
                throw new \Exception('Journal entry is not balanced. Debits must equal credits.');
            }

            DB::commit();

            Log::info('Journal entry created', [
                'entry_id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'total_amount' => $entry->total_amount,
                'lines_count' => $entry->lines->count(),
            ]);

            return $entry->load(['lines.account', 'creator']);

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to create journal entry', [
                'error' => $e->getMessage(),
                'data' => $data,
            ]);
            throw $e;
        }
    }

    /**
     * Validate double-entry balance
     */
    public function validateDoubleEntry(array $lines): bool
    {
        $totalDebits = 0;
        $totalCredits = 0;

        foreach ($lines as $line) {
            $debitAmount = $line['debit_amount'] ?? 0;
            $creditAmount = $line['credit_amount'] ?? 0;
            
            $totalDebits += $debitAmount;
            $totalCredits += $creditAmount;
        }

        // Allow for small rounding differences (0.01)
        return abs($totalDebits - $totalCredits) < 0.01;
    }

    /**
     * Post a journal entry
     */
    public function postEntry(int $entryId): bool
    {
        DB::beginTransaction();

        try {
            $entry = JournalEntry::with('lines.account')->findOrFail($entryId);

            // Validate entry can be posted
            if ($entry->status !== 'draft') {
                throw new \Exception('Only draft entries can be posted.');
            }

            if (!$this->validateDoubleEntry($entry->lines->toArray())) {
                throw new \Exception('Journal entry is not balanced. Debits must equal credits.');
            }

            // Check if any accounts are locked
            foreach ($entry->lines as $line) {
                if ($line->account && $line->account->is_locked) {
                    throw new \Exception("Account {$line->account->account_name} is locked and cannot be modified.");
                }
            }

            // Update account balances
            foreach ($entry->lines as $line) {
                $account = $line->account;
                if ($account) {
                    $netChange = $line->debit_amount - $line->credit_amount;
                    $account->updateBalance($netChange);
                }
            }

            // Mark entry as posted
            $entry->status = 'posted';
            $entry->posted_at = now();
            $entry->save();

            DB::commit();

            Log::info('Journal entry posted', [
                'entry_id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'posted_at' => $entry->posted_at,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to post journal entry', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Generate a unique reference number
     */
    public function generateReferenceNumber(): string
    {
        $prefix = 'JE';
        $date = now()->format('Ymd');
        $sequence = JournalEntry::whereDate('created_at', today())->count() + 1;
        
        return $prefix . $date . '-' . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Validate entry data
     */
    private function validateEntryData(array $data): void
    {
        if (empty($data['description'])) {
            throw new \Exception('Journal entry description is required.');
        }

        if (empty($data['lines']) || !is_array($data['lines']) || count($data['lines']) < 2) {
            throw new \Exception('Journal entry must have at least 2 lines.');
        }

        // Validate each line
        foreach ($data['lines'] as $index => $line) {
            if (empty($line['account_id'])) {
                throw new \Exception("Line " . ($index + 1) . ": Account ID is required.");
            }

            $debitAmount = $line['debit_amount'] ?? 0;
            $creditAmount = $line['credit_amount'] ?? 0;

            if ($debitAmount == 0 && $creditAmount == 0) {
                throw new \Exception("Line " . ($index + 1) . ": Either debit or credit amount must be greater than zero.");
            }

            if ($debitAmount > 0 && $creditAmount > 0) {
                throw new \Exception("Line " . ($index + 1) . ": Cannot have both debit and credit amounts.");
            }

            // Validate account exists
            $account = ChartOfAccount::find($line['account_id']);
            if (!$account) {
                throw new \Exception("Line " . ($index + 1) . ": Invalid account ID.");
            }

            if ($account->is_locked) {
                throw new \Exception("Line " . ($index + 1) . ": Account {$account->account_name} is locked.");
            }
        }
    }

    /**
     * Get trial balance as of a specific date
     */
    public function getTrialBalance(Carbon $asOfDate = null): array
    {
        $asOfDate = $asOfDate ?? now();

        $balances = JournalEntryLine::with('account')
            ->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<=', $asOfDate);
            })
            ->get()
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

        return $balances->toArray();
    }

    /**
     * Get account balance as of a specific date
     */
    public function getAccountBalance(int $accountId, Carbon $asOfDate = null): float
    {
        $asOfDate = $asOfDate ?? now();

        $debits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<=', $asOfDate);
            })
            ->sum('debit_amount');

        $credits = JournalEntryLine::where('account_id', $accountId)
            ->whereHas('journalEntry', function ($query) use ($asOfDate) {
                $query->where('status', 'posted')
                      ->where('entry_date', '<=', $asOfDate);
            })
            ->sum('credit_amount');

        return $debits - $credits;
    }

    /**
     * Get journal entries by date range
     */
    public function getEntriesByDateRange(Carbon $startDate, Carbon $endDate, string $status = null): array
    {
        $query = JournalEntry::with(['lines.account', 'creator', 'approver'])
            ->whereBetween('entry_date', [$startDate, $endDate]);

        if ($status) {
            $query->where('status', $status);
        }

        $entries = $query->orderBy('entry_date')->get();

        return $entries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'total_amount' => $entry->total_amount,
                'formatted_total_amount' => $entry->formatted_total_amount,
                'status' => $entry->status,
                'status_color' => $entry->status_color,
                'status_icon' => $entry->status_icon,
                'is_balanced' => $entry->is_balanced,
                'creator' => $entry->creator ? $entry->creator->name : 'Unknown',
                'approver' => $entry->approver ? $entry->approver->name : null,
                'posted_at' => $entry->posted_at ? $entry->posted_at->format('Y-m-d H:i:s') : null,
                'lines' => $entry->lines->map(function ($line) {
                    return [
                        'account_name' => $line->account_name,
                        'account_code' => $line->account_code,
                        'debit_amount' => $line->debit_amount,
                        'formatted_debit_amount' => $line->formatted_debit_amount,
                        'credit_amount' => $line->credit_amount,
                        'formatted_credit_amount' => $line->formatted_credit_amount,
                        'line_description' => $line->line_description,
                    ];
                }),
            ];
        })->toArray();
    }

    /**
     * Reverse a posted journal entry
     */
    public function reverseEntry(int $entryId): JournalEntry
    {
        DB::beginTransaction();

        try {
            $originalEntry = JournalEntry::with('lines.account')->findOrFail($entryId);

            if ($originalEntry->status !== 'posted') {
                throw new \Exception('Only posted entries can be reversed.');
            }

            // Create reversal entry
            $reversalEntry = $this->createEntry([
                'reference_number' => $originalEntry->reference_number . '-REV',
                'entry_date' => now()->toDateString(),
                'description' => 'Reversal of ' . $originalEntry->description,
                'total_amount' => $originalEntry->total_amount,
                'lines' => $originalEntry->lines->map(function ($line) {
                    return [
                        'account_id' => $line->account_id,
                        'debit_amount' => $line->credit_amount, // Swap debit and credit
                        'credit_amount' => $line->debit_amount,
                        'line_description' => 'Reversal of ' . $line->line_description,
                    ];
                })->toArray(),
            ]);

            // Mark original entry as reversed
            $originalEntry->status = 'reversed';
            $originalEntry->save();

            // Post the reversal entry
            $this->postEntry($reversalEntry->id);

            DB::commit();

            Log::info('Journal entry reversed', [
                'original_entry_id' => $originalEntry->id,
                'reversal_entry_id' => $reversalEntry->id,
            ]);

            return $reversalEntry;

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Failed to reverse journal entry', [
                'entry_id' => $entryId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get unbalanced entries
     */
    public function getUnbalancedEntries(): array
    {
        $unbalancedEntries = JournalEntry::getUnbalancedEntries()
            ->load(['creator', 'lines.account']);

        return $unbalancedEntries->map(function ($entry) {
            return [
                'id' => $entry->id,
                'reference_number' => $entry->reference_number,
                'entry_date' => $entry->entry_date->format('Y-m-d'),
                'description' => $entry->description,
                'total_amount' => $entry->total_amount,
                'formatted_total_amount' => $entry->formatted_total_amount,
                'debit_total' => $entry->debit_total,
                'credit_total' => $entry->credit_total,
                'balance_difference' => $entry->debit_total - $entry->credit_total,
                'formatted_balance_difference' => '₦' . number_format($entry->debit_total - $entry->credit_total, 2),
                'status' => $entry->status,
                'status_color' => $entry->status_color,
                'status_icon' => $entry->status_icon,
                'created_at' => $entry->created_at->format('Y-m-d H:i:s'),
            ];
        })->toArray();
    }
} 
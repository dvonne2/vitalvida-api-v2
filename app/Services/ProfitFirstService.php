<?php

namespace App\Services;

use App\Models\BankAccount;
use App\Models\ProfitAllocation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ProfitFirstService
{
    /**
     * Allocate revenue according to Profit First methodology
     */
    public function allocateRevenue(float $amount, string $paymentRef): array
    {
        try {
            DB::beginTransaction();

            $profitFirstAccounts = BankAccount::getProfitFirstAccounts();
            $allocations = [];
            $totalAllocated = 0;

            foreach ($profitFirstAccounts as $account) {
                $allocationAmount = ($account->allocation_percentage / 100) * $amount;
                
                if ($allocationAmount > 0) {
                    $allocation = ProfitAllocation::create([
                        'payment_reference' => $paymentRef,
                        'amount_received' => $amount,
                        'allocated_to' => $account->wallet_type,
                        'amount_allocated' => $allocationAmount,
                        'bank_account_id' => $account->id,
                        'allocation_status' => 'pending',
                    ]);

                    $allocations[] = $allocation;
                    $totalAllocated += $allocationAmount;
                }
            }

            // Validate total allocation
            if (abs($totalAllocated - $amount) > 0.01) {
                throw new \Exception('Allocation total does not match received amount');
            }

            DB::commit();

            Log::info('Revenue allocated via Profit First', [
                'payment_ref' => $paymentRef,
                'amount' => $amount,
                'allocations_count' => count($allocations),
                'total_allocated' => $totalAllocated,
            ]);

            return [
                'success' => true,
                'allocations' => $allocations,
                'total_allocated' => $totalAllocated,
                'payment_reference' => $paymentRef,
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Profit First allocation failed', [
                'payment_ref' => $paymentRef,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get current wallet balances
     */
    public function getWalletBalances(): array
    {
        $balances = [];
        $walletTypes = ['marketing', 'opex', 'inventory', 'profit', 'bonus', 'tax'];

        foreach ($walletTypes as $walletType) {
            $account = BankAccount::where('wallet_type', $walletType)->first();
            $completedAllocations = ProfitAllocation::where('allocated_to', $walletType)
                ->where('allocation_status', 'completed')
                ->sum('amount_allocated');

            $balances[$walletType] = [
                'account' => $account,
                'current_balance' => $account ? $account->current_balance : 0,
                'total_allocated' => $completedAllocations,
                'allocation_percentage' => $account ? $account->allocation_percentage : 0,
                'formatted_balance' => '₦' . number_format($account ? $account->current_balance : 0, 2),
                'formatted_allocated' => '₦' . number_format($completedAllocations, 2),
            ];
        }

        return $balances;
    }

    /**
     * Transfer funds between wallets
     */
    public function transferBetweenWallets(string $from, string $to, float $amount): bool
    {
        try {
            DB::beginTransaction();

            $fromAccount = BankAccount::where('wallet_type', $from)->first();
            $toAccount = BankAccount::where('wallet_type', $to)->first();

            if (!$fromAccount || !$toAccount) {
                throw new \Exception('Invalid wallet types');
            }

            if ($fromAccount->current_balance < $amount) {
                throw new \Exception('Insufficient funds in source wallet');
            }

            // Debit from source wallet
            $fromAccount->updateBalance(-$amount);

            // Credit to destination wallet
            $toAccount->updateBalance($amount);

            // Create journal entry for the transfer
            $this->createTransferJournalEntry($fromAccount, $toAccount, $amount);

            DB::commit();

            Log::info('Wallet transfer completed', [
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
            ]);

            return true;

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Wallet transfer failed', [
                'from' => $from,
                'to' => $to,
                'amount' => $amount,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Process pending allocations
     */
    public function processPendingAllocations(): array
    {
        $pendingAllocations = ProfitAllocation::pending()->get();
        $processed = 0;
        $failed = 0;

        foreach ($pendingAllocations as $allocation) {
            try {
                $allocation->markAsCompleted();
                $processed++;
            } catch (\Exception $e) {
                $allocation->markAsFailed($e->getMessage());
                $failed++;
            }
        }

        return [
            'processed' => $processed,
            'failed' => $failed,
            'total' => $pendingAllocations->count(),
        ];
    }

    /**
     * Get Profit First compliance report
     */
    public function getComplianceReport(): array
    {
        $balances = $this->getWalletBalances();
        $totalAllocated = array_sum(array_column($balances, 'total_allocated'));
        $expectedPercentage = BankAccount::getTotalAllocationPercentage();

        $compliance = [];
        foreach ($balances as $walletType => $balance) {
            $expectedAmount = ($balance['allocation_percentage'] / 100) * $totalAllocated;
            $actualAmount = $balance['total_allocated'];
            
            $compliance[$walletType] = [
                'expected' => $expectedAmount,
                'actual' => $actualAmount,
                'variance' => $actualAmount - $expectedAmount,
                'compliance_percentage' => $expectedAmount > 0 ? ($actualAmount / $expectedAmount) * 100 : 0,
            ];
        }

        return [
            'balances' => $balances,
            'compliance' => $compliance,
            'total_allocated' => $totalAllocated,
            'expected_percentage' => $expectedPercentage,
        ];
    }

    /**
     * Create journal entry for wallet transfer
     */
    private function createTransferJournalEntry(BankAccount $fromAccount, BankAccount $toAccount, float $amount): void
    {
        $journalEntry = \App\Models\JournalEntry::create([
            'reference_number' => \App\Models\JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->toDateString(),
            'description' => "Transfer from {$fromAccount->wallet_type} to {$toAccount->wallet_type}",
            'total_amount' => $amount,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        // Debit destination account
        $journalEntry->addLine(
            $toAccount->id,
            $amount,
            0,
            "Transfer from {$fromAccount->wallet_type}"
        );

        // Credit source account
        $journalEntry->addLine(
            $fromAccount->id,
            0,
            $amount,
            "Transfer to {$toAccount->wallet_type}"
        );

        $journalEntry->post();
    }
} 
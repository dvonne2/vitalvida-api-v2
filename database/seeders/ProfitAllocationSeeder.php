<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\ProfitAllocation;
use App\Models\BankAccount;
use Carbon\Carbon;

class ProfitAllocationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('💰 Seeding Profit Allocations...');

        // Get bank accounts for Profit First wallets
        $bankAccounts = BankAccount::getProfitFirstAccounts();
        
        if ($bankAccounts->isEmpty()) {
            $this->command->warn('No Profit First bank accounts found. Please run BankAccountSeeder first.');
            return;
        }

        // Sample revenue allocations
        $this->createSampleAllocations($bankAccounts);

        $this->command->info('✅ Profit Allocations seeded successfully!');
    }

    private function createSampleAllocations($bankAccounts)
    {
        $samplePayments = [
            [
                'payment_reference' => 'PAY-2025-001',
                'amount_received' => 500000.00, // ₦500K
                'date' => Carbon::now()->subDays(30),
            ],
            [
                'payment_reference' => 'PAY-2025-002',
                'amount_received' => 750000.00, // ₦750K
                'date' => Carbon::now()->subDays(25),
            ],
            [
                'payment_reference' => 'PAY-2025-003',
                'amount_received' => 1200000.00, // ₦1.2M
                'date' => Carbon::now()->subDays(20),
            ],
            [
                'payment_reference' => 'PAY-2025-004',
                'amount_received' => 300000.00, // ₦300K
                'date' => Carbon::now()->subDays(15),
            ],
            [
                'payment_reference' => 'PAY-2025-005',
                'amount_received' => 900000.00, // ₦900K
                'date' => Carbon::now()->subDays(10),
            ],
            [
                'payment_reference' => 'PAY-2025-006',
                'amount_received' => 600000.00, // ₦600K
                'date' => Carbon::now()->subDays(5),
            ],
            [
                'payment_reference' => 'PAY-2025-007',
                'amount_received' => 450000.00, // ₦450K
                'date' => Carbon::now()->subDays(2),
            ],
            [
                'payment_reference' => 'PAY-2025-008',
                'amount_received' => 800000.00, // ₦800K
                'date' => Carbon::now()->subDays(1),
            ],
        ];

        foreach ($samplePayments as $payment) {
            $this->createAllocationForPayment($payment, $bankAccounts);
        }
    }

    private function createAllocationForPayment($payment, $bankAccounts)
    {
        $amountReceived = $payment['amount_received'];
        $paymentRef = $payment['payment_reference'];
        $createdAt = $payment['date'];

        foreach ($bankAccounts as $account) {
            $allocationAmount = ($account->allocation_percentage / 100) * $amountReceived;
            
            if ($allocationAmount > 0) {
                // Determine allocation status (mostly completed, some pending)
                $status = rand(1, 10) <= 8 ? 'completed' : 'pending';
                $allocatedAt = $status === 'completed' ? $createdAt->copy()->addHours(rand(1, 6)) : null;

                ProfitAllocation::create([
                    'payment_reference' => $paymentRef,
                    'amount_received' => $amountReceived,
                    'allocated_to' => $account->wallet_type,
                    'amount_allocated' => $allocationAmount,
                    'bank_account_id' => $account->id,
                    'allocation_status' => $status,
                    'allocated_at' => $allocatedAt,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        }
    }
} 
<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\JournalEntry;
use App\Models\ChartOfAccount;
use App\Models\User;
use Carbon\Carbon;

class JournalEntrySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('📝 Seeding Journal Entries...');

        // Get a user for created_by field
        $user = User::first() ?? User::factory()->create();

        // Get chart of accounts
        $accounts = ChartOfAccount::all();
        
        if ($accounts->isEmpty()) {
            $this->command->warn('No chart of accounts found. Please run ChartOfAccountSeeder first.');
            return;
        }

        // Sample journal entries
        $this->createSampleJournalEntries($user, $accounts);

        $this->command->info('🎉 Journal entries seeded successfully!');
        
        // Display summary
        $draftEntries = JournalEntry::where('status', 'draft')->count();
        $postedEntries = JournalEntry::where('status', 'posted')->count();
        $reversedEntries = JournalEntry::where('status', 'reversed')->count();
        
        $this->command->info("📊 Summary: {$draftEntries} draft entries, {$postedEntries} posted entries, {$reversedEntries} reversed entries");
    }

    private function createSampleJournalEntries($user, $accounts)
    {
        // Get specific accounts for common transactions
        $cashAccount = $accounts->where('account_code', 'CASH')->first();
        $accountsReceivable = $accounts->where('account_code', 'ACCOUNTS-REC')->first();
        $salesRevenue = $accounts->where('account_code', 'SALES-REV')->first();
        $cogsAccount = $accounts->where('account_code', 'COGS')->first();
        $inventoryAccount = $accounts->where('account_code', 'INVENTORY')->first();
        $accountsPayable = $accounts->where('account_code', 'ACCOUNTS-PAY')->first();
        $marketingExpense = $accounts->where('account_code', 'MARKETING-EXP')->first();
        $salaryExpense = $accounts->where('account_code', 'SALARY-EXP')->first();
        $rentExpense = $accounts->where('account_code', 'RENT-EXP')->first();
        $utilitiesExpense = $accounts->where('account_code', 'UTILITIES-EXP')->first();

        // 1. Sales Transaction
        if ($cashAccount && $salesRevenue) {
            $this->createSalesEntry($user, $cashAccount, $salesRevenue);
        }

        // 2. Purchase Transaction
        if ($inventoryAccount && $accountsPayable) {
            $this->createPurchaseEntry($user, $inventoryAccount, $accountsPayable);
        }

        // 3. Expense Transaction
        if ($cashAccount && $marketingExpense) {
            $this->createExpenseEntry($user, $cashAccount, $marketingExpense);
        }

        // 4. Salary Payment
        if ($cashAccount && $salaryExpense) {
            $this->createSalaryEntry($user, $cashAccount, $salaryExpense);
        }

        // 5. Rent Payment
        if ($cashAccount && $rentExpense) {
            $this->createRentEntry($user, $cashAccount, $rentExpense);
        }

        // 6. Utilities Payment
        if ($cashAccount && $utilitiesExpense) {
            $this->createUtilitiesEntry($user, $cashAccount, $utilitiesExpense);
        }

        // 7. Credit Sale
        if ($accountsReceivable && $salesRevenue) {
            $this->createCreditSaleEntry($user, $accountsReceivable, $salesRevenue);
        }

        // 8. Cost of Goods Sold
        if ($cogsAccount && $inventoryAccount) {
            $this->createCOGSEntry($user, $cogsAccount, $inventoryAccount);
        }
    }

    private function createSalesEntry($user, $cashAccount, $salesRevenue)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->subDays(5)->toDateString(),
            'description' => 'Cash sale of products',
            'total_amount' => 50000.00,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now()->subDays(5),
        ]);

        // Debit Cash
        $entry->addLine($cashAccount->id, 50000.00, 0, 'Cash received from sales');
        
        // Credit Sales Revenue
        $entry->addLine($salesRevenue->id, 0, 50000.00, 'Sales revenue recorded');

        $this->command->info("✅ Created sales journal entry: {$entry->reference_number}");
    }

    private function createPurchaseEntry($user, $inventoryAccount, $accountsPayable)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->subDays(3)->toDateString(),
            'description' => 'Purchase of inventory on credit',
            'total_amount' => 75000.00,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now()->subDays(3),
        ]);

        // Debit Inventory
        $entry->addLine($inventoryAccount->id, 75000.00, 0, 'Inventory purchased');
        
        // Credit Accounts Payable
        $entry->addLine($accountsPayable->id, 0, 75000.00, 'Amount owed to supplier');

        $this->command->info("✅ Created purchase journal entry: {$entry->reference_number}");
    }

    private function createExpenseEntry($user, $cashAccount, $marketingExpense)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->subDays(2)->toDateString(),
            'description' => 'Marketing expense payment',
            'total_amount' => 25000.00,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now()->subDays(2),
        ]);

        // Debit Marketing Expense
        $entry->addLine($marketingExpense->id, 25000.00, 0, 'Marketing campaign expense');
        
        // Credit Cash
        $entry->addLine($cashAccount->id, 0, 25000.00, 'Cash paid for marketing');

        $this->command->info("✅ Created expense journal entry: {$entry->reference_number}");
    }

    private function createSalaryEntry($user, $cashAccount, $salaryExpense)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->subDays(1)->toDateString(),
            'description' => 'Salary payment for employees',
            'total_amount' => 150000.00,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now()->subDays(1),
        ]);

        // Debit Salary Expense
        $entry->addLine($salaryExpense->id, 150000.00, 0, 'Employee salary expense');
        
        // Credit Cash
        $entry->addLine($cashAccount->id, 0, 150000.00, 'Cash paid for salaries');

        $this->command->info("✅ Created salary journal entry: {$entry->reference_number}");
    }

    private function createRentEntry($user, $cashAccount, $rentExpense)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->toDateString(),
            'description' => 'Office rent payment',
            'total_amount' => 100000.00,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // Debit Rent Expense
        $entry->addLine($rentExpense->id, 100000.00, 0, 'Office rent expense');
        
        // Credit Cash
        $entry->addLine($cashAccount->id, 0, 100000.00, 'Cash paid for rent');

        $this->command->info("✅ Created rent journal entry: {$entry->reference_number} (draft)");
    }

    private function createUtilitiesEntry($user, $cashAccount, $utilitiesExpense)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->toDateString(),
            'description' => 'Utilities payment',
            'total_amount' => 15000.00,
            'status' => 'draft',
            'created_by' => $user->id,
        ]);

        // Debit Utilities Expense
        $entry->addLine($utilitiesExpense->id, 15000.00, 0, 'Utilities expense');
        
        // Credit Cash
        $entry->addLine($cashAccount->id, 0, 15000.00, 'Cash paid for utilities');

        $this->command->info("✅ Created utilities journal entry: {$entry->reference_number} (draft)");
    }

    private function createCreditSaleEntry($user, $accountsReceivable, $salesRevenue)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->subDays(4)->toDateString(),
            'description' => 'Credit sale to customer',
            'total_amount' => 35000.00,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now()->subDays(4),
        ]);

        // Debit Accounts Receivable
        $entry->addLine($accountsReceivable->id, 35000.00, 0, 'Amount owed by customer');
        
        // Credit Sales Revenue
        $entry->addLine($salesRevenue->id, 0, 35000.00, 'Credit sale revenue');

        $this->command->info("✅ Created credit sale journal entry: {$entry->reference_number}");
    }

    private function createCOGSEntry($user, $cogsAccount, $inventoryAccount)
    {
        $entry = JournalEntry::create([
            'reference_number' => JournalEntry::generateReferenceNumber(),
            'entry_date' => now()->subDays(1)->toDateString(),
            'description' => 'Cost of goods sold adjustment',
            'total_amount' => 40000.00,
            'status' => 'posted',
            'created_by' => $user->id,
            'posted_at' => now()->subDays(1),
        ]);

        // Debit Cost of Goods Sold
        $entry->addLine($cogsAccount->id, 40000.00, 0, 'Cost of goods sold');
        
        // Credit Inventory
        $entry->addLine($inventoryAccount->id, 0, 40000.00, 'Inventory reduction');

        $this->command->info("✅ Created COGS journal entry: {$entry->reference_number}");
    }
} 
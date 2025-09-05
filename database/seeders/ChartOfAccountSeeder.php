<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\ChartOfAccount;

class ChartOfAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create parent accounts first
        $this->createParentAccounts();
        
        // Create Nigerian tax compliance accounts
        $this->createTaxAccounts();
        
        // Create revenue accounts
        $this->createRevenueAccounts();
        
        // Create expense accounts
        $this->createExpenseAccounts();
        
        // Create asset accounts
        $this->createAssetAccounts();
        
        // Create liability accounts
        $this->createLiabilityAccounts();
        
        // Create equity accounts
        $this->createEquityAccounts();
    }

    private function createParentAccounts()
    {
        $parents = [
            ['account_code' => '1000', 'account_name' => 'Current Assets', 'account_type' => 'Asset', 'reporting_group' => 'Assets'],
            ['account_code' => '2000', 'account_name' => 'Fixed Assets', 'account_type' => 'Asset', 'reporting_group' => 'Assets'],
            ['account_code' => '3000', 'account_name' => 'Current Liabilities', 'account_type' => 'Liability', 'reporting_group' => 'Liabilities'],
            ['account_code' => '4000', 'account_name' => 'Long-term Liabilities', 'account_type' => 'Liability', 'reporting_group' => 'Liabilities'],
            ['account_code' => '5000', 'account_name' => 'Revenue', 'account_type' => 'Income', 'reporting_group' => 'Revenue'],
            ['account_code' => '6000', 'account_name' => 'Operating Expenses', 'account_type' => 'Expense', 'reporting_group' => 'Expenses'],
            ['account_code' => '7000', 'account_name' => 'Equity', 'account_type' => 'Equity', 'reporting_group' => 'Equity'],
        ];

        foreach ($parents as $parent) {
            ChartOfAccount::create($parent);
        }
    }

    private function createTaxAccounts()
    {
        $taxAccounts = [
            [
                'account_code' => 'VAT-PAYABLE',
                'account_name' => 'Value Added Tax Payable',
                'account_type' => 'Liability',
                'reporting_group' => 'Tax Liabilities',
                'description' => 'Nigerian VAT payable to FIRS (7.5%)',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'PAYE-PAYABLE',
                'account_name' => 'PAYE Tax Payable',
                'account_type' => 'Liability',
                'reporting_group' => 'Tax Liabilities',
                'description' => 'Pay As You Earn tax payable to LIRS',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'CIT-PAYABLE',
                'account_name' => 'Company Income Tax Payable',
                'account_type' => 'Liability',
                'reporting_group' => 'Tax Liabilities',
                'description' => 'Company Income Tax payable to FIRS (30%)',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'EDT-PAYABLE',
                'account_name' => 'Education Tax Payable',
                'account_type' => 'Liability',
                'reporting_group' => 'Tax Liabilities',
                'description' => 'Education Tax payable to TETFUND (2%)',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'WHT-PAYABLE',
                'account_name' => 'Withholding Tax Payable',
                'account_type' => 'Liability',
                'reporting_group' => 'Tax Liabilities',
                'description' => 'Withholding Tax payable to FIRS',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'VAT-RECEIVABLE',
                'account_name' => 'Value Added Tax Receivable',
                'account_type' => 'Asset',
                'reporting_group' => 'Tax Assets',
                'description' => 'VAT receivable from customers',
                'parent_account_id' => ChartOfAccount::where('account_code', '1000')->first()->id,
            ],
        ];

        foreach ($taxAccounts as $account) {
            ChartOfAccount::create($account);
        }
    }

    private function createRevenueAccounts()
    {
        $revenueAccounts = [
            [
                'account_code' => 'SALES-REV',
                'account_name' => 'Sales Revenue',
                'account_type' => 'Income',
                'reporting_group' => 'Revenue',
                'description' => 'Primary revenue from book sales',
                'parent_account_id' => ChartOfAccount::where('account_code', '5000')->first()->id,
            ],
            [
                'account_code' => 'SHIPPING-REV',
                'account_name' => 'Shipping Revenue',
                'account_type' => 'Income',
                'reporting_group' => 'Revenue',
                'description' => 'Revenue from delivery charges',
                'parent_account_id' => ChartOfAccount::where('account_code', '5000')->first()->id,
            ],
            [
                'account_code' => 'AFFILIATE-REV',
                'account_name' => 'Affiliate Revenue',
                'account_type' => 'Income',
                'reporting_group' => 'Revenue',
                'description' => 'Commission from affiliate sales',
                'parent_account_id' => ChartOfAccount::where('account_code', '5000')->first()->id,
            ],
        ];

        foreach ($revenueAccounts as $account) {
            ChartOfAccount::create($account);
        }
    }

    private function createExpenseAccounts()
    {
        $expenseAccounts = [
            [
                'account_code' => 'COGS',
                'account_name' => 'Cost of Goods Sold',
                'account_type' => 'Expense',
                'reporting_group' => 'Cost of Sales',
                'description' => 'Direct costs of book inventory',
                'parent_account_id' => ChartOfAccount::where('account_code', '6000')->first()->id,
            ],
            [
                'account_code' => 'MARKETING-EXP',
                'account_name' => 'Marketing Expenses',
                'account_type' => 'Expense',
                'reporting_group' => 'Operating Expenses',
                'description' => 'Digital marketing and advertising costs',
                'parent_account_id' => ChartOfAccount::where('account_code', '6000')->first()->id,
            ],
            [
                'account_code' => 'SALARY-EXP',
                'account_name' => 'Salary Expenses',
                'account_type' => 'Expense',
                'reporting_group' => 'Operating Expenses',
                'description' => 'Employee salaries and wages',
                'parent_account_id' => ChartOfAccount::where('account_code', '6000')->first()->id,
            ],
            [
                'account_code' => 'RENT-EXP',
                'account_name' => 'Rent Expenses',
                'account_type' => 'Expense',
                'reporting_group' => 'Operating Expenses',
                'description' => 'Office and warehouse rent',
                'parent_account_id' => ChartOfAccount::where('account_code', '6000')->first()->id,
            ],
            [
                'account_code' => 'UTILITIES-EXP',
                'account_name' => 'Utilities Expenses',
                'account_type' => 'Expense',
                'reporting_group' => 'Operating Expenses',
                'description' => 'Electricity, internet, phone expenses',
                'parent_account_id' => ChartOfAccount::where('account_code', '6000')->first()->id,
            ],
            [
                'account_code' => 'INSURANCE-EXP',
                'account_name' => 'Insurance Expenses',
                'account_type' => 'Expense',
                'reporting_group' => 'Operating Expenses',
                'description' => 'Business insurance premiums',
                'parent_account_id' => ChartOfAccount::where('account_code', '6000')->first()->id,
            ],
        ];

        foreach ($expenseAccounts as $account) {
            ChartOfAccount::create($account);
        }
    }

    private function createAssetAccounts()
    {
        $assetAccounts = [
            [
                'account_code' => 'CASH',
                'account_name' => 'Cash and Cash Equivalents',
                'account_type' => 'Asset',
                'reporting_group' => 'Current Assets',
                'description' => 'Cash in bank accounts and petty cash',
                'parent_account_id' => ChartOfAccount::where('account_code', '1000')->first()->id,
            ],
            [
                'account_code' => 'ACCOUNTS-REC',
                'account_name' => 'Accounts Receivable',
                'account_type' => 'Asset',
                'reporting_group' => 'Current Assets',
                'description' => 'Amounts owed by customers',
                'parent_account_id' => ChartOfAccount::where('account_code', '1000')->first()->id,
            ],
            [
                'account_code' => 'INVENTORY',
                'account_name' => 'Inventory',
                'account_type' => 'Asset',
                'reporting_group' => 'Current Assets',
                'description' => 'Book inventory at cost',
                'parent_account_id' => ChartOfAccount::where('account_code', '1000')->first()->id,
            ],
            [
                'account_code' => 'PREPAID-EXP',
                'account_name' => 'Prepaid Expenses',
                'account_type' => 'Asset',
                'reporting_group' => 'Current Assets',
                'description' => 'Prepaid rent, insurance, etc.',
                'parent_account_id' => ChartOfAccount::where('account_code', '1000')->first()->id,
            ],
        ];

        foreach ($assetAccounts as $account) {
            ChartOfAccount::create($account);
        }
    }

    private function createLiabilityAccounts()
    {
        $liabilityAccounts = [
            [
                'account_code' => 'ACCOUNTS-PAY',
                'account_name' => 'Accounts Payable',
                'account_type' => 'Liability',
                'reporting_group' => 'Current Liabilities',
                'description' => 'Amounts owed to suppliers',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'ACCRUED-EXP',
                'account_name' => 'Accrued Expenses',
                'account_type' => 'Liability',
                'reporting_group' => 'Current Liabilities',
                'description' => 'Accrued salaries, utilities, etc.',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
            [
                'account_code' => 'DEFERRED-REV',
                'account_name' => 'Deferred Revenue',
                'account_type' => 'Liability',
                'reporting_group' => 'Current Liabilities',
                'description' => 'Advance payments from customers',
                'parent_account_id' => ChartOfAccount::where('account_code', '3000')->first()->id,
            ],
        ];

        foreach ($liabilityAccounts as $account) {
            ChartOfAccount::create($account);
        }
    }

    private function createEquityAccounts()
    {
        $equityAccounts = [
            [
                'account_code' => 'OWNER-EQUITY',
                'account_name' => 'Owner\'s Equity',
                'account_type' => 'Equity',
                'reporting_group' => 'Equity',
                'description' => 'Owner\'s capital investment',
                'parent_account_id' => ChartOfAccount::where('account_code', '7000')->first()->id,
            ],
            [
                'account_code' => 'RETAINED-EARN',
                'account_name' => 'Retained Earnings',
                'account_type' => 'Equity',
                'reporting_group' => 'Equity',
                'description' => 'Accumulated profits',
                'parent_account_id' => ChartOfAccount::where('account_code', '7000')->first()->id,
            ],
        ];

        foreach ($equityAccounts as $account) {
            ChartOfAccount::create($account);
        }
    }
}

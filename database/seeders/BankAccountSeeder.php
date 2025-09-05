<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\BankAccount;
use App\Models\User;

class BankAccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get a user for created_by field
        $user = User::first() ?? User::factory()->create();

        // Create Moniepoint accounts with Profit First allocation
        $this->createMoniepointAccounts($user->id);
        
        // Create traditional bank accounts
        $this->createTraditionalBankAccounts($user->id);
    }

    private function createMoniepointAccounts($userId)
    {
        $moniepointAccounts = [
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '1234567890',
                'account_name' => 'Vitalvida Books - Main Account',
                'account_code' => 'MON-MAIN',
                'wallet_type' => 'main',
                'allocation_percentage' => 0.00, // Main account doesn't get allocation
                'current_balance' => 5000000.00, // ₦5M starting balance
                'status' => 'active',
                'purpose_description' => 'Primary business account for all incoming revenue',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 10000000, // ₦10M daily limit
                    'monthly' => 100000000, // ₦100M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '2345678901',
                'account_name' => 'Vitalvida Books - Profit Account',
                'account_code' => 'MON-PROFIT',
                'wallet_type' => 'profit',
                'allocation_percentage' => 20.00, // 20% of revenue
                'current_balance' => 1000000.00, // ₦1M starting balance
                'status' => 'active',
                'purpose_description' => 'Profit First - Owner distributions and business growth',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 5000000, // ₦5M daily limit
                    'monthly' => 50000000, // ₦50M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '3456789012',
                'account_name' => 'Vitalvida Books - Tax Account',
                'account_code' => 'MON-TAX',
                'wallet_type' => 'tax',
                'allocation_percentage' => 15.00, // 15% of revenue
                'current_balance' => 750000.00, // ₦750K starting balance
                'status' => 'active',
                'purpose_description' => 'Tax First - VAT, PAYE, CIT, EDT, WHT payments',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 2000000, // ₦2M daily limit
                    'monthly' => 20000000, // ₦20M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '4567890123',
                'account_name' => 'Vitalvida Books - Marketing Account',
                'account_code' => 'MON-MARKETING',
                'wallet_type' => 'marketing',
                'allocation_percentage' => 25.00, // 25% of revenue
                'current_balance' => 1250000.00, // ₦1.25M starting balance
                'status' => 'active',
                'purpose_description' => 'Marketing First - Digital ads, SEO, content marketing',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 3000000, // ₦3M daily limit
                    'monthly' => 30000000, // ₦30M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '5678901234',
                'account_name' => 'Vitalvida Books - Opex Account',
                'account_code' => 'MON-OPEX',
                'wallet_type' => 'opex',
                'allocation_percentage' => 25.00, // 25% of revenue
                'current_balance' => 1250000.00, // ₦1.25M starting balance
                'status' => 'active',
                'purpose_description' => 'Operating Expenses - Rent, salaries, utilities, insurance',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 2000000, // ₦2M daily limit
                    'monthly' => 20000000, // ₦20M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '6789012345',
                'account_name' => 'Vitalvida Books - Inventory Account',
                'account_code' => 'MON-INVENTORY',
                'wallet_type' => 'inventory',
                'allocation_percentage' => 10.00, // 10% of revenue
                'current_balance' => 500000.00, // ₦500K starting balance
                'status' => 'active',
                'purpose_description' => 'Inventory First - Book purchases, stock management',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 1000000, // ₦1M daily limit
                    'monthly' => 10000000, // ₦10M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Moniepoint',
                'account_number' => '7890123456',
                'account_name' => 'Vitalvida Books - Bonus Account',
                'account_code' => 'MON-BONUS',
                'wallet_type' => 'bonus',
                'allocation_percentage' => 5.00, // 5% of revenue
                'current_balance' => 250000.00, // ₦250K starting balance
                'status' => 'active',
                'purpose_description' => 'Bonus First - Employee bonuses, incentives, rewards',
                'api_key' => 'mp_test_key_123456',
                'webhook_url' => 'https://vitalvida.com/webhooks/moniepoint',
                'transaction_limits' => [
                    'daily' => 500000, // ₦500K daily limit
                    'monthly' => 5000000, // ₦5M monthly limit
                ],
                'created_by' => $userId,
            ],
        ];

        foreach ($moniepointAccounts as $account) {
            BankAccount::create($account);
        }
    }

    private function createTraditionalBankAccounts($userId)
    {
        $traditionalAccounts = [
            [
                'bank_name' => 'First Bank of Nigeria',
                'account_number' => '1234567890',
                'account_name' => 'Vitalvida Books - Backup Account',
                'account_code' => 'FBN-BACKUP',
                'wallet_type' => 'main',
                'allocation_percentage' => 0.00,
                'current_balance' => 1000000.00, // ₦1M backup
                'status' => 'active',
                'purpose_description' => 'Backup account for emergency funds and large transactions',
                'api_key' => null,
                'webhook_url' => null,
                'transaction_limits' => [
                    'daily' => 5000000, // ₦5M daily limit
                    'monthly' => 50000000, // ₦50M monthly limit
                ],
                'created_by' => $userId,
            ],
            [
                'bank_name' => 'Access Bank',
                'account_number' => '9876543210',
                'account_name' => 'Vitalvida Books - Tax Reserve',
                'account_code' => 'ACCESS-TAX',
                'wallet_type' => 'tax',
                'allocation_percentage' => 0.00, // No allocation, manual transfers
                'current_balance' => 500000.00, // ₦500K tax reserve
                'status' => 'active',
                'purpose_description' => 'Tax reserve account for quarterly tax payments',
                'api_key' => null,
                'webhook_url' => null,
                'transaction_limits' => [
                    'daily' => 1000000, // ₦1M daily limit
                    'monthly' => 10000000, // ₦10M monthly limit
                ],
                'created_by' => $userId,
            ],
        ];

        foreach ($traditionalAccounts as $account) {
            BankAccount::create($account);
        }
    }
}

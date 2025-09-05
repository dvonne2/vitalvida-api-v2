<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Models\Budget;
use App\Models\User;

class BudgetSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get a user for created_by field
        $user = User::first() ?? User::factory()->create();

        // Create budgets for different departments
        $this->createMarketingBudgets($user->id);
        $this->createOperationsBudgets($user->id);
        $this->createHRBudgets($user->id);
        $this->createFinanceBudgets($user->id);
        $this->createTechnologyBudgets($user->id);
    }

    private function createMarketingBudgets($userId)
    {
        $marketingBudgets = [
            [
                'department' => 'Marketing',
                'fiscal_year' => '2025',
                'month' => '2025-01',
                'budget_amount' => 2500000.00, // ₦2.5M
                'actual_amount' => 2300000.00, // ₦2.3M
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Digital marketing campaigns and social media advertising',
                'budget_categories' => [
                    'google_ads' => 800000,
                    'facebook_ads' => 600000,
                    'content_marketing' => 400000,
                    'seo' => 300000,
                    'email_marketing' => 200000,
                    'influencer_marketing' => 200000,
                ],
            ],
            [
                'department' => 'Marketing',
                'fiscal_year' => '2025',
                'month' => '2025-02',
                'budget_amount' => 2800000.00, // ₦2.8M
                'actual_amount' => 0.00,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Valentine\'s Day campaign and book launch promotions',
                'budget_categories' => [
                    'google_ads' => 900000,
                    'facebook_ads' => 700000,
                    'content_marketing' => 450000,
                    'seo' => 300000,
                    'email_marketing' => 250000,
                    'influencer_marketing' => 200000,
                ],
            ],
        ];

        foreach ($marketingBudgets as $budget) {
            $budgetModel = Budget::create($budget);
            $budgetModel->calculateVariance();
        }
    }

    private function createOperationsBudgets($userId)
    {
        $operationsBudgets = [
            [
                'department' => 'Operations',
                'fiscal_year' => '2025',
                'month' => '2025-01',
                'budget_amount' => 1800000.00, // ₦1.8M
                'actual_amount' => 1750000.00, // ₦1.75M
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Warehouse operations, logistics, and delivery costs',
                'budget_categories' => [
                    'warehouse_rent' => 500000,
                    'delivery_costs' => 400000,
                    'packaging_materials' => 200000,
                    'equipment_maintenance' => 150000,
                    'utilities' => 300000,
                    'insurance' => 250000,
                ],
            ],
            [
                'department' => 'Operations',
                'fiscal_year' => '2025',
                'month' => '2025-02',
                'budget_amount' => 1900000.00, // ₦1.9M
                'actual_amount' => 0.00,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Expanded operations for increased order volume',
                'budget_categories' => [
                    'warehouse_rent' => 500000,
                    'delivery_costs' => 450000,
                    'packaging_materials' => 220000,
                    'equipment_maintenance' => 150000,
                    'utilities' => 320000,
                    'insurance' => 260000,
                ],
            ],
        ];

        foreach ($operationsBudgets as $budget) {
            $budgetModel = Budget::create($budget);
            $budgetModel->calculateVariance();
        }
    }

    private function createHRBudgets($userId)
    {
        $hrBudgets = [
            [
                'department' => 'Human Resources',
                'fiscal_year' => '2025',
                'month' => '2025-01',
                'budget_amount' => 1200000.00, // ₦1.2M
                'actual_amount' => 1180000.00, // ₦1.18M
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Salaries, benefits, and HR operations',
                'budget_categories' => [
                    'salaries' => 800000,
                    'benefits' => 200000,
                    'training' => 100000,
                    'recruitment' => 50000,
                    'hr_software' => 30000,
                    'employee_events' => 20000,
                ],
            ],
            [
                'department' => 'Human Resources',
                'fiscal_year' => '2025',
                'month' => '2025-02',
                'budget_amount' => 1250000.00, // ₦1.25M
                'actual_amount' => 0.00,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'New hires and performance bonuses',
                'budget_categories' => [
                    'salaries' => 850000,
                    'benefits' => 200000,
                    'training' => 100000,
                    'recruitment' => 60000,
                    'hr_software' => 30000,
                    'employee_events' => 10000,
                ],
            ],
        ];

        foreach ($hrBudgets as $budget) {
            $budgetModel = Budget::create($budget);
            $budgetModel->calculateVariance();
        }
    }

    private function createFinanceBudgets($userId)
    {
        $financeBudgets = [
            [
                'department' => 'Finance',
                'fiscal_year' => '2025',
                'month' => '2025-01',
                'budget_amount' => 800000.00, // ₦800K
                'actual_amount' => 780000.00, // ₦780K
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Accounting software, tax preparation, and financial reporting',
                'budget_categories' => [
                    'accounting_software' => 200000,
                    'tax_preparation' => 150000,
                    'audit_fees' => 100000,
                    'banking_fees' => 50000,
                    'insurance_premiums' => 200000,
                    'legal_fees' => 100000,
                ],
            ],
            [
                'department' => 'Finance',
                'fiscal_year' => '2025',
                'month' => '2025-02',
                'budget_amount' => 850000.00, // ₦850K
                'actual_amount' => 0.00,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Q1 tax filing and financial planning',
                'budget_categories' => [
                    'accounting_software' => 200000,
                    'tax_preparation' => 200000,
                    'audit_fees' => 100000,
                    'banking_fees' => 50000,
                    'insurance_premiums' => 200000,
                    'legal_fees' => 100000,
                ],
            ],
        ];

        foreach ($financeBudgets as $budget) {
            $budgetModel = Budget::create($budget);
            $budgetModel->calculateVariance();
        }
    }

    private function createTechnologyBudgets($userId)
    {
        $technologyBudgets = [
            [
                'department' => 'Technology',
                'fiscal_year' => '2025',
                'month' => '2025-01',
                'budget_amount' => 600000.00, // ₦600K
                'actual_amount' => 580000.00, // ₦580K
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'Website maintenance, hosting, and IT infrastructure',
                'budget_categories' => [
                    'website_hosting' => 100000,
                    'software_licenses' => 150000,
                    'it_equipment' => 100000,
                    'cybersecurity' => 80000,
                    'cloud_services' => 120000,
                    'technical_support' => 50000,
                ],
            ],
            [
                'department' => 'Technology',
                'fiscal_year' => '2025',
                'month' => '2025-02',
                'budget_amount' => 650000.00, // ₦650K
                'actual_amount' => 0.00,
                'status' => 'approved',
                'created_by' => $userId,
                'approved_by' => $userId,
                'notes' => 'E-commerce platform upgrades and security enhancements',
                'budget_categories' => [
                    'website_hosting' => 100000,
                    'software_licenses' => 150000,
                    'it_equipment' => 120000,
                    'cybersecurity' => 100000,
                    'cloud_services' => 150000,
                    'technical_support' => 30000,
                ],
            ],
        ];

        foreach ($technologyBudgets as $budget) {
            $budgetModel = Budget::create($budget);
            $budgetModel->calculateVariance();
        }
    }
}

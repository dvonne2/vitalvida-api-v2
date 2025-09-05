<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxOptimizationStrategy;

class TaxOptimizationStrategySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $strategies = [
            [
                'strategy_name' => 'Bonus Accrual for CIT Reduction',
                'description' => 'Accrue employee bonuses before year-end to reduce taxable profit and CIT liability. This strategy leverages the timing difference between when bonuses are earned and when they are paid.',
                'potential_savings' => 1200000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(3),
            ],
            [
                'strategy_name' => 'Accelerated Asset Depreciation',
                'description' => 'Utilize accelerated depreciation methods for qualifying assets to reduce taxable income in early years. This provides immediate tax savings while deferring tax liability.',
                'potential_savings' => 1250000,
                'implementation_status' => 'available',
                'difficulty_level' => 'low',
                'deadline' => now()->addMonths(6),
            ],
            [
                'strategy_name' => 'Training & Development Expenses',
                'description' => 'Increase investment in employee training and development programs. These expenses are fully deductible and can reduce CIT liability while improving workforce capabilities.',
                'potential_savings' => 450000,
                'implementation_status' => 'available',
                'difficulty_level' => 'low',
                'deadline' => now()->addMonths(2),
            ],
            [
                'strategy_name' => 'Research & Development Tax Credits',
                'description' => 'Identify and claim R&D tax credits for qualifying activities. This can provide significant tax savings for innovative business activities.',
                'potential_savings' => 800000,
                'implementation_status' => 'available',
                'difficulty_level' => 'high',
                'deadline' => now()->addMonths(4),
            ],
            [
                'strategy_name' => 'Inventory Valuation Optimization',
                'description' => 'Optimize inventory valuation methods to reduce taxable income. Consider LIFO method for rising costs or specific identification for high-value items.',
                'potential_savings' => 600000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(1),
            ],
            [
                'strategy_name' => 'Charitable Donations Strategy',
                'description' => 'Increase charitable donations to qualifying organizations. These donations are tax-deductible and can reduce CIT liability while supporting social causes.',
                'potential_savings' => 300000,
                'implementation_status' => 'available',
                'difficulty_level' => 'low',
                'deadline' => now()->addMonths(2),
            ],
            [
                'strategy_name' => 'Energy Efficiency Tax Incentives',
                'description' => 'Invest in energy-efficient equipment and renewable energy systems. These investments may qualify for tax incentives and accelerated depreciation.',
                'potential_savings' => 750000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(5),
            ],
            [
                'strategy_name' => 'Export Promotion Tax Benefits',
                'description' => 'Optimize export activities to qualify for export promotion tax benefits. This can include reduced tax rates and special deductions for export-related expenses.',
                'potential_savings' => 900000,
                'implementation_status' => 'available',
                'difficulty_level' => 'high',
                'deadline' => now()->addMonths(3),
            ],
            [
                'strategy_name' => 'Small Business Tax Concessions',
                'description' => 'Structure business operations to qualify for small business tax concessions. This may include reduced tax rates and simplified compliance requirements.',
                'potential_savings' => 500000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(4),
            ],
            [
                'strategy_name' => 'Tax Loss Harvesting',
                'description' => 'Strategically realize capital losses to offset capital gains and reduce overall tax liability. This involves careful timing of asset sales.',
                'potential_savings' => 400000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(2),
            ],
            [
                'strategy_name' => 'Employee Stock Option Plans',
                'description' => 'Implement employee stock option plans to provide tax-efficient compensation. This can reduce payroll taxes and provide employee retention benefits.',
                'potential_savings' => 650000,
                'implementation_status' => 'available',
                'difficulty_level' => 'high',
                'deadline' => now()->addMonths(6),
            ],
            [
                'strategy_name' => 'Digital Services Tax Optimization',
                'description' => 'Optimize digital services revenue streams to minimize tax liability. This includes proper classification of digital services and related expenses.',
                'potential_savings' => 350000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(3),
            ],
            [
                'strategy_name' => 'Intercompany Transfer Pricing',
                'description' => 'Optimize intercompany transactions to ensure arm\'s length pricing and minimize tax exposure. This requires proper documentation and compliance.',
                'potential_savings' => 1100000,
                'implementation_status' => 'available',
                'difficulty_level' => 'high',
                'deadline' => now()->addMonths(4),
            ],
            [
                'strategy_name' => 'Tax Treaty Benefits',
                'description' => 'Leverage tax treaty benefits for international transactions. This can reduce withholding taxes and provide other tax advantages.',
                'potential_savings' => 700000,
                'implementation_status' => 'available',
                'difficulty_level' => 'high',
                'deadline' => now()->addMonths(5),
            ],
            [
                'strategy_name' => 'Deferred Tax Asset Optimization',
                'description' => 'Optimize deferred tax assets to maximize future tax benefits. This involves careful planning of timing differences and tax attributes.',
                'potential_savings' => 550000,
                'implementation_status' => 'available',
                'difficulty_level' => 'medium',
                'deadline' => now()->addMonths(3),
            ],
        ];

        foreach ($strategies as $strategy) {
            TaxOptimizationStrategy::create($strategy);
        }

        $this->command->info('Tax optimization strategies seeded successfully!');
        $this->command->info('Total potential savings: ₦' . number_format(collect($strategies)->sum('potential_savings'), 2));
    }
}

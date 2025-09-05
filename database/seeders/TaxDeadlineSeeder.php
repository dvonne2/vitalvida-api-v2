<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\TaxDeadline;

class TaxDeadlineSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $deadlines = [
            [
                'tax_type' => 'VAT',
                'filing_frequency' => 'monthly',
                'due_day' => 21,
                'due_month' => null,
                'description' => 'Value Added Tax (VAT) monthly filing deadline - 21st of the following month',
                'is_active' => true,
            ],
            [
                'tax_type' => 'PAYE',
                'filing_frequency' => 'monthly',
                'due_day' => 10,
                'due_month' => null,
                'description' => 'Pay As You Earn (PAYE) monthly filing deadline - 10th of the following month',
                'is_active' => true,
            ],
            [
                'tax_type' => 'WHT',
                'filing_frequency' => 'monthly',
                'due_day' => 21,
                'due_month' => null,
                'description' => 'Withholding Tax (WHT) monthly filing deadline - 21st of the following month',
                'is_active' => true,
            ],
            [
                'tax_type' => 'CIT',
                'filing_frequency' => 'annual',
                'due_day' => 31,
                'due_month' => '3_months_after_year_end',
                'description' => 'Company Income Tax (CIT) annual filing deadline - 3 months after year end',
                'is_active' => true,
            ],
            [
                'tax_type' => 'EDT',
                'filing_frequency' => 'annual',
                'due_day' => 31,
                'due_month' => '3_months_after_year_end',
                'description' => 'Education Tax (EDT) annual filing deadline - 3 months after year end',
                'is_active' => true,
            ],
        ];

        foreach ($deadlines as $deadline) {
            TaxDeadline::create($deadline);
        }

        $this->command->info('Tax deadlines seeded successfully!');
    }
}

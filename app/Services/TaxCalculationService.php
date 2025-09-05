<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class TaxCalculationService
{
    /**
     * Nigerian tax rates and thresholds for 2024
     */
    private const TAX_BRACKETS_2024 = [
        ['min' => 0, 'max' => 300000, 'rate' => 0.07], // 7% for first ₦300,000
        ['min' => 300000, 'max' => 600000, 'rate' => 0.11], // 11% for next ₦300,000
        ['min' => 600000, 'max' => 1100000, 'rate' => 0.15], // 15% for next ₦500,000
        ['min' => 1100000, 'max' => 1600000, 'rate' => 0.19], // 19% for next ₦500,000
        ['min' => 1600000, 'max' => 3200000, 'rate' => 0.21], // 21% for next ₦1,600,000
        ['min' => 3200000, 'max' => PHP_FLOAT_MAX, 'rate' => 0.24] // 24% for income above ₦3.2M
    ];

    private const PENSION_RATES = [
        'employee_contribution' => 0.08, // 8% employee contribution
        'employer_contribution' => 0.10, // 10% employer contribution
        'max_contribution_base' => 510000 // Maximum monthly salary for pension calculation (₦510,000)
    ];

    private const NHF_RATES = [
        'employee_contribution' => 0.025, // 2.5% National Housing Fund
        'employer_contribution' => 0.025, // 2.5% employer contribution
        'max_contribution_base' => 100000 // Maximum monthly salary for NHF (₦100,000)
    ];

    private const NSITF_RATE = 0.01; // 1% NSITF (employer only)

    private const TAX_RELIEFS = [
        'gross_income_relief' => 200000, // Annual gross income relief
        'consolidated_relief_allowance' => 200000, // Annual CRA
        'pension_relief_rate' => 0.08, // 8% of gross income or ₦500,000 whichever is lower
        'max_pension_relief' => 500000, // Maximum annual pension relief
        'nhf_relief_rate' => 0.025, // 2.5% of gross income
        'max_nhf_relief' => 100000, // Maximum annual NHF relief
        'life_insurance_relief' => 300000, // Annual life insurance relief
        'medical_expenses_relief' => 200000 // Annual medical expenses relief
    ];

    /**
     * Calculate all statutory deductions for an employee
     */
    public function calculateStatutoryDeductions(User $employee, float $grossPay, Carbon $periodStart, Carbon $periodEnd): array
    {
        // Calculate annual gross income for tax purposes
        $annualGrossIncome = $this->calculateAnnualIncome($employee, $grossPay, $periodStart, $periodEnd);
        
        // Calculate PAYE tax
        $payeCalculation = $this->calculatePAYE($annualGrossIncome, $employee);
        $monthlyPAYE = $payeCalculation['annual_tax'] / 12;
        
        // Calculate pension contributions
        $pensionContributions = $this->calculatePensionContributions($grossPay);
        
        // Calculate NHF contributions
        $nhfContributions = $this->calculateNHFContributions($grossPay);
        
        // Calculate NSITF (employer only)
        $nsitfContribution = $grossPay * self::NSITF_RATE;
        
        return [
            'paye_tax' => $monthlyPAYE,
            'pension_employee' => $pensionContributions['employee'],
            'pension_employer' => $pensionContributions['employer'],
            'nhf_employee' => $nhfContributions['employee'],
            'nhf_employer' => $nhfContributions['employer'],
            'nsitf_employer' => $nsitfContribution,
            'total_employee_deductions' => $monthlyPAYE + $pensionContributions['employee'] + $nhfContributions['employee'],
            'total_employer_contributions' => $pensionContributions['employer'] + $nhfContributions['employer'] + $nsitfContribution,
            'total_tax' => $monthlyPAYE,
            'calculation_details' => [
                'annual_gross_income' => $annualGrossIncome,
                'tax_calculation' => $payeCalculation,
                'pension_base' => min($grossPay, self::PENSION_RATES['max_contribution_base']),
                'nhf_base' => min($grossPay, self::NHF_RATES['max_contribution_base'])
            ]
        ];
    }

    /**
     * Calculate Nigerian PAYE tax
     */
    public function calculatePAYE(float $annualGrossIncome, User $employee): array
    {
        // Apply tax reliefs
        $reliefs = $this->calculateTaxReliefs($annualGrossIncome, $employee);
        $taxableIncome = max(0, $annualGrossIncome - $reliefs['total_relief']);
        
        // Calculate tax using marginal tax rates
        $taxCalculation = $this->applyTaxBrackets($taxableIncome);
        
        return [
            'annual_gross_income' => $annualGrossIncome,
            'total_reliefs' => $reliefs['total_relief'],
            'taxable_income' => $taxableIncome,
            'annual_tax' => $taxCalculation['total_tax'],
            'effective_tax_rate' => $annualGrossIncome > 0 ? ($taxCalculation['total_tax'] / $annualGrossIncome) * 100 : 0,
            'marginal_tax_rate' => $taxCalculation['marginal_rate'] * 100,
            'reliefs_breakdown' => $reliefs,
            'tax_brackets_applied' => $taxCalculation['brackets_applied']
        ];
    }

    /**
     * Calculate tax reliefs applicable to the employee
     */
    private function calculateTaxReliefs(float $annualGrossIncome, User $employee): array
    {
        $reliefs = [];
        
        // Gross Income Relief (₦200,000 or 1% of gross income, whichever is higher)
        $grossIncomeRelief = max(self::TAX_RELIEFS['gross_income_relief'], $annualGrossIncome * 0.01);
        $reliefs['gross_income_relief'] = $grossIncomeRelief;
        
        // Consolidated Relief Allowance (₦200,000 + 20% of gross income)
        $consolidatedReliefAllowance = self::TAX_RELIEFS['consolidated_relief_allowance'] + ($annualGrossIncome * 0.20);
        $reliefs['consolidated_relief_allowance'] = $consolidatedReliefAllowance;
        
        // Pension Relief (8% of gross income or ₦500,000, whichever is lower)
        $pensionRelief = min(
            $annualGrossIncome * self::TAX_RELIEFS['pension_relief_rate'],
            self::TAX_RELIEFS['max_pension_relief']
        );
        $reliefs['pension_relief'] = $pensionRelief;
        
        // NHF Relief (2.5% of gross income)
        $nhfRelief = min(
            $annualGrossIncome * self::TAX_RELIEFS['nhf_relief_rate'],
            self::TAX_RELIEFS['max_nhf_relief']
        );
        $reliefs['nhf_relief'] = $nhfRelief;
        
        // Life Insurance Relief (₦300,000 maximum annually)
        $lifeInsuranceRelief = $employee->life_insurance_premium ?? 0;
        $lifeInsuranceRelief = min($lifeInsuranceRelief, self::TAX_RELIEFS['life_insurance_relief']);
        $reliefs['life_insurance_relief'] = $lifeInsuranceRelief;
        
        // Medical Expenses Relief (₦200,000 maximum annually)
        $medicalExpensesRelief = $employee->medical_expenses ?? 0;
        $medicalExpensesRelief = min($medicalExpensesRelief, self::TAX_RELIEFS['medical_expenses_relief']);
        $reliefs['medical_expenses_relief'] = $medicalExpensesRelief;
        
        // Calculate total relief
        $reliefs['total_relief'] = array_sum($reliefs);
        
        return $reliefs;
    }

    /**
     * Apply tax brackets to calculate total tax
     */
    private function applyTaxBrackets(float $taxableIncome): array
    {
        $totalTax = 0;
        $remainingIncome = $taxableIncome;
        $marginalRate = 0;
        $bracketsApplied = [];
        
        foreach (self::TAX_BRACKETS_2024 as $bracket) {
            if ($remainingIncome <= 0) break;
            
            $bracketBase = $bracket['min'];
            $bracketTop = $bracket['max'];
            $bracketRate = $bracket['rate'];
            
            // Calculate taxable amount in this bracket
            $taxableInBracket = min($remainingIncome, $bracketTop - $bracketBase);
            
            if ($taxableInBracket > 0) {
                $taxInBracket = $taxableInBracket * $bracketRate;
                $totalTax += $taxInBracket;
                $marginalRate = $bracketRate; // Last applied rate is marginal rate
                
                $bracketsApplied[] = [
                    'range' => "₦" . number_format($bracketBase) . " - ₦" . number_format(min($bracketTop, $taxableIncome)),
                    'taxable_amount' => $taxableInBracket,
                    'rate' => $bracketRate * 100 . '%',
                    'tax' => $taxInBracket
                ];
                
                $remainingIncome -= $taxableInBracket;
            }
        }
        
        return [
            'total_tax' => $totalTax,
            'marginal_rate' => $marginalRate,
            'brackets_applied' => $bracketsApplied
        ];
    }

    /**
     * Calculate pension contributions
     */
    private function calculatePensionContributions(float $grossPay): array
    {
        // Use minimum of gross pay or maximum contribution base
        $contributionBase = min($grossPay, self::PENSION_RATES['max_contribution_base']);
        
        return [
            'employee' => $contributionBase * self::PENSION_RATES['employee_contribution'],
            'employer' => $contributionBase * self::PENSION_RATES['employer_contribution'],
            'contribution_base' => $contributionBase
        ];
    }

    /**
     * Calculate National Housing Fund contributions
     */
    private function calculateNHFContributions(float $grossPay): array
    {
        // Use minimum of gross pay or maximum contribution base
        $contributionBase = min($grossPay, self::NHF_RATES['max_contribution_base']);
        
        return [
            'employee' => $contributionBase * self::NHF_RATES['employee_contribution'],
            'employer' => $contributionBase * self::NHF_RATES['employer_contribution'],
            'contribution_base' => $contributionBase
        ];
    }

    /**
     * Calculate annual income for tax purposes
     */
    private function calculateAnnualIncome(User $employee, float $monthlyGrossPay, Carbon $periodStart, Carbon $periodEnd): float
    {
        // For simplicity, annualize the current monthly gross pay
        // In a real system, you might want to use actual YTD earnings
        return $monthlyGrossPay * 12;
    }

    /**
     * Get tax summary for employee
     */
    public function getTaxSummary(User $employee, float $grossPay): array
    {
        $deductions = $this->calculateStatutoryDeductions($employee, $grossPay, now()->startOfMonth(), now()->endOfMonth());
        
        return [
            'employee_info' => [
                'id' => $employee->id,
                'name' => $employee->name,
                'employee_id' => $employee->employee_id ?? $employee->id
            ],
            'gross_pay' => $grossPay,
            'deductions' => [
                'paye_tax' => [
                    'amount' => $deductions['paye_tax'],
                    'description' => 'Pay As You Earn Tax'
                ],
                'pension_contribution' => [
                    'amount' => $deductions['pension_employee'],
                    'description' => 'Pension Contribution (8%)'
                ],
                'nhf_contribution' => [
                    'amount' => $deductions['nhf_employee'],
                    'description' => 'National Housing Fund (2.5%)'
                ]
            ],
            'total_deductions' => $deductions['total_employee_deductions'],
            'net_pay' => $grossPay - $deductions['total_employee_deductions'],
            'employer_contributions' => [
                'pension' => $deductions['pension_employer'],
                'nhf' => $deductions['nhf_employer'],
                'nsitf' => $deductions['nsitf_employer'],
                'total' => $deductions['total_employer_contributions']
            ],
            'tax_calculation_details' => $deductions['calculation_details']
        ];
    }

    /**
     * Calculate year-to-date tax liability
     */
    public function calculateYTDTaxLiability(User $employee, Carbon $year): array
    {
        // This would typically pull from payroll records
        // For now, we'll simulate based on current salary
        $monthlySalary = $employee->base_salary ?? 50000;
        $monthsInYear = $year->isCurrentYear() ? now()->month : 12;
        $ytdGrossIncome = $monthlySalary * $monthsInYear;
        
        $payeCalculation = $this->calculatePAYE($ytdGrossIncome, $employee);
        $ytdTaxLiability = $payeCalculation['annual_tax'] / 12 * $monthsInYear;
        
        return [
            'year' => $year->year,
            'months_completed' => $monthsInYear,
            'ytd_gross_income' => $ytdGrossIncome,
            'ytd_tax_liability' => $ytdTaxLiability,
            'ytd_reliefs_claimed' => $payeCalculation['total_reliefs'] / 12 * $monthsInYear,
            'ytd_taxable_income' => $payeCalculation['taxable_income'] / 12 * $monthsInYear,
            'average_monthly_tax' => $ytdTaxLiability / max(1, $monthsInYear),
            'projected_annual_tax' => $payeCalculation['annual_tax'],
            'effective_tax_rate' => $payeCalculation['effective_tax_rate']
        ];
    }

    /**
     * Generate tax certificate for employee
     */
    public function generateTaxCertificate(User $employee, int $year): array
    {
        // This would pull actual payroll data for the year
        $yearCarbon = Carbon::create($year);
        $ytdData = $this->calculateYTDTaxLiability($employee, $yearCarbon);
        
        return [
            'certificate_info' => [
                'employee_id' => $employee->employee_id ?? $employee->id,
                'employee_name' => $employee->name,
                'tax_year' => $year,
                'generated_date' => now()->format('F j, Y'),
                'certificate_number' => 'VV-' . $year . '-' . str_pad($employee->id, 6, '0', STR_PAD_LEFT)
            ],
            'income_summary' => [
                'total_gross_income' => $ytdData['ytd_gross_income'],
                'total_reliefs_claimed' => $ytdData['ytd_reliefs_claimed'],
                'total_taxable_income' => $ytdData['ytd_taxable_income'],
                'total_tax_paid' => $ytdData['ytd_tax_liability']
            ],
            'monthly_breakdown' => [], // Would be populated with actual monthly data
            'employer_info' => [
                'company_name' => 'VitalVida Limited',
                'tax_id' => 'VV-TIN-123456789',
                'address' => 'Nigeria'
            ],
            'statutory_info' => [
                'firs_office' => 'Federal Inland Revenue Service',
                'preparation_date' => now()->format('F j, Y'),
                'valid_until' => now()->addYear()->format('F j, Y')
            ]
        ];
    }

    /**
     * Calculate bonus tax (same rate as salary)
     */
    public function calculateBonusTax(float $bonusAmount, User $employee, float $currentAnnualIncome = null): array
    {
        // For bonus taxation, we treat it as part of annual income
        $currentIncome = $currentAnnualIncome ?? (($employee->base_salary ?? 50000) * 12);
        $totalIncomeWithBonus = $currentIncome + $bonusAmount;
        
        // Calculate tax on total income
        $taxWithBonus = $this->calculatePAYE($totalIncomeWithBonus, $employee);
        $taxWithoutBonus = $this->calculatePAYE($currentIncome, $employee);
        
        $additionalTax = $taxWithBonus['annual_tax'] - $taxWithoutBonus['annual_tax'];
        
        return [
            'bonus_amount' => $bonusAmount,
            'additional_tax' => $additionalTax,
            'net_bonus' => $bonusAmount - $additionalTax,
            'effective_tax_rate_on_bonus' => $bonusAmount > 0 ? ($additionalTax / $bonusAmount) * 100 : 0,
            'total_income_with_bonus' => $totalIncomeWithBonus,
            'total_annual_tax_liability' => $taxWithBonus['annual_tax']
        ];
    }

    /**
     * Calculate comprehensive tax impact for bonus payments (Enhanced version)
     */
    public function calculateBonusTaxImpact(float $bonusAmount, User $employee): array
    {
        $currentGrossPay = $employee->base_salary ?? 50000;
        $newGrossPay = $currentGrossPay + $bonusAmount;
        
        // Calculate current tax scenario
        $currentTax = $this->calculateTaxes($currentGrossPay, $employee);
        $newTax = $this->calculateTaxes($newGrossPay, $employee);
        
        $additionalTax = $newTax['total_tax'] - $currentTax['total_tax'];
        
        return [
            'bonus_amount' => $bonusAmount,
            'current_monthly_tax' => $currentTax['total_tax'],
            'new_monthly_tax' => $newTax['total_tax'],
            'additional_tax' => $additionalTax,
            'bonus_after_tax' => $bonusAmount - $additionalTax,
            'effective_tax_rate_on_bonus' => $bonusAmount > 0 ? ($additionalTax / $bonusAmount) * 100 : 0,
            'detailed_breakdown' => [
                'current_scenario' => $currentTax,
                'new_scenario' => $newTax,
                'tax_increase_breakdown' => [
                    'paye_increase' => $newTax['paye_tax']['monthly'] - $currentTax['paye_tax']['monthly'],
                    'pension_increase' => $newTax['statutory_deductions']['pension'] - $currentTax['statutory_deductions']['pension'],
                    'nhf_increase' => $newTax['statutory_deductions']['nhf'] - $currentTax['statutory_deductions']['nhf'],
                    'nsitf_increase' => $newTax['statutory_deductions']['nsitf'] - $currentTax['statutory_deductions']['nsitf']
                ]
            ]
        ];
    }

    /**
     * Calculate all taxes and deductions for an employee (Enhanced version)
     */
    public function calculateTaxes(float $grossPay, User $employee): array
    {
        $annualGrossPay = $grossPay * 12;
        
        // Calculate reliefs and exemptions
        $reliefs = $this->calculateReliefs($annualGrossPay, $employee);
        $taxableIncome = max(0, $annualGrossPay - $reliefs['total_reliefs']);
        
        // Calculate PAYE (Pay As You Earn) tax
        $annualTax = $this->calculatePAYEAmount($taxableIncome);
        $monthlyTax = $annualTax / 12;
        
        // Calculate statutory deductions
        $pension = $grossPay * self::PENSION_RATES['employee_contribution'];
        $nhf = $grossPay * self::NHF_RATES['employee_contribution'];
        $nsitf = $grossPay * self::NSITF_RATE;
        
        return [
            'gross_pay' => $grossPay,
            'annual_gross' => $annualGrossPay,
            'reliefs' => $reliefs,
            'taxable_income' => $taxableIncome,
            'paye_tax' => [
                'annual' => $annualTax,
                'monthly' => $monthlyTax
            ],
            'statutory_deductions' => [
                'pension' => $pension,
                'nhf' => $nhf,
                'nsitf' => $nsitf,
                'total' => $pension + $nhf + $nsitf
            ],
            'total_tax' => $monthlyTax + $pension + $nhf + $nsitf,
            'tax_rate' => $annualGrossPay > 0 ? ($annualTax / $annualGrossPay) * 100 : 0
        ];
    }

    /**
     * Calculate PAYE tax using progressive brackets (Enhanced version)
     */
    private function calculatePAYEAmount(float $taxableIncome): float
    {
        $totalTax = 0;
        
        foreach (self::TAX_BRACKETS_2024 as $bracket) {
            if ($taxableIncome <= $bracket['min']) {
                break;
            }
            
            $bracketMax = min($taxableIncome, $bracket['max']);
            $bracketIncome = $bracketMax - $bracket['min'] + 1;
            $bracketTax = $bracketIncome * $bracket['rate'];
            $totalTax += $bracketTax;
            
            if ($taxableIncome <= $bracket['max']) {
                break;
            }
        }
        
        return round($totalTax, 2);
    }

    /**
     * Calculate tax reliefs and exemptions (Enhanced version)
     */
    private function calculateReliefs(float $annualGross, User $employee): array
    {
        // Consolidated Relief Allowance (CRA)
        $cra = max(200000, $annualGross * 0.01); // Higher of ₦200,000 or 1% of gross income
        $cra = min($cra, 200000); // Capped at ₦200,000
        
        // Pension relief (8% of basic salary)
        $pensionRelief = min($annualGross * 0.08, 300000); // Capped at ₦300,000
        
        // Other reliefs based on employee circumstances
        $otherReliefs = 0;
        
        // Dependent relief (if employee has dependents)
        $dependents = $employee->dependents ?? 0;
        if ($dependents > 0) {
            $otherReliefs += min($dependents * 2500, 25000); // ₦2,500 per dependent, max 10
        }
        
        // Life insurance relief
        $lifeInsuranceRelief = $employee->life_insurance_premium ?? 0;
        $lifeInsuranceRelief = min($lifeInsuranceRelief, 300000); // Capped at ₦300,000
        
        // Medical expenses relief
        $medicalExpensesRelief = $employee->medical_expenses ?? 0;
        $medicalExpensesRelief = min($medicalExpensesRelief, 200000); // Capped at ₦200,000
        
        $totalReliefs = $cra + $pensionRelief + $otherReliefs + $lifeInsuranceRelief + $medicalExpensesRelief;
        
        return [
            'consolidated_relief' => $cra,
            'pension_relief' => $pensionRelief,
            'dependent_relief' => $otherReliefs,
            'life_insurance_relief' => $lifeInsuranceRelief,
            'medical_expenses_relief' => $medicalExpensesRelief,
            'total_reliefs' => $totalReliefs
        ];
    }

    /**
     * Get current Nigerian tax rates and thresholds
     */
    public function getCurrentTaxRates(): array
    {
        return [
            'tax_year' => 2024,
            'paye_brackets' => self::TAX_BRACKETS_2024,
            'pension_rates' => self::PENSION_RATES,
            'nhf_rates' => self::NHF_RATES,
            'nsitf_rate' => self::NSITF_RATE,
            'tax_reliefs' => self::TAX_RELIEFS,
            'currency' => 'NGN',
            'last_updated' => '2024-01-01'
        ];
    }
} 
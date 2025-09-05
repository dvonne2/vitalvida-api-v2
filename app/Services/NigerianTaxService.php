<?php

namespace App\Services;

use App\Models\TaxCalculation;
use App\Models\TaxDeadline;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class NigerianTaxService
{
    /**
     * Calculate VAT for a given revenue amount
     */
    public function calculateVAT(float $revenue): array
    {
        try {
            $period = now()->format('Y-m');
            $taxCalculation = TaxCalculation::calculateVAT($revenue, $period);
            
            Log::info('VAT calculated successfully', [
                'revenue' => $revenue,
                'period' => $period,
                'tax_amount' => $taxCalculation->tax_amount,
            ]);

            return [
                'success' => true,
                'calculation' => $taxCalculation,
                'taxable_amount' => $revenue,
                'tax_rate' => 7.5,
                'tax_amount' => $taxCalculation->tax_amount,
                'due_date' => $taxCalculation->due_date->format('Y-m-d'),
                'formatted_tax_amount' => $taxCalculation->formatted_tax_amount,
            ];

        } catch (\Exception $e) {
            Log::error('VAT calculation failed', [
                'revenue' => $revenue,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate PAYE for given salaries
     */
    public function calculatePAYE(array $salaries): array
    {
        try {
            $totalSalaries = array_sum($salaries);
            $period = now()->format('Y-m');
            $taxCalculation = TaxCalculation::calculatePAYE($totalSalaries, $period);

            Log::info('PAYE calculated successfully', [
                'total_salaries' => $totalSalaries,
                'period' => $period,
                'tax_amount' => $taxCalculation->tax_amount,
            ]);

            return [
                'success' => true,
                'calculation' => $taxCalculation,
                'total_salaries' => $totalSalaries,
                'tax_rate' => 30,
                'tax_amount' => $taxCalculation->tax_amount,
                'due_date' => $taxCalculation->due_date->format('Y-m-d'),
                'formatted_tax_amount' => $taxCalculation->formatted_tax_amount,
            ];

        } catch (\Exception $e) {
            Log::error('PAYE calculation failed', [
                'salaries' => $salaries,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate CIT for taxable profit
     */
    public function calculateCIT(float $taxableProfit): array
    {
        try {
            $period = now()->format('Y');
            $taxCalculation = TaxCalculation::calculateCIT($taxableProfit, $period);

            Log::info('CIT calculated successfully', [
                'taxable_profit' => $taxableProfit,
                'period' => $period,
                'tax_amount' => $taxCalculation->tax_amount,
            ]);

            return [
                'success' => true,
                'calculation' => $taxCalculation,
                'taxable_profit' => $taxableProfit,
                'tax_rate' => 30,
                'tax_amount' => $taxCalculation->tax_amount,
                'due_date' => $taxCalculation->due_date->format('Y-m-d'),
                'formatted_tax_amount' => $taxCalculation->formatted_tax_amount,
            ];

        } catch (\Exception $e) {
            Log::error('CIT calculation failed', [
                'taxable_profit' => $taxableProfit,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate Education Tax for taxable profit
     */
    public function calculateEducationTax(float $taxableProfit): array
    {
        try {
            $period = now()->format('Y');
            $taxCalculation = TaxCalculation::calculateEducationTax($taxableProfit, $period);

            Log::info('Education Tax calculated successfully', [
                'taxable_profit' => $taxableProfit,
                'period' => $period,
                'tax_amount' => $taxCalculation->tax_amount,
            ]);

            return [
                'success' => true,
                'calculation' => $taxCalculation,
                'taxable_profit' => $taxableProfit,
                'tax_rate' => 2,
                'tax_amount' => $taxCalculation->tax_amount,
                'due_date' => $taxCalculation->due_date->format('Y-m-d'),
                'formatted_tax_amount' => $taxCalculation->formatted_tax_amount,
            ];

        } catch (\Exception $e) {
            Log::error('Education Tax calculation failed', [
                'taxable_profit' => $taxableProfit,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Calculate WHT for different types
     */
    public function calculateWHT(float $amount, string $type): array
    {
        try {
            $period = now()->format('Y-m');
            $taxCalculation = TaxCalculation::calculateWHT($amount, $type, $period);

            Log::info('WHT calculated successfully', [
                'amount' => $amount,
                'type' => $type,
                'period' => $period,
                'tax_amount' => $taxCalculation->tax_amount,
            ]);

            return [
                'success' => true,
                'calculation' => $taxCalculation,
                'amount' => $amount,
                'type' => $type,
                'tax_rate' => $taxCalculation->tax_rate,
                'tax_amount' => $taxCalculation->tax_amount,
                'due_date' => $taxCalculation->due_date->format('Y-m-d'),
                'formatted_tax_amount' => $taxCalculation->formatted_tax_amount,
            ];

        } catch (\Exception $e) {
            Log::error('WHT calculation failed', [
                'amount' => $amount,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Get upcoming tax deadlines
     */
    public function getUpcomingDeadlines(): Collection
    {
        return TaxDeadline::getUpcomingDeadlines(30);
    }

    /**
     * Check for overdue tax filings
     */
    public function checkOverdueFilings(): Collection
    {
        return TaxCalculation::getOverdueTaxes();
    }

    /**
     * Get tax summary for all types
     */
    public function getTaxSummary(): array
    {
        $summary = TaxCalculation::getTaxSummary();
        $totalTaxAmount = $summary->sum('total_tax_amount');
        $totalPenaltyAmount = $summary->sum('total_penalty_amount');
        $overdueCount = $summary->sum('overdue_count');

        return [
            'summary' => $summary,
            'total_tax_amount' => $totalTaxAmount,
            'total_penalty_amount' => $totalPenaltyAmount,
            'overdue_count' => $overdueCount,
            'formatted_total_tax' => '₦' . number_format($totalTaxAmount, 2),
            'formatted_total_penalty' => '₦' . number_format($totalPenaltyAmount, 2),
        ];
    }

    /**
     * Get critical tax alerts
     */
    public function getCriticalAlerts(): array
    {
        $overdueTaxes = $this->checkOverdueFilings();
        $upcomingDeadlines = $this->getUpcomingDeadlines();
        $criticalDeadlines = TaxDeadline::getCriticalDeadlines();

        return [
            'overdue_taxes' => $overdueTaxes,
            'upcoming_deadlines' => $upcomingDeadlines,
            'critical_deadlines' => $criticalDeadlines,
            'overdue_count' => $overdueTaxes->count(),
            'upcoming_count' => $upcomingDeadlines->count(),
            'critical_count' => $criticalDeadlines->count(),
        ];
    }

    /**
     * Calculate total tax liability for a period
     */
    public function calculateTotalTaxLiability(string $period): array
    {
        $calculations = TaxCalculation::byPeriod($period)->get();
        
        $totalLiability = $calculations->sum('tax_amount');
        $totalPenalty = $calculations->sum('penalty_amount');
        $totalDue = $totalLiability + $totalPenalty;

        return [
            'period' => $period,
            'calculations' => $calculations,
            'total_liability' => $totalLiability,
            'total_penalty' => $totalPenalty,
            'total_due' => $totalDue,
            'formatted_total_liability' => '₦' . number_format($totalLiability, 2),
            'formatted_total_penalty' => '₦' . number_format($totalPenalty, 2),
            'formatted_total_due' => '₦' . number_format($totalDue, 2),
        ];
    }

    /**
     * Mark tax as filed
     */
    public function markTaxAsFiled(int $taxCalculationId): bool
    {
        try {
            $taxCalculation = TaxCalculation::findOrFail($taxCalculationId);
            $taxCalculation->markAsFiled();

            Log::info('Tax marked as filed', [
                'tax_calculation_id' => $taxCalculationId,
                'tax_type' => $taxCalculation->tax_type,
                'period' => $taxCalculation->period,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark tax as filed', [
                'tax_calculation_id' => $taxCalculationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Mark tax as paid
     */
    public function markTaxAsPaid(int $taxCalculationId): bool
    {
        try {
            $taxCalculation = TaxCalculation::findOrFail($taxCalculationId);
            $taxCalculation->markAsPaid();

            Log::info('Tax marked as paid', [
                'tax_calculation_id' => $taxCalculationId,
                'tax_type' => $taxCalculation->tax_type,
                'period' => $taxCalculation->period,
            ]);

            return true;

        } catch (\Exception $e) {
            Log::error('Failed to mark tax as paid', [
                'tax_calculation_id' => $taxCalculationId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }
} 
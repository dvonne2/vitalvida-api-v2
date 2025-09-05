<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TaxCalculation;
use App\Models\TaxOptimizationStrategy;
use App\Services\NigerianTaxService;
use App\Services\TaxOptimizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaxController extends Controller
{
    protected $taxService;
    protected $optimizationService;

    public function __construct(NigerianTaxService $taxService, TaxOptimizationService $optimizationService)
    {
        $this->taxService = $taxService;
        $this->optimizationService = $optimizationService;
    }

    /**
     * Get tax compliance status
     */
    public function getComplianceStatus(): JsonResponse
    {
        try {
            $criticalAlerts = $this->taxService->getCriticalAlerts();
            $taxSummary = $this->taxService->getTaxSummary();

            $data = [
                'critical_alerts' => $criticalAlerts,
                'tax_summary' => $taxSummary,
                'overdue_count' => $criticalAlerts['overdue_count'],
                'upcoming_count' => $criticalAlerts['upcoming_count'],
                'total_tax_liability' => $taxSummary['total_tax_amount'] ?? 0,
                'total_penalties' => $taxSummary['total_penalty_amount'] ?? 0,
                'formatted_total_liability' => $taxSummary['formatted_total_tax'] ?? '₦0.00',
                'formatted_total_penalties' => $taxSummary['formatted_total_penalty'] ?? '₦0.00',
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Tax compliance status retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tax compliance status',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Calculate tax
     */
    public function calculateTax(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tax_type' => 'required|string|in:VAT,PAYE,CIT,EDT,WHT',
                'amount' => 'required|numeric|min:0',
                'period' => 'required|string',
                'additional_data' => 'nullable|array',
            ]);

            $taxType = $request->input('tax_type');
            $amount = $request->input('amount');
            $period = $request->input('period');
            $additionalData = $request->input('additional_data', []);

            $result = match($taxType) {
                'VAT' => $this->taxService->calculateVAT($amount),
                'PAYE' => $this->taxService->calculatePAYE([$amount]),
                'CIT' => $this->taxService->calculateCIT($amount),
                'EDT' => $this->taxService->calculateEducationTax($amount),
                'WHT' => $this->taxService->calculateWHT($amount, $additionalData['type'] ?? 'contract'),
                default => ['success' => false, 'error' => 'Invalid tax type'],
            };

            if ($result['success']) {
                return response()->json([
                    'success' => true,
                    'data' => $result,
                    'message' => 'Tax calculated successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to calculate tax',
                    'error' => $result['error'],
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to calculate tax',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get upcoming tax deadlines
     */
    public function getUpcomingDeadlines(): JsonResponse
    {
        try {
            $upcomingDeadlines = $this->taxService->getUpcomingDeadlines();
            $overdueDeadlines = $this->taxService->checkOverdueFilings();

            $data = [
                'upcoming_deadlines' => $upcomingDeadlines->map(function ($deadline) {
                    return [
                        'tax_type' => $deadline->tax_type,
                        'tax_type_icon' => $deadline->tax_type_icon,
                        'next_due_date' => $deadline->next_due_date->format('Y-m-d'),
                        'days_until_due' => $deadline->days_until_due,
                        'is_overdue' => $deadline->is_overdue,
                        'status_color' => $deadline->status_color,
                        'status_icon' => $deadline->status_icon,
                        'reminder_message' => $deadline->getReminderMessage(),
                        'description' => $deadline->description,
                    ];
                }),
                'overdue_deadlines' => $overdueDeadlines->map(function ($tax) {
                    return [
                        'id' => $tax->id,
                        'tax_type' => $tax->tax_type,
                        'period' => $tax->period,
                        'tax_amount' => $tax->tax_amount,
                        'formatted_tax_amount' => $tax->formatted_tax_amount,
                        'penalty_amount' => $tax->penalty_amount,
                        'formatted_penalty_amount' => $tax->formatted_penalty_amount,
                        'total_amount_due' => $tax->total_amount_due,
                        'formatted_total_amount_due' => $tax->formatted_total_amount_due,
                        'due_date' => $tax->due_date->format('Y-m-d'),
                        'days_overdue' => $tax->days_overdue,
                        'status' => $tax->status,
                        'status_color' => $tax->status_color,
                        'status_icon' => $tax->status_icon,
                    ];
                }),
                'summary' => [
                    'upcoming_count' => $upcomingDeadlines->count(),
                    'overdue_count' => $overdueDeadlines->count(),
                    'total_penalties' => $overdueDeadlines->sum('penalty_amount'),
                    'formatted_total_penalties' => '₦' . number_format($overdueDeadlines->sum('penalty_amount'), 2),
                ],
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Tax deadlines retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tax deadlines',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tax optimization strategies
     */
    public function getOptimizationStrategies(): JsonResponse
    {
        try {
            $currentPosition = $this->optimizationService->analyzeCurrentPosition();
            $availableStrategies = $this->optimizationService->getAvailableStrategies();
            $potentialSavings = $this->optimizationService->calculatePotentialSavings();

            $data = [
                'current_position' => $currentPosition,
                'available_strategies' => $availableStrategies->map(function ($strategy) {
                    return [
                        'id' => $strategy->id,
                        'strategy_name' => $strategy->strategy_name,
                        'description' => $strategy->description,
                        'potential_savings' => $strategy->potential_savings,
                        'formatted_potential_savings' => $strategy->formatted_potential_savings,
                        'implementation_status' => $strategy->implementation_status,
                        'status_color' => $strategy->status_color,
                        'status_icon' => $strategy->status_icon,
                        'difficulty_level' => $strategy->difficulty_level,
                        'difficulty_color' => $strategy->difficulty_color,
                        'difficulty_icon' => $strategy->difficulty_icon,
                        'deadline' => $strategy->deadline ? $strategy->deadline->format('Y-m-d') : null,
                        'days_until_deadline' => $strategy->days_until_deadline,
                        'is_overdue' => $strategy->is_overdue,
                        'priority_score' => $strategy->priority_score,
                    ];
                }),
                'potential_savings' => $potentialSavings,
                'optimization_summary' => TaxOptimizationStrategy::getOptimizationSummary(),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Tax optimization strategies retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tax optimization strategies',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Implement a tax optimization strategy
     */
    public function implementStrategy(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'strategy_id' => 'required|integer|exists:tax_optimization_strategies,id',
            ]);

            $strategyId = $request->input('strategy_id');
            $success = $this->optimizationService->implementStrategy($strategyId);

            if ($success) {
                $strategy = TaxOptimizationStrategy::find($strategyId);
                
                return response()->json([
                    'success' => true,
                    'data' => [
                        'strategy_id' => $strategyId,
                        'strategy_name' => $strategy->strategy_name,
                        'potential_savings' => $strategy->potential_savings,
                        'formatted_potential_savings' => $strategy->formatted_potential_savings,
                        'implementation_status' => $strategy->implementation_status,
                        'status_color' => $strategy->status_color,
                        'status_icon' => $strategy->status_icon,
                    ],
                    'message' => 'Tax optimization strategy implemented successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to implement tax optimization strategy',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to implement tax optimization strategy',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get tax calculation by ID
     */
    public function show(TaxCalculation $taxCalculation): JsonResponse
    {
        try {
            $data = [
                'id' => $taxCalculation->id,
                'tax_type' => $taxCalculation->tax_type,
                'period' => $taxCalculation->period,
                'taxable_amount' => $taxCalculation->taxable_amount,
                'formatted_taxable_amount' => $taxCalculation->formatted_taxable_amount,
                'tax_rate' => $taxCalculation->tax_rate,
                'formatted_tax_rate' => $taxCalculation->formatted_tax_rate,
                'tax_amount' => $taxCalculation->tax_amount,
                'formatted_tax_amount' => $taxCalculation->formatted_tax_amount,
                'status' => $taxCalculation->status,
                'status_color' => $taxCalculation->status_color,
                'status_icon' => $taxCalculation->status_icon,
                'due_date' => $taxCalculation->due_date->format('Y-m-d'),
                'filed_date' => $taxCalculation->filed_date ? $taxCalculation->filed_date->format('Y-m-d') : null,
                'paid_date' => $taxCalculation->paid_date ? $taxCalculation->paid_date->format('Y-m-d') : null,
                'penalty_amount' => $taxCalculation->penalty_amount,
                'formatted_penalty_amount' => $taxCalculation->formatted_penalty_amount,
                'total_amount_due' => $taxCalculation->total_amount_due,
                'formatted_total_amount_due' => $taxCalculation->formatted_total_amount_due,
                'is_overdue' => $taxCalculation->is_overdue,
                'days_until_due' => $taxCalculation->days_until_due,
                'days_overdue' => $taxCalculation->days_overdue,
                'created_at' => $taxCalculation->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $taxCalculation->updated_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Tax calculation retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load tax calculation',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark tax as filed
     */
    public function markAsFiled(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tax_calculation_id' => 'required|integer|exists:tax_calculations,id',
            ]);

            $taxCalculationId = $request->input('tax_calculation_id');
            $success = $this->taxService->markTaxAsFiled($taxCalculationId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tax marked as filed successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark tax as filed',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark tax as filed',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Mark tax as paid
     */
    public function markAsPaid(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'tax_calculation_id' => 'required|integer|exists:tax_calculations,id',
            ]);

            $taxCalculationId = $request->input('tax_calculation_id');
            $success = $this->taxService->markTaxAsPaid($taxCalculationId);

            if ($success) {
                return response()->json([
                    'success' => true,
                    'message' => 'Tax marked as paid successfully',
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to mark tax as paid',
                ], 400);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark tax as paid',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Budget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BudgetController extends Controller
{
    /**
     * Get all budgets
     */
    public function index(): JsonResponse
    {
        try {
            $budgets = Budget::with(['creator', 'approver'])
                ->orderBy('created_at', 'desc')
                ->get();

            $data = $budgets->map(function ($budget) {
                return [
                    'id' => $budget->id,
                    'department' => $budget->department,
                    'fiscal_year' => $budget->fiscal_year,
                    'month' => $budget->month,
                    'budget_amount' => $budget->budget_amount,
                    'formatted_budget_amount' => $budget->formatted_budget_amount,
                    'actual_amount' => $budget->actual_amount,
                    'formatted_actual_amount' => $budget->formatted_actual_amount,
                    'variance' => $budget->variance,
                    'formatted_variance' => $budget->formatted_variance,
                    'variance_percentage' => $budget->variance_percentage,
                    'formatted_variance_percentage' => $budget->formatted_variance_percentage,
                    'status' => $budget->status,
                    'status_color' => $budget->status_color,
                    'status_icon' => $budget->status_icon,
                    'variance_status' => $budget->variance_status,
                    'variance_status_color' => $budget->variance_status_color,
                    'utilization_percentage' => $budget->utilization_percentage,
                    'is_over_budget' => $budget->is_over_budget,
                    'is_under_budget' => $budget->is_under_budget,
                    'remaining_budget' => $budget->remaining_budget,
                    'formatted_remaining_budget' => $budget->formatted_remaining_budget,
                    'creator' => $budget->creator ? $budget->creator->name : 'Unknown',
                    'approver' => $budget->approver ? $budget->approver->name : null,
                    'created_at' => $budget->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $budget->updated_at->format('Y-m-d H:i:s'),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Budgets retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load budgets',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Store a new budget
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'department' => 'required|string|max:255',
                'fiscal_year' => 'required|string|max:4',
                'month' => 'required|string|max:7',
                'budget_amount' => 'required|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $budget = Budget::create([
                'department' => $request->input('department'),
                'fiscal_year' => $request->input('fiscal_year'),
                'month' => $request->input('month'),
                'budget_amount' => $request->input('budget_amount'),
                'actual_amount' => $request->input('actual_amount', 0),
                'status' => 'draft',
                'created_by' => auth()->id(),
                'notes' => $request->input('notes'),
            ]);

            $budget->calculateVariance();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $budget->id,
                    'department' => $budget->department,
                    'fiscal_year' => $budget->fiscal_year,
                    'month' => $budget->month,
                    'budget_amount' => $budget->budget_amount,
                    'formatted_budget_amount' => $budget->formatted_budget_amount,
                    'actual_amount' => $budget->actual_amount,
                    'formatted_actual_amount' => $budget->formatted_actual_amount,
                    'variance' => $budget->variance,
                    'formatted_variance' => $budget->formatted_variance,
                    'variance_percentage' => $budget->variance_percentage,
                    'formatted_variance_percentage' => $budget->formatted_variance_percentage,
                    'status' => $budget->status,
                    'status_color' => $budget->status_color,
                    'status_icon' => $budget->status_icon,
                ],
                'message' => 'Budget created successfully',
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create budget',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Show a specific budget
     */
    public function show(Budget $budget): JsonResponse
    {
        try {
            $budget->load(['creator', 'approver']);

            $data = [
                'id' => $budget->id,
                'department' => $budget->department,
                'fiscal_year' => $budget->fiscal_year,
                'month' => $budget->month,
                'budget_amount' => $budget->budget_amount,
                'formatted_budget_amount' => $budget->formatted_budget_amount,
                'actual_amount' => $budget->actual_amount,
                'formatted_actual_amount' => $budget->formatted_actual_amount,
                'variance' => $budget->variance,
                'formatted_variance' => $budget->formatted_variance,
                'variance_percentage' => $budget->variance_percentage,
                'formatted_variance_percentage' => $budget->formatted_variance_percentage,
                'status' => $budget->status,
                'status_color' => $budget->status_color,
                'status_icon' => $budget->status_icon,
                'variance_status' => $budget->variance_status,
                'variance_status_color' => $budget->variance_status_color,
                'utilization_percentage' => $budget->utilization_percentage,
                'is_over_budget' => $budget->is_over_budget,
                'is_under_budget' => $budget->is_under_budget,
                'remaining_budget' => $budget->remaining_budget,
                'formatted_remaining_budget' => $budget->formatted_remaining_budget,
                'creator' => $budget->creator ? $budget->creator->name : 'Unknown',
                'approver' => $budget->approver ? $budget->approver->name : null,
                'notes' => $budget->notes,
                'created_at' => $budget->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $budget->updated_at->format('Y-m-d H:i:s'),
            ];

            return response()->json([
                'success' => true,
                'data' => $data,
                'message' => 'Budget retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load budget',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update a budget
     */
    public function update(Request $request, Budget $budget): JsonResponse
    {
        try {
            $request->validate([
                'department' => 'sometimes|string|max:255',
                'fiscal_year' => 'sometimes|string|max:4',
                'month' => 'sometimes|string|max:7',
                'budget_amount' => 'sometimes|numeric|min:0',
                'actual_amount' => 'sometimes|numeric|min:0',
                'notes' => 'nullable|string',
            ]);

            $budget->update($request->only([
                'department', 'fiscal_year', 'month', 'budget_amount', 'actual_amount', 'notes'
            ]));

            $budget->calculateVariance();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $budget->id,
                    'department' => $budget->department,
                    'fiscal_year' => $budget->fiscal_year,
                    'month' => $budget->month,
                    'budget_amount' => $budget->budget_amount,
                    'formatted_budget_amount' => $budget->formatted_budget_amount,
                    'actual_amount' => $budget->actual_amount,
                    'formatted_actual_amount' => $budget->formatted_actual_amount,
                    'variance' => $budget->variance,
                    'formatted_variance' => $budget->formatted_variance,
                    'variance_percentage' => $budget->variance_percentage,
                    'formatted_variance_percentage' => $budget->formatted_variance_percentage,
                    'status' => $budget->status,
                    'status_color' => $budget->status_color,
                    'status_icon' => $budget->status_icon,
                ],
                'message' => 'Budget updated successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update budget',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete a budget
     */
    public function destroy(Budget $budget): JsonResponse
    {
        try {
            $budget->delete();

            return response()->json([
                'success' => true,
                'message' => 'Budget deleted successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete budget',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get variance analysis
     */
    public function getVarianceAnalysis(): JsonResponse
    {
        try {
            $currentMonth = now()->format('Y-m');
            $budgets = Budget::where('month', $currentMonth)->get();

            $analysis = [
                'total_budget' => $budgets->sum('budget_amount'),
                'total_actual' => $budgets->sum('actual_amount'),
                'total_variance' => $budgets->sum('budget_amount') - $budgets->sum('actual_amount'),
                'variance_percentage' => $this->calculateVariancePercentage($budgets),
                'departments' => $budgets->groupBy('department')->map(function ($deptBudgets) {
                    return [
                        'department' => $deptBudgets->first()->department,
                        'budget_amount' => $deptBudgets->sum('budget_amount'),
                        'actual_amount' => $deptBudgets->sum('actual_amount'),
                        'variance' => $deptBudgets->sum('budget_amount') - $deptBudgets->sum('actual_amount'),
                        'utilization_percentage' => $this->calculateUtilizationPercentage($deptBudgets),
                        'is_over_budget' => $deptBudgets->sum('actual_amount') > $deptBudgets->sum('budget_amount'),
                    ];
                }),
                'over_budget_departments' => $budgets->filter(function ($budget) {
                    return $budget->is_over_budget;
                })->count(),
                'under_budget_departments' => $budgets->filter(function ($budget) {
                    return $budget->is_under_budget;
                })->count(),
                'formatted_total_budget' => '₦' . number_format($budgets->sum('budget_amount'), 2),
                'formatted_total_actual' => '₦' . number_format($budgets->sum('actual_amount'), 2),
                'formatted_total_variance' => '₦' . number_format($budgets->sum('budget_amount') - $budgets->sum('actual_amount'), 2),
            ];

            return response()->json([
                'success' => true,
                'data' => $analysis,
                'message' => 'Variance analysis retrieved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load variance analysis',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Approve a budget
     */
    public function approve(Request $request, Budget $budget): JsonResponse
    {
        try {
            $budget->approve();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $budget->id,
                    'status' => $budget->status,
                    'status_color' => $budget->status_color,
                    'status_icon' => $budget->status_icon,
                ],
                'message' => 'Budget approved successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve budget',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Lock a budget
     */
    public function lock(Request $request, Budget $budget): JsonResponse
    {
        try {
            $budget->lock();

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $budget->id,
                    'status' => $budget->status,
                    'status_color' => $budget->status_color,
                    'status_icon' => $budget->status_icon,
                ],
                'message' => 'Budget locked successfully',
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to lock budget',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    // Helper methods
    private function calculateVariancePercentage($budgets): float
    {
        $totalBudget = $budgets->sum('budget_amount');
        $totalActual = $budgets->sum('actual_amount');
        
        return $totalBudget > 0 ? (($totalActual - $totalBudget) / $totalBudget) * 100 : 0;
    }

    private function calculateUtilizationPercentage($budgets): float
    {
        $totalBudget = $budgets->sum('budget_amount');
        $totalActual = $budgets->sum('actual_amount');
        
        return $totalBudget > 0 ? ($totalActual / $totalBudget) * 100 : 0;
    }
}

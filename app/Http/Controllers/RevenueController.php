<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Models\Revenue;
use App\Models\Department;
use Carbon\Carbon;

class RevenueController extends Controller
{
    /**
     * Get revenue summary
     */
    public function getSummary(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'month');
            $departmentId = $request->get('department_id');

            $query = Revenue::query();

            if ($departmentId) {
                $query->byDepartment($departmentId);
            }

            switch ($period) {
                case 'today':
                    $revenue = $query->byDate(Carbon::today())->sum('total_revenue');
                    break;
                case 'week':
                    $revenue = $query->whereBetween('date', [
                        Carbon::now()->startOfWeek(),
                        Carbon::now()->endOfWeek()
                    ])->sum('total_revenue');
                    break;
                case 'month':
                    $revenue = $query->whereYear('date', Carbon::now()->year)
                        ->whereMonth('date', Carbon::now()->month)
                        ->sum('total_revenue');
                    break;
                case 'quarter':
                    $revenue = $query->whereYear('date', Carbon::now()->year)
                        ->whereBetween('date', [
                            Carbon::now()->startOfQuarter(),
                            Carbon::now()->endOfQuarter()
                        ])->sum('total_revenue');
                    break;
                case 'year':
                    $revenue = $query->whereYear('date', Carbon::now()->year)
                        ->sum('total_revenue');
                    break;
                default:
                    $revenue = $query->sum('total_revenue');
            }

            $revenueBreakdown = Revenue::selectRaw('
                SUM(order_revenue) as order_revenue,
                SUM(delivery_revenue) as delivery_revenue,
                SUM(service_revenue) as service_revenue,
                SUM(other_revenue) as other_revenue
            ')->first();

            $revenueData = [
                'total_revenue' => $revenue,
                'formatted_total' => '₦' . number_format($revenue, 2),
                'breakdown' => [
                    'order_revenue' => $revenueBreakdown->order_revenue ?? 0,
                    'delivery_revenue' => $revenueBreakdown->delivery_revenue ?? 0,
                    'service_revenue' => $revenueBreakdown->service_revenue ?? 0,
                    'other_revenue' => $revenueBreakdown->other_revenue ?? 0,
                ],
                'period' => $period,
            ];

            return response()->json([
                'success' => true,
                'data' => $revenueData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue summary',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue by department
     */
    public function getByDepartment(Request $request): JsonResponse
    {
        try {
            $period = $request->get('period', 'month');
            $departments = Department::active()->get();

            $departmentRevenue = $departments->map(function ($dept) use ($period) {
                $query = Revenue::byDepartment($dept->id);

                switch ($period) {
                    case 'today':
                        $revenue = $query->byDate(Carbon::today())->sum('total_revenue');
                        break;
                    case 'week':
                        $revenue = $query->whereBetween('date', [
                            Carbon::now()->startOfWeek(),
                            Carbon::now()->endOfWeek()
                        ])->sum('total_revenue');
                        break;
                    case 'month':
                        $revenue = $query->whereYear('date', Carbon::now()->year)
                            ->whereMonth('date', Carbon::now()->month)
                            ->sum('total_revenue');
                        break;
                    default:
                        $revenue = $query->sum('total_revenue');
                }

                return [
                    'department_id' => $dept->id,
                    'department_name' => $dept->name,
                    'department_code' => $dept->code,
                    'revenue' => $revenue,
                    'formatted_revenue' => '₦' . number_format($revenue, 2),
                    'target_revenue' => $dept->target_revenue,
                    'achievement' => $dept->target_revenue > 0 ? 
                        ($revenue / $dept->target_revenue) * 100 : 0,
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $departmentRevenue
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue by department',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get revenue trend
     */
    public function getTrend(Request $request): JsonResponse
    {
        try {
            $days = $request->get('days', 30);
            $departmentId = $request->get('department_id');

            $trend = Revenue::getRevenueTrend($days);

            if ($departmentId) {
                $trend = Revenue::where('department_id', $departmentId)
                    ->where('date', '>=', Carbon::now()->subDays($days))
                    ->groupBy('date')
                    ->selectRaw('date, SUM(total_revenue) as daily_revenue')
                    ->orderBy('date')
                    ->get();
            }

            $trendData = $trend->map(function ($item) {
                return [
                    'date' => $item->date,
                    'revenue' => $item->daily_revenue,
                    'formatted_revenue' => '₦' . number_format($item->daily_revenue, 2),
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $trendData
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to load revenue trend',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create revenue record
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'date' => 'required|date',
                'total_revenue' => 'required|numeric|min:0',
                'order_revenue' => 'nullable|numeric|min:0',
                'delivery_revenue' => 'nullable|numeric|min:0',
                'service_revenue' => 'nullable|numeric|min:0',
                'other_revenue' => 'nullable|numeric|min:0',
                'department_id' => 'nullable|exists:departments,id',
                'source' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $revenue = Revenue::createRevenue([
                'date' => $request->date,
                'total_revenue' => $request->total_revenue,
                'order_revenue' => $request->order_revenue ?? 0,
                'delivery_revenue' => $request->delivery_revenue ?? 0,
                'service_revenue' => $request->service_revenue ?? 0,
                'other_revenue' => $request->other_revenue ?? 0,
                'department_id' => $request->department_id,
                'source' => $request->source,
                'notes' => $request->notes,
                'created_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Revenue record created successfully',
                'data' => $revenue
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create revenue record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update revenue record
     */
    public function update(Request $request, $id): JsonResponse
    {
        try {
            $revenue = Revenue::findOrFail($id);

            $request->validate([
                'total_revenue' => 'nullable|numeric|min:0',
                'order_revenue' => 'nullable|numeric|min:0',
                'delivery_revenue' => 'nullable|numeric|min:0',
                'service_revenue' => 'nullable|numeric|min:0',
                'other_revenue' => 'nullable|numeric|min:0',
                'department_id' => 'nullable|exists:departments,id',
                'source' => 'nullable|string|max:100',
                'notes' => 'nullable|string',
            ]);

            $revenue->update([
                'total_revenue' => $request->total_revenue ?? $revenue->total_revenue,
                'order_revenue' => $request->order_revenue ?? $revenue->order_revenue,
                'delivery_revenue' => $request->delivery_revenue ?? $revenue->delivery_revenue,
                'service_revenue' => $request->service_revenue ?? $revenue->service_revenue,
                'other_revenue' => $request->other_revenue ?? $revenue->other_revenue,
                'department_id' => $request->department_id ?? $revenue->department_id,
                'source' => $request->source ?? $revenue->source,
                'notes' => $request->notes ?? $revenue->notes,
                'updated_by' => auth()->id(),
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Revenue record updated successfully',
                'data' => $revenue
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update revenue record',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Delete revenue record
     */
    public function destroy($id): JsonResponse
    {
        try {
            $revenue = Revenue::findOrFail($id);
            $revenue->delete();

            return response()->json([
                'success' => true,
                'message' => 'Revenue record deleted successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete revenue record',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

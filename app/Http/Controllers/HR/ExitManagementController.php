<?php

namespace App\Http\Controllers\HR;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\ExitProcess;
use App\Models\ExitChecklist;
use App\Models\ExitInterview;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class ExitManagementController extends Controller
{
    /**
     * Get exit management dashboard
     */
    public function getExitDashboard(): JsonResponse
    {
        try {
            $exitProcesses = ExitProcess::with(['employee.department'])
                ->where('status', 'active')
                ->get();

            $overview = $this->calculateExitOverview($exitProcesses);
            $exitReasonsBreakdown = $this->getExitReasonsBreakdown();
            $departmentTurnoverRates = $this->getDepartmentTurnoverRates();
            $activeExitProcesses = $this->formatActiveExitProcesses($exitProcesses);

            return response()->json([
                'success' => true,
                'data' => [
                    'overview' => $overview,
                    'exit_reasons_breakdown' => $exitReasonsBreakdown,
                    'department_turnover_rates' => $departmentTurnoverRates,
                    'active_exit_processes' => $activeExitProcesses
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exit Dashboard Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load exit dashboard',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get exit process details
     */
    public function getExitProcessDetails(int $exitId): JsonResponse
    {
        try {
            $exitProcess = ExitProcess::with(['employee.department', 'employee.position', 'checklist'])
                ->findOrFail($exitId);

            $exitInterview = ExitInterview::where('exit_process_id', $exitId)->first();
            $checklistProgress = $this->calculateChecklistProgress($exitProcess);

            return response()->json([
                'success' => true,
                'data' => [
                    'exit_process' => [
                        'id' => $exitProcess->id,
                        'employee_name' => $exitProcess->employee->first_name . ' ' . $exitProcess->employee->last_name,
                        'position' => $exitProcess->employee->position->title ?? 'Unknown',
                        'department' => $exitProcess->employee->department->name ?? 'Unknown',
                        'exit_date' => $exitProcess->exit_date->format('d/m/Y'),
                        'reason' => $exitProcess->reason,
                        'notice_period' => $exitProcess->notice_period,
                        'status' => $exitProcess->status,
                        'progress' => $checklistProgress['overall_progress']
                    ],
                    'checklist' => $this->formatChecklist($exitProcess->checklist),
                    'exit_interview' => $exitInterview ? [
                        'conducted_date' => $exitInterview->conducted_date->format('M j, Y'),
                        'interviewer' => $exitInterview->interviewer,
                        'key_findings' => $exitInterview->key_findings,
                        'recommendations' => $exitInterview->recommendations,
                        'recording_url' => $exitInterview->recording_url
                    ] : null,
                    'cost_impact' => $this->calculateCostImpact($exitProcess),
                    'knowledge_transfer_status' => $this->getKnowledgeTransferStatus($exitProcess),
                    'actions' => $this->getAvailableActions($exitProcess)
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Exit Process Details Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to load exit process details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Initiate exit process
     */
    public function initiateExitProcess(Request $request): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'employee_id' => 'required|exists:employees,id',
                'exit_date' => 'required|date|after:today',
                'reason' => 'required|in:resignation,performance,contract_end,termination,retirement',
                'notice_period' => 'required|integer|min:1|max:90',
                'exit_type' => 'required|in:voluntary,involuntary',
                'notes' => 'nullable|string'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $employee = Employee::findOrFail($request->employee_id);
            
            // Create exit process
            $exitProcess = ExitProcess::create([
                'employee_id' => $request->employee_id,
                'exit_date' => $request->exit_date,
                'reason' => $request->reason,
                'notice_period' => $request->notice_period,
                'exit_type' => $request->exit_type,
                'notes' => $request->notes,
                'status' => 'active',
                'initiated_by' => $request->user()->name ?? 'System',
                'initiated_at' => now()
            ]);

            // Create exit checklist
            $this->createExitChecklist($exitProcess->id);

            // Update employee status
            $employee->update(['status' => 'exiting']);

            return response()->json([
                'success' => true,
                'message' => 'Exit process initiated successfully',
                'data' => [
                    'exit_process_id' => $exitProcess->id,
                    'employee_name' => $employee->first_name . ' ' . $employee->last_name,
                    'exit_date' => $exitProcess->exit_date->format('M j, Y'),
                    'reason' => $exitProcess->reason,
                    'checklist_created' => true
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Initiate Exit Process Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate exit process',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update exit checklist
     */
    public function updateExitChecklist(Request $request, int $exitId): JsonResponse
    {
        try {
            $validator = \Illuminate\Support\Facades\Validator::make($request->all(), [
                'checklist_item' => 'required|string',
                'status' => 'required|in:complete,pending,in_progress',
                'notes' => 'nullable|string',
                'completion_date' => 'nullable|date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $exitChecklist = ExitChecklist::where('exit_process_id', $exitId)
                ->where('item_name', $request->checklist_item)
                ->first();

            if (!$exitChecklist) {
                return response()->json([
                    'success' => false,
                    'message' => 'Checklist item not found'
                ], 404);
            }

            $exitChecklist->update([
                'status' => $request->status,
                'notes' => $request->notes,
                'completion_date' => $request->status === 'complete' ? ($request->completion_date ?? now()) : null,
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Checklist item updated successfully',
                'data' => [
                    'checklist_item' => $request->checklist_item,
                    'status' => $request->status,
                    'completion_date' => $exitChecklist->completion_date?->format('M j, Y')
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Update Exit Checklist Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to update checklist item',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete exit process
     */
    public function completeExitProcess(int $exitId): JsonResponse
    {
        try {
            $exitProcess = ExitProcess::with('checklist')->findOrFail($exitId);
            
            // Check if all checklist items are complete
            $incompleteItems = $exitProcess->checklist->where('status', '!=', 'complete')->count();
            
            if ($incompleteItems > 0) {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot complete exit process - ' . $incompleteItems . ' checklist items pending',
                    'data' => [
                        'incomplete_items' => $incompleteItems,
                        'pending_items' => $exitProcess->checklist->where('status', '!=', 'complete')->pluck('item_name')
                    ]
                ], 400);
            }

            // Complete exit process
            $exitProcess->update([
                'status' => 'completed',
                'completed_at' => now()
            ]);

            // Update employee status
            $employee = Employee::find($exitProcess->employee_id);
            $employee->update(['status' => 'terminated']);

            return response()->json([
                'success' => true,
                'message' => 'Exit process completed successfully',
                'data' => [
                    'exit_process_id' => $exitId,
                    'completion_date' => now()->format('M j, Y'),
                    'employee_status' => 'terminated'
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Complete Exit Process Error: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete exit process',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate exit overview
     */
    private function calculateExitOverview($exitProcesses): array
    {
        $totalExitsYTD = ExitProcess::whereYear('created_at', now()->year)->count();
        $avgTenure = $this->calculateAverageTenure();
        $costImpact = $this->calculateTotalCostImpact();
        $activeExits = $exitProcesses->count();

        return [
            'total_exits_ytd' => $totalExitsYTD,
            'avg_tenure' => $avgTenure . 'm',
            'cost_impact' => $costImpact,
            'active_exits' => $activeExits
        ];
    }

    /**
     * Get exit reasons breakdown
     */
    private function getExitReasonsBreakdown(): array
    {
        $reasons = ['resignation', 'performance', 'contract_end', 'termination'];
        $breakdown = [];
        $totalExits = ExitProcess::whereYear('created_at', now()->year)->count();

        foreach ($reasons as $reason) {
            $count = ExitProcess::where('reason', $reason)
                ->whereYear('created_at', now()->year)
                ->count();
            
            $breakdown[$reason] = [
                'count' => $count,
                'percentage' => $totalExits > 0 ? round(($count / $totalExits) * 100, 1) . '%' : '0%'
            ];
        }

        return $breakdown;
    }

    /**
     * Get department turnover rates
     */
    private function getDepartmentTurnoverRates(): array
    {
        $departments = ['sales', 'engineering', 'marketing', 'operations'];
        $rates = [];

        foreach ($departments as $department) {
            $rates[$department] = $this->calculateDepartmentTurnoverRate($department);
        }

        return $rates;
    }

    /**
     * Format active exit processes
     */
    private function formatActiveExitProcesses($exitProcesses): array
    {
        return $exitProcesses->map(function ($process) {
            $checklistProgress = $this->calculateChecklistProgress($process);
            $costImpact = $this->calculateCostImpact($process);

            return [
                'employee' => $process->employee->first_name . ' ' . $process->employee->last_name,
                'position' => $process->employee->position->title ?? 'Unknown',
                'department' => $process->employee->department->name ?? 'Unknown',
                'exit_date' => $process->exit_date->format('d/m/Y'),
                'reason' => $process->reason,
                'progress' => $checklistProgress['overall_progress'] . '%',
                'cost_impact' => $costImpact,
                'checklist' => $this->formatChecklist($process->checklist),
                'actions' => $this->getAvailableActions($process)
            ];
        })->toArray();
    }

    /**
     * Calculate checklist progress
     */
    private function calculateChecklistProgress($exitProcess): array
    {
        $checklist = $exitProcess->checklist;
        $totalItems = $checklist->count();
        $completedItems = $checklist->where('status', 'complete')->count();
        
        $overallProgress = $totalItems > 0 ? round(($completedItems / $totalItems) * 100, 1) : 0;

        return [
            'overall_progress' => $overallProgress,
            'completed_items' => $completedItems,
            'total_items' => $totalItems
        ];
    }

    /**
     * Format checklist
     */
    private function formatChecklist($checklist): array
    {
        return $checklist->map(function ($item) {
            $formattedItem = [
                'item_name' => $item->item_name,
                'status' => $item->status
            ];

            if ($item->completion_date) {
                $formattedItem['date'] = $item->completion_date->format('d M');
            }

            if ($item->notes) {
                $formattedItem['note'] = $item->notes;
            }

            return $formattedItem;
        })->toArray();
    }

    /**
     * Calculate cost impact
     */
    private function calculateCostImpact($exitProcess): float
    {
        $employee = $exitProcess->employee;
        $monthlySalary = $employee->base_salary ?? 0;
        
        // Calculate replacement cost (3 months salary)
        $replacementCost = $monthlySalary * 3;
        
        // Calculate knowledge transfer cost
        $knowledgeTransferCost = $monthlySalary * 0.5;
        
        return $replacementCost + $knowledgeTransferCost;
    }

    /**
     * Get knowledge transfer status
     */
    private function getKnowledgeTransferStatus($exitProcess): array
    {
        $checklistItem = $exitProcess->checklist->where('item_name', 'knowledge_transfer')->first();
        
        return [
            'status' => $checklistItem->status ?? 'pending',
            'progress' => $checklistItem->status === 'complete' ? '100%' : '0%',
            'notes' => $checklistItem->notes ?? ''
        ];
    }

    /**
     * Get available actions
     */
    private function getAvailableActions($exitProcess): array
    {
        $actions = ['view_details'];
        
        if ($exitProcess->status === 'active') {
            $actions[] = 'update_checklist';
            $actions[] = 'schedule_exit_interview';
        }
        
        $checklistProgress = $this->calculateChecklistProgress($exitProcess);
        if ($checklistProgress['overall_progress'] >= 100) {
            $actions[] = 'archive';
        }
        
        return $actions;
    }

    /**
     * Create exit checklist
     */
    private function createExitChecklist(int $exitProcessId): void
    {
        $checklistItems = [
            'assets_returned' => 'Return company assets (laptop, phone, etc.)',
            'knowledge_transfer' => 'Complete knowledge transfer sessions',
            'final_pay' => 'Process final salary and benefits',
            'access_revoked' => 'Revoke system access and permissions',
            'exit_interview' => 'Conduct exit interview and record feedback'
        ];

        foreach ($checklistItems as $itemName => $description) {
            ExitChecklist::create([
                'exit_process_id' => $exitProcessId,
                'item_name' => $itemName,
                'description' => $description,
                'status' => 'pending',
                'created_at' => now()
            ]);
        }
    }

    /**
     * Calculate average tenure
     */
    private function calculateAverageTenure(): float
    {
        $employees = Employee::where('status', 'terminated')->get();
        
        if ($employees->isEmpty()) {
            return 18.4; // Default value
        }
        
        $totalMonths = $employees->sum(function ($employee) {
            return $employee->hire_date->diffInMonths($employee->termination_date ?? now());
        });
        
        return round($totalMonths / $employees->count(), 1);
    }

    /**
     * Calculate total cost impact
     */
    private function calculateTotalCostImpact(): float
    {
        $exitProcesses = ExitProcess::whereYear('created_at', now()->year)->get();
        
        return $exitProcesses->sum(function ($process) {
            return $this->calculateCostImpact($process);
        });
    }

    /**
     * Calculate department turnover rate
     */
    private function calculateDepartmentTurnoverRate(string $department): string
    {
        // Simulated turnover rates
        $rates = [
            'sales' => '15.8%',
            'engineering' => '8.3%',
            'marketing' => '11.1%',
            'operations' => '5%'
        ];
        
        return $rates[$department] ?? '0%';
    }
}

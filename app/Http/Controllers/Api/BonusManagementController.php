<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use App\Services\BonusCalculationService;
use App\Models\BonusLog;
use App\Models\User;
use App\Models\BonusApprovalRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class BonusManagementController extends Controller
{
    public function __construct(
        private BonusCalculationService $bonusService
    ) {}

    /**
     * Calculate bonuses for specified month
     */
    public function calculateMonthlyBonuses(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'department' => 'nullable|string',
            'recalculate' => 'boolean',
            'dry_run' => 'boolean'
        ]);

        try {
            $month = Carbon::createFromFormat('Y-m', $validated['month']);
            
            // Check if bonuses already calculated for this month
            if (!($validated['recalculate'] ?? false)) {
                $existingBonuses = BonusLog::whereYear('created_at', $month->year)
                    ->whereMonth('created_at', $month->month)
                    ->exists();
                    
                if ($existingBonuses) {
                    return response()->json([
                        'error' => 'Bonuses already calculated for this month',
                        'message' => 'Use recalculate=true to recalculate bonuses'
                    ], 422);
                }
            }

            // Calculate bonuses
            $bonusResults = $this->bonusService->calculateMonthlyBonuses($month);
            
            // If dry run, return results without saving
            if ($validated['dry_run'] ?? false) {
                return response()->json([
                    'success' => true,
                    'message' => 'Dry run completed successfully',
                    'month' => $month->format('Y-m'),
                    'results' => $bonusResults,
                    'dry_run' => true
                ]);
            }

            // Create approval requests for bonuses requiring approval
            $approvalResults = $this->createApprovalRequestsForBonuses($bonusResults);

            return response()->json([
                'success' => true,
                'message' => 'Monthly bonuses calculated successfully',
                'month' => $month->format('Y-m'),
                'results' => $bonusResults,
                'approval_summary' => $approvalResults,
                'next_steps' => [
                    'auto_approved_count' => $approvalResults['auto_approved_count'],
                    'pending_approval_count' => $approvalResults['pending_approval_count'],
                    'total_pending_amount' => $approvalResults['total_pending_amount']
                ]
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to calculate monthly bonuses', [
                'month' => $validated['month'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'error' => 'Failed to calculate bonuses',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bonus calculation results for a specific month
     */
    public function getBonusCalculations(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'month' => 'required|date_format:Y-m',
            'user_id' => 'nullable|integer|exists:users,id',
            'status' => 'nullable|in:pending,approved,paid,rejected'
        ]);

        $month = Carbon::createFromFormat('Y-m', $validated['month']);
        
        $query = BonusLog::with(['user'])
            ->whereYear('created_at', $month->year)
            ->whereMonth('created_at', $month->month);

        if ($validated['user_id'] ?? false) {
            $query->where('user_id', $validated['user_id']);
        }
        
        if ($validated['status'] ?? false) {
            $query->where('status', $validated['status']);
        }

        $bonuses = $query->get()->groupBy('user_id')->map(function($userBonuses) {
            $user = $userBonuses->first()->user;
            return [
                'user' => [
                    'id' => $user->id,
                    'name' => $user->name,
                    'role' => $user->role,
                    'department' => $user->department ?? 'General',
                    'base_salary' => $user->base_salary ?? 0
                ],
                'bonuses' => $userBonuses->map(function($bonus) {
                    return [
                        'id' => $bonus->id,
                        'type' => $bonus->bonus_type,
                        'description' => $bonus->description,
                        'amount' => $bonus->amount,
                        'status' => $bonus->status,
                        'calculation_basis' => $bonus->calculation_basis,
                        'requires_approval' => $bonus->requires_approval,
                        'approval_status' => $bonus->approvalRequest?->status,
                        'created_at' => $bonus->created_at
                    ];
                }),
                'total_bonus_amount' => $userBonuses->sum('amount'),
                'bonus_count' => $userBonuses->count()
            ];
        });

        $summary = [
            'total_users' => $bonuses->count(),
            'total_bonus_amount' => $bonuses->sum('total_bonus_amount'),
            'status_breakdown' => BonusLog::whereYear('created_at', $month->year)
                ->whereMonth('created_at', $month->month)
                ->groupBy('status')
                ->selectRaw('status, count(*) as count, sum(amount) as total_amount')
                ->get()
                ->keyBy('status')
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'month' => $month->format('Y-m'),
                'bonuses' => $bonuses,
                'summary' => $summary
            ]
        ]);
    }

    /**
     * Approve or reject bonus requests
     */
    public function processApprovalRequest(Request $request, int $approvalRequestId): JsonResponse
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'comments' => 'nullable|string|max:1000',
            'adjusted_amount' => 'nullable|numeric|min:0'
        ]);

        try {
            $user = $request->user();
            $approvalRequest = BonusApprovalRequest::with(['bonuses', 'user'])
                ->findOrFail($approvalRequestId);

            // Verify user can approve
            if (!$this->canUserApproveBonus($user, $approvalRequest)) {
                return response()->json([
                    'error' => 'You are not authorized to approve this bonus request'
                ], 403);
            }

            DB::beginTransaction();

            if ($validated['action'] === 'approve') {
                $this->approveBonusRequest($approvalRequest, $user, $validated);
                $message = 'Bonus request approved successfully';
            } else {
                $this->rejectBonusRequest($approvalRequest, $user, $validated);
                $message = 'Bonus request rejected';
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => $message,
                'data' => [
                    'approval_request' => [
                        'id' => $approvalRequest->id,
                        'status' => $approvalRequest->fresh()->status,
                        'processed_by' => $user->name,
                        'processed_at' => now()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Failed to process approval request', [
                'approval_request_id' => $approvalRequestId,
                'user_id' => $user->id ?? 'unknown',
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'error' => 'Failed to process approval request',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get bonus analytics and insights
     */
    public function getBonusAnalytics(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'period' => 'required|in:month,quarter,year',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after:start_date',
            'analysis_type' => 'nullable|in:basic,multi_dimensional,performance_correlation,trend_analysis,top_performers',
            'group_by' => 'nullable|string',
            'dimensions' => 'nullable|string',
            'correlation_type' => 'nullable|in:individual,team,company',
            'trend_period' => 'nullable|integer|min:1|max:12',
            'limit' => 'nullable|integer|min:1|max:100',
            'sort_by' => 'nullable|in:total_bonus,performance_score,department'
        ]);

        $startDate = Carbon::parse($validated['start_date']);
        $endDate = Carbon::parse($validated['end_date']);
        $analysisType = $validated['analysis_type'] ?? 'basic';

        $bonuses = BonusLog::with(['user'])
            ->whereBetween('created_at', [$startDate, $endDate])
            ->where('status', 'paid')
            ->get();

        $analytics = match($analysisType) {
            'multi_dimensional' => $this->analyzeBonusesByDepartment($bonuses, $validated['dimensions']),
            'performance_correlation' => $this->analyzePerformanceCorrelation($bonuses, $validated['correlation_type']),
            'trend_analysis' => $this->analyzeBonusTrends($bonuses, $validated['trend_period']),
            'top_performers' => $this->getTopPerformers($bonuses, $validated['limit'], $validated['sort_by']),
            default => $this->getBasicAnalytics($bonuses, $validated['group_by'])
        };

        return response()->json([
            'success' => true,
            'data' => [
                'period' => [
                    'start_date' => $startDate->format('Y-m-d'),
                    'end_date' => $endDate->format('Y-m-d'),
                    'period_type' => $validated['period']
                ],
                'analytics' => $analytics
            ]
        ]);
    }

    /**
     * Get pending bonus approvals
     */
    public function getPendingApprovals(Request $request): JsonResponse
    {
        $user = $request->user();
        
        $query = BonusApprovalRequest::with(['user', 'bonuses'])
            ->where('status', 'pending_approval');

        // Filter by user's approval level
        if (!in_array($user->role, ['CEO', 'superadmin'])) {
            $query->whereJsonContains('required_approvers', $user->role);
        }

        $pendingApprovals = $query->orderBy('created_at', 'desc')->get();

        return response()->json([
            'success' => true,
            'data' => [
                'pending_approvals' => $pendingApprovals->map(function($approval) {
                    return [
                        'id' => $approval->id,
                        'user' => [
                            'id' => $approval->user->id,
                            'name' => $approval->user->name,
                            'role' => $approval->user->role
                        ],
                        'total_amount' => $approval->total_amount,
                        'approval_tier' => $approval->approval_tier,
                        'required_approvers' => json_decode($approval->required_approvers, true),
                        'justification' => $approval->justification,
                        'expires_at' => $approval->expires_at,
                        'created_at' => $approval->created_at,
                        'urgency' => $this->calculateUrgency($approval->expires_at)
                    ];
                }),
                'summary' => [
                    'total_pending' => $pendingApprovals->count(),
                    'total_amount' => $pendingApprovals->sum('total_amount'),
                    'urgent_count' => $pendingApprovals->filter(function($approval) {
                        return $this->calculateUrgency($approval->expires_at) === 'urgent';
                    })->count()
                ]
            ]
        ]);
    }

    /**
     * Get employee bonus summary
     */
    public function getEmployeeBonusSummary(Request $request, int $userId): JsonResponse
    {
        $user = User::findOrFail($userId);
        
        $bonuses = BonusLog::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();

        $summary = [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'role' => $user->role,
                'department' => $user->department ?? 'General'
            ],
            'bonus_summary' => [
                'total_bonuses_earned' => $bonuses->where('status', 'paid')->sum('amount'),
                'total_bonuses_pending' => $bonuses->where('status', 'approved')->sum('amount'),
                'total_bonuses_rejected' => $bonuses->where('status', 'rejected')->sum('amount'),
                'bonus_count' => $bonuses->count()
            ],
            'bonus_history' => $bonuses->groupBy(function($bonus) {
                return $bonus->created_at->format('Y-m');
            })->map(function($monthBonuses, $month) {
                return [
                    'month' => $month,
                    'total_amount' => $monthBonuses->sum('amount'),
                    'bonus_count' => $monthBonuses->count(),
                    'status_breakdown' => $monthBonuses->groupBy('status')->map->count()
                ];
            })
        ];

        return response()->json([
            'success' => true,
            'data' => $summary
        ]);
    }

    /**
     * Create approval requests for bonuses
     */
    private function createApprovalRequestsForBonuses(array $bonusResults): array
    {
        $autoApprovedCount = 0;
        $pendingApprovalCount = 0;
        $totalPendingAmount = 0;

        foreach ($bonusResults['employee_bonuses'] as $userId => $bonuses) {
            $user = User::find($userId);
            
            foreach ($bonuses as $bonusData) {
                // Validate against thresholds
                $validation = $this->bonusService->validateBonusesAgainstThresholds([$bonusData], $userId);
                $validationResult = $validation[0];

                // Create bonus record
                $bonus = BonusLog::create([
                    'user_id' => $userId,
                    'bonus_type' => $bonusData['type'],
                    'description' => $bonusData['description'],
                    'amount' => $bonusData['amount'],
                    'calculation_basis' => json_encode($bonusData['basis']),
                    'requires_approval' => !$validationResult['can_auto_approve'],
                    'status' => $validationResult['can_auto_approve'] ? 'approved' : 'pending',
                    'calculated_by' => auth()->id(),
                    'calculated_at' => now()
                ]);

                if ($validationResult['can_auto_approve']) {
                    $autoApprovedCount++;
                } else {
                    // Create approval request
                    BonusApprovalRequest::create([
                        'user_id' => $userId,
                        'bonus_ids' => json_encode([$bonus->id]),
                        'total_amount' => $bonusData['amount'],
                        'approval_tier' => $this->determineApprovalTier($bonusData['amount']),
                        'required_approvers' => json_encode($this->getRequiredApprovers($bonusData['amount'])),
                        'justification' => $bonusData['description'],
                        'status' => 'pending_approval',
                        'expires_at' => now()->addDays(7),
                        'created_by' => auth()->id()
                    ]);
                    
                    $pendingApprovalCount++;
                    $totalPendingAmount += $bonusData['amount'];
                }
            }
        }

        return [
            'auto_approved_count' => $autoApprovedCount,
            'pending_approval_count' => $pendingApprovalCount,
            'total_pending_amount' => $totalPendingAmount
        ];
    }

    /**
     * Check if user can approve bonus
     */
    private function canUserApproveBonus($user, BonusApprovalRequest $request): bool
    {
        $requiredApprovers = json_decode($request->required_approvers, true);
        return in_array($user->role, $requiredApprovers);
    }

    /**
     * Approve bonus request
     */
    private function approveBonusRequest(BonusApprovalRequest $request, $user, array $data): void
    {
        $adjustedAmount = $data['adjusted_amount'] ?? null;

        // Update approval request
        $request->update([
            'status' => 'approved',
            'approved_by' => $user->id,
            'approved_at' => now(),
            'approval_comments' => $data['comments'],
            'adjusted_amount' => $adjustedAmount
        ]);

        // Update related bonuses
        $bonusIds = json_decode($request->bonus_ids, true);
        $bonuses = BonusLog::whereIn('id', $bonusIds)->get();
        
        foreach ($bonuses as $bonus) {
            $bonus->update([
                'status' => 'approved',
                'approved_by' => $user->id,
                'approved_at' => now(),
                'amount' => $adjustedAmount ?? $bonus->amount,
                'approval_comments' => $data['comments']
            ]);
        }
    }

    /**
     * Reject bonus request
     */
    private function rejectBonusRequest(BonusApprovalRequest $request, $user, array $data): void
    {
        // Update approval request
        $request->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['comments']
        ]);

        // Update related bonuses
        $bonusIds = json_decode($request->bonus_ids, true);
        BonusLog::whereIn('id', $bonusIds)->update([
            'status' => 'rejected',
            'rejected_by' => $user->id,
            'rejected_at' => now(),
            'rejection_reason' => $data['comments']
        ]);
    }

    /**
     * Determine approval tier based on amount
     */
    private function determineApprovalTier(float $amount): string
    {
        return match(true) {
            $amount <= 15000 => 'fc',
            $amount <= 50000 => 'gm',
            default => 'ceo'
        };
    }

    /**
     * Get required approvers based on amount
     */
    private function getRequiredApprovers(float $amount): array
    {
        return match(true) {
            $amount <= 15000 => ['fc'],
            $amount <= 50000 => ['gm'],
            default => ['ceo']
        };
    }

    /**
     * Calculate urgency level
     */
    private function calculateUrgency($expiresAt): string
    {
        $daysUntilExpiry = Carbon::parse($expiresAt)->diffInDays(now(), false);
        
        return match(true) {
            $daysUntilExpiry < 0 => 'expired',
            $daysUntilExpiry <= 1 => 'urgent',
            $daysUntilExpiry <= 3 => 'high',
            default => 'normal'
        };
    }

    /**
     * Get basic analytics
     */
    private function getBasicAnalytics($bonuses, $groupBy = null): array
    {
        return [
            'overview' => [
                'total_bonuses_paid' => $bonuses->sum('amount'),
                'total_users_rewarded' => $bonuses->pluck('user_id')->unique()->count(),
                'average_bonus_per_user' => $bonuses->count() > 0 ? $bonuses->sum('amount') / $bonuses->pluck('user_id')->unique()->count() : 0,
                'total_bonus_transactions' => $bonuses->count()
            ],
            'bonus_type_breakdown' => $bonuses->groupBy('bonus_type')->map(function($group) {
                return [
                    'count' => $group->count(),
                    'total_amount' => $group->sum('amount'),
                    'average_amount' => $group->avg('amount')
                ];
            }),
            'department_analysis' => $this->analyzeBonusesByDepartment($bonuses, $groupBy)
        ];
    }

    /**
     * Analyze bonuses by department
     */
    private function analyzeBonusesByDepartment($bonuses, $dimensions = null): array
    {
        return $bonuses->groupBy('user.department')->map(function($group) {
            return [
                'count' => $group->count(),
                'total_amount' => $group->sum('amount'),
                'average_amount' => $group->avg('amount'),
                'user_count' => $group->pluck('user_id')->unique()->count()
            ];
        })->toArray();
    }

    /**
     * Analyze performance correlation
     */
    private function analyzePerformanceCorrelation($bonuses, $correlationType = 'individual'): array
    {
        // This would integrate with performance metrics
        return [
            'correlation_type' => $correlationType,
            'correlation_score' => 0.75, // Placeholder
            'analysis_period' => 'monthly',
            'insights' => [
                'High performers receive 15% more bonuses',
                'Team performance correlates with bonus distribution',
                'Individual metrics show strong correlation with bonus amounts'
            ]
        ];
    }

    /**
     * Analyze bonus trends
     */
    private function analyzeBonusTrends($bonuses, $trendPeriod = 6): array
    {
        $trends = [];
        $current = now()->subMonths($trendPeriod);
        
        for ($i = 0; $i < $trendPeriod; $i++) {
            $monthStart = $current->copy()->startOfMonth();
            $monthEnd = $current->copy()->endOfMonth();
            
            $monthlyBonuses = $bonuses->filter(function($bonus) use ($monthStart, $monthEnd) {
                return $bonus->created_at->between($monthStart, $monthEnd);
            });
            
            $trends[] = [
                'month' => $monthStart->format('M Y'),
                'total_amount' => $monthlyBonuses->sum('amount'),
                'bonus_count' => $monthlyBonuses->count(),
                'average_amount' => $monthlyBonuses->avg('amount')
            ];
            
            $current->addMonth();
        }
        
        return $trends;
    }

    /**
     * Get top performers
     */
    private function getTopPerformers($bonuses, $limit = 10, $sortBy = 'total_bonus'): array
    {
        $userBonuses = $bonuses->groupBy('user_id')->map(function($userBonus) {
            return [
                'user' => $userBonus->first()->user,
                'total_bonus' => $userBonus->sum('amount'),
                'bonus_count' => $userBonus->count(),
                'average_bonus' => $userBonus->avg('amount')
            ];
        });

        $sorted = $userBonuses->sortByDesc($sortBy)->take($limit);

        return $sorted->map(function($userBonus) {
            return [
                'user' => [
                    'id' => $userBonus['user']->id,
                    'name' => $userBonus['user']->name,
                    'role' => $userBonus['user']->role,
                    'department' => $userBonus['user']->department ?? 'General'
                ],
                'total_bonus' => $userBonus['total_bonus'],
                'bonus_count' => $userBonus['bonus_count'],
                'average_bonus' => $userBonus['average_bonus']
            ];
        })->values()->toArray();
    }
} 
<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\AgentPerformanceMetric;
use App\Models\StrikeLog;
use App\Models\AgentActivityLog;
use App\Models\PhotoAudit;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DABehavioralController extends Controller
{
    /**
     * Overall compliance metrics (78%)
     * GET /api/da/compliance/overview
     */
    public function getComplianceOverview(): JsonResponse
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'overall_compliance' => 0,
                    'total_das' => 0,
                    'compliant_das' => 0,
                    'non_compliant_das' => 0,
                    'metrics' => []
                ]
            ]);
        }

        // Calculate compliance metrics
        $complianceMetrics = [
            'login_compliance' => $this->calculateLoginCompliance(),
            'photo_compliance' => $this->calculatePhotoCompliance(),
            'stock_compliance' => $this->calculateStockCompliance(),
            'delivery_compliance' => $this->calculateDeliveryCompliance(),
            'performance_compliance' => $this->calculatePerformanceCompliance()
        ];

        $overallCompliance = array_sum($complianceMetrics) / count($complianceMetrics);
        $compliantDAs = DeliveryAgent::where('status', 'active')
            ->where('strikes_count', '<', 3)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'overall_compliance' => round($overallCompliance, 1),
                'total_das' => $totalDAs,
                'compliant_das' => $compliantDAs,
                'non_compliant_das' => $totalDAs - $compliantDAs,
                'metrics' => $complianceMetrics,
                'last_updated' => now()->toISOString()
            ]
        ]);
    }

    /**
     * Get violations summary
     * GET /api/da/violations
     */
    public function getViolationsSummary(): JsonResponse
    {
        $today = now()->toDateString();
        $thisWeek = now()->startOfWeek();
        $thisMonth = now()->startOfMonth();

        $violationsSummary = [
            'total_violations' => StrikeLog::count(),
            'violations_today' => StrikeLog::whereDate('created_at', $today)->count(),
            'violations_this_week' => StrikeLog::whereBetween('created_at', [$thisWeek, now()])->count(),
            'violations_this_month' => StrikeLog::whereBetween('created_at', [$thisMonth, now()])->count(),
            'pending_reviews' => StrikeLog::where('status', 'pending_review')->count(),
            'resolved_violations' => StrikeLog::where('status', 'resolved')->count(),
            'escalated_violations' => StrikeLog::where('status', 'escalated')->count()
        ];

        // Violation categories
        $violationCategories = StrikeLog::select('violation_type')
            ->selectRaw('COUNT(*) as count')
            ->groupBy('violation_type')
            ->orderBy('count', 'desc')
            ->get();

        // Recent violations
        $recentViolations = StrikeLog::with(['deliveryAgent', 'reportedBy'])
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'summary' => $violationsSummary,
                'categories' => $violationCategories,
                'recent_violations' => $recentViolations
            ]
        ]);
    }

    /**
     * Get pending actions
     * GET /api/da/pending-actions
     */
    public function getPendingActions(): JsonResponse
    {
        $pendingActions = [];

        // DAs with 3 strikes (immediate action required)
        $threeStrikeDAs = DeliveryAgent::where('status', 'active')
            ->where('strikes_count', '>=', 3)
            ->with('user')
            ->get();

        foreach ($threeStrikeDAs as $da) {
            $pendingActions[] = [
                'id' => 'THREE_STRIKE_' . $da->id,
                'type' => 'three_strike_suspension',
                'priority' => 'critical',
                'title' => 'DA with 3 Strikes - Immediate Suspension Required',
                'description' => "DA {$da->da_code} has accumulated 3 strikes and requires immediate suspension review",
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'da_name' => $da->user->name ?? 'Unknown',
                'strikes_count' => $da->strikes_count,
                'created_at' => now()->toISOString()
            ];
        }

        // DAs with 2 strikes (warning required)
        $twoStrikeDAs = DeliveryAgent::where('status', 'active')
            ->where('strikes_count', 2)
            ->with('user')
            ->get();

        foreach ($twoStrikeDAs as $da) {
            $pendingActions[] = [
                'id' => 'TWO_STRIKE_' . $da->id,
                'type' => 'two_strike_warning',
                'priority' => 'high',
                'title' => 'DA with 2 Strikes - Warning Required',
                'description' => "DA {$da->da_code} has 2 strikes and requires a warning",
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'da_name' => $da->user->name ?? 'Unknown',
                'strikes_count' => $da->strikes_count,
                'created_at' => now()->toISOString()
            ];
        }

        // Pending violation reviews
        $pendingReviews = StrikeLog::where('status', 'pending_review')
            ->with(['deliveryAgent.user'])
            ->get();

        foreach ($pendingReviews as $violation) {
            $pendingActions[] = [
                'id' => 'VIOLATION_REVIEW_' . $violation->id,
                'type' => 'violation_review',
                'priority' => 'medium',
                'title' => 'Violation Review Required',
                'description' => "Review violation: {$violation->violation_type} by DA {$violation->deliveryAgent->da_code}",
                'violation_id' => $violation->id,
                'da_id' => $violation->delivery_agent_id,
                'da_code' => $violation->deliveryAgent->da_code,
                'violation_type' => $violation->violation_type,
                'created_at' => $violation->created_at->toISOString()
            ];
        }

        // DAs with low performance
        $lowPerformanceDAs = DeliveryAgent::where('status', 'active')
            ->where('success_rate', '<', 70)
            ->with('user')
            ->get();

        foreach ($lowPerformanceDAs as $da) {
            $pendingActions[] = [
                'id' => 'LOW_PERFORMANCE_' . $da->id,
                'type' => 'performance_review',
                'priority' => 'medium',
                'title' => 'Low Performance Review Required',
                'description' => "DA {$da->da_code} has low success rate ({$da->success_rate}%)",
                'da_id' => $da->id,
                'da_code' => $da->da_code,
                'da_name' => $da->user->name ?? 'Unknown',
                'success_rate' => $da->success_rate,
                'created_at' => now()->toISOString()
            ];
        }

        return response()->json([
            'success' => true,
            'data' => [
                'total_pending' => count($pendingActions),
                'critical_priority' => count(array_filter($pendingActions, fn($action) => $action['priority'] === 'critical')),
                'high_priority' => count(array_filter($pendingActions, fn($action) => $action['priority'] === 'high')),
                'medium_priority' => count(array_filter($pendingActions, fn($action) => $action['priority'] === 'medium')),
                'actions' => $pendingActions
            ]
        ]);
    }

    /**
     * Get compliance rate (85%)
     * GET /api/da/compliance-rate
     */
    public function getComplianceRate(): JsonResponse
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) {
            return response()->json([
                'success' => true,
                'data' => [
                    'compliance_rate' => 0,
                    'total_das' => 0,
                    'compliant_das' => 0,
                    'breakdown' => []
                ]
            ]);
        }

        $complianceBreakdown = [
            'login_compliance' => $this->calculateLoginCompliance(),
            'photo_compliance' => $this->calculatePhotoCompliance(),
            'stock_compliance' => $this->calculateStockCompliance(),
            'delivery_compliance' => $this->calculateDeliveryCompliance(),
            'performance_compliance' => $this->calculatePerformanceCompliance()
        ];

        $overallCompliance = array_sum($complianceBreakdown) / count($complianceBreakdown);
        $compliantDAs = DeliveryAgent::where('status', 'active')
            ->where('strikes_count', '<', 3)
            ->count();

        return response()->json([
            'success' => true,
            'data' => [
                'compliance_rate' => round($overallCompliance, 1),
                'total_das' => $totalDAs,
                'compliant_das' => $compliantDAs,
                'breakdown' => $complianceBreakdown
            ]
        ]);
    }

    /**
     * Get all DAs with performance scores
     * GET /api/da/agents
     */
    public function getAgents(Request $request): JsonResponse
    {
        $query = DeliveryAgent::with(['user', 'zobin'])
            ->where('status', 'active');

        // Apply filters
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        if ($request->has('performance_min')) {
            $query->where('success_rate', '>=', $request->performance_min);
        }

        if ($request->has('strikes_max')) {
            $query->where('strikes_count', '<=', $request->strikes_max);
        }

        if ($request->has('search')) {
            $query->where(function($q) use ($request) {
                $q->where('da_code', 'like', "%{$request->search}%")
                  ->orWhereHas('user', function($userQuery) use ($request) {
                      $userQuery->where('name', 'like', "%{$request->search}%");
                  });
            });
        }

        $agents = $query->orderBy('success_rate', 'desc')
            ->paginate($request->get('per_page', 20));

        // Add performance scores
        $agents->getCollection()->transform(function ($agent) {
            $agent->performance_score = $this->calculatePerformanceScore($agent);
            return $agent;
        });

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Individual DA performance
     * GET /api/da/agents/{daId}/performance
     */
    public function getAgentPerformance($daId): JsonResponse
    {
        $agent = DeliveryAgent::with(['user', 'zobin'])->find($daId);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery agent not found'
            ], 404);
        }

        // Get performance metrics for last 30 days
        $thirtyDaysAgo = now()->subDays(30);
        
        $performanceMetrics = AgentPerformanceMetric::where('delivery_agent_id', $daId)
            ->where('metric_date', '>=', $thirtyDaysAgo)
            ->orderBy('metric_date')
            ->get();

        // Get recent violations
        $recentViolations = StrikeLog::where('delivery_agent_id', $daId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // Get recent activities
        $recentActivities = AgentActivityLog::where('delivery_agent_id', $daId)
            ->orderBy('created_at', 'desc')
            ->limit(10)
            ->get();

        // Calculate compliance score
        $complianceScore = $this->calculateIndividualComplianceScore($agent);

        $performanceData = [
            'agent' => $agent,
            'performance_score' => $this->calculatePerformanceScore($agent),
            'compliance_score' => $complianceScore,
            'performance_metrics' => $performanceMetrics,
            'recent_violations' => $recentViolations,
            'recent_activities' => $recentActivities,
            'stock_status' => $this->getAgentStockStatus($agent),
            'delivery_stats' => $this->getAgentDeliveryStats($agent)
        ];

        return response()->json([
            'success' => true,
            'data' => $performanceData
        ]);
    }

    /**
     * Update DA status
     * PUT /api/da/agents/{daId}/status
     */
    public function updateAgentStatus(Request $request, $daId): JsonResponse
    {
        $agent = DeliveryAgent::find($daId);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery agent not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,suspended,terminated,inactive',
            'reason' => 'required|string',
            'updated_by' => 'required|exists:users,id',
            'effective_date' => 'sometimes|date',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $agent->status;
        
        $agent->update([
            'status' => $request->status,
            'suspended_at' => $request->status === 'suspended' ? now() : null,
            'terminated_at' => $request->status === 'terminated' ? now() : null
        ]);

        // Log the status change
        AgentActivityLog::create([
            'delivery_agent_id' => $agent->id,
            'activity_type' => 'status_change',
            'description' => "Status changed from {$oldStatus} to {$request->status}",
            'activity_data' => [
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'reason' => $request->reason,
                'updated_by' => $request->updated_by,
                'effective_date' => $request->effective_date ?? now()->toDateString(),
                'notes' => $request->notes
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Agent status updated successfully',
            'data' => $agent->fresh()
        ]);
    }

    /**
     * Filter DAs by status/performance
     * GET /api/da/agents/filter
     */
    public function filterAgents(Request $request): JsonResponse
    {
        $query = DeliveryAgent::with(['user', 'zobin']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        if ($request->has('success_rate_min')) {
            $query->where('success_rate', '>=', $request->success_rate_min);
        }

        if ($request->has('success_rate_max')) {
            $query->where('success_rate', '<=', $request->success_rate_max);
        }

        if ($request->has('strikes_min')) {
            $query->where('strikes_count', '>=', $request->strikes_min);
        }

        if ($request->has('strikes_max')) {
            $query->where('strikes_count', '<=', $request->strikes_max);
        }

        if ($request->has('active_since')) {
            $query->where('created_at', '>=', $request->active_since);
        }

        if ($request->has('last_active')) {
            $query->where('last_active_at', '>=', now()->subDays($request->last_active));
        }

        $agents = $query->orderBy('success_rate', 'desc')
            ->paginate($request->get('per_page', 20));

        // Add performance scores
        $agents->getCollection()->transform(function ($agent) {
            $agent->performance_score = $this->calculatePerformanceScore($agent);
            return $agent;
        });

        return response()->json([
            'success' => true,
            'data' => $agents
        ]);
    }

    /**
     * Get violation categories
     * GET /api/da/violations/categories
     */
    public function getViolationCategories(): JsonResponse
    {
        $categories = [
            'late_login' => [
                'name' => 'Late Login',
                'description' => 'Login after 8:14 AM',
                'strike_value' => 1,
                'penalty' => '₦100 per minute late'
            ],
            'missed_photo' => [
                'name' => 'Missed Photo',
                'description' => 'Failed to submit required photos',
                'strike_value' => 1,
                'penalty' => '₦500 per missed photo'
            ],
            'low_stock' => [
                'name' => 'Low Stock',
                'description' => 'Stock below minimum threshold',
                'strike_value' => 1,
                'penalty' => '₦1000 per day below threshold'
            ],
            'delivery_failure' => [
                'name' => 'Delivery Failure',
                'description' => 'Failed delivery attempts',
                'strike_value' => 1,
                'penalty' => '₦2000 per failed delivery'
            ],
            'customer_complaint' => [
                'name' => 'Customer Complaint',
                'description' => 'Valid customer complaints',
                'strike_value' => 2,
                'penalty' => '₦5000 per complaint'
            ],
            'unauthorized_absence' => [
                'name' => 'Unauthorized Absence',
                'description' => 'Absence without notification',
                'strike_value' => 2,
                'penalty' => '₦10000 per day'
            ],
            'inventory_misuse' => [
                'name' => 'Inventory Misuse',
                'description' => 'Misuse or theft of inventory',
                'strike_value' => 3,
                'penalty' => 'Immediate suspension + investigation'
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $categories
        ]);
    }

    /**
     * Report new violation
     * POST /api/da/violations
     */
    public function reportViolation(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivery_agent_id' => 'required|exists:delivery_agents,id',
            'violation_type' => 'required|string',
            'description' => 'required|string',
            'reported_by' => 'required|exists:users,id',
            'evidence' => 'nullable|array',
            'evidence.*' => 'string',
            'severity' => 'sometimes|in:low,medium,high,critical',
            'strike_value' => 'sometimes|integer|min:1|max:3'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = DeliveryAgent::find($request->delivery_agent_id);
            
            // Determine strike value if not provided
            $strikeValue = $request->strike_value ?? $this->getStrikeValueForViolation($request->violation_type);
            
            // Check if this would result in 3+ strikes
            $newStrikeCount = $agent->strikes_count + $strikeValue;
            $requiresReview = $newStrikeCount >= 3;

            $violation = StrikeLog::create([
                'delivery_agent_id' => $request->delivery_agent_id,
                'violation_type' => $request->violation_type,
                'description' => $request->description,
                'reported_by' => $request->reported_by,
                'evidence' => $request->evidence,
                'severity' => $request->severity ?? 'medium',
                'strike_value' => $strikeValue,
                'status' => $requiresReview ? 'pending_review' : 'active',
                'requires_escalation' => $requiresReview
            ]);

            // Update agent strike count
            $agent->increment('strikes_count', $strikeValue);

            // Log the violation
            AgentActivityLog::create([
                'delivery_agent_id' => $request->delivery_agent_id,
                'activity_type' => 'violation_reported',
                'description' => "Violation reported: {$request->violation_type}",
                'activity_data' => [
                    'violation_id' => $violation->id,
                    'violation_type' => $request->violation_type,
                    'strike_value' => $strikeValue,
                    'new_strike_count' => $newStrikeCount
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Violation reported successfully',
                'data' => [
                    'violation' => $violation,
                    'agent_strikes' => $agent->fresh()->strikes_count,
                    'requires_review' => $requiresReview
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to report violation',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update violation status
     * PUT /api/da/violations/{violationId}
     */
    public function updateViolation(Request $request, $violationId): JsonResponse
    {
        $violation = StrikeLog::find($violationId);

        if (!$violation) {
            return response()->json([
                'success' => false,
                'message' => 'Violation not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:active,pending_review,resolved,escalated,dismissed',
            'resolution_notes' => 'nullable|string',
            'resolved_by' => 'required|exists:users,id',
            'action_taken' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $oldStatus = $violation->status;
        
        $violation->update([
            'status' => $request->status,
            'resolution_notes' => $request->resolution_notes,
            'resolved_by' => $request->resolved_by,
            'action_taken' => $request->action_taken,
            'resolved_at' => $request->status === 'resolved' ? now() : null
        ]);

        // Log the status change
        AgentActivityLog::create([
            'delivery_agent_id' => $violation->delivery_agent_id,
            'activity_type' => 'violation_status_change',
            'description' => "Violation status changed from {$oldStatus} to {$request->status}",
            'activity_data' => [
                'violation_id' => $violation->id,
                'old_status' => $oldStatus,
                'new_status' => $request->status,
                'resolved_by' => $request->resolved_by,
                'action_taken' => $request->action_taken
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Violation status updated successfully',
            'data' => $violation->fresh()
        ]);
    }

    /**
     * Get DA-specific violations
     * GET /api/da/violations/{daId}
     */
    public function getDAViolations($daId): JsonResponse
    {
        $agent = DeliveryAgent::find($daId);

        if (!$agent) {
            return response()->json([
                'success' => false,
                'message' => 'Delivery agent not found'
            ], 404);
        }

        $violations = StrikeLog::where('delivery_agent_id', $daId)
            ->with(['reportedBy'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        $violationSummary = [
            'total_violations' => StrikeLog::where('delivery_agent_id', $daId)->count(),
            'active_strikes' => $agent->strikes_count,
            'pending_reviews' => StrikeLog::where('delivery_agent_id', $daId)
                ->where('status', 'pending_review')
                ->count(),
            'resolved_violations' => StrikeLog::where('delivery_agent_id', $daId)
                ->where('status', 'resolved')
                ->count()
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'agent' => $agent,
                'summary' => $violationSummary,
                'violations' => $violations
            ]
        ]);
    }

    /**
     * Calculate login compliance
     */
    private function calculateLoginCompliance(): float
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) return 0;

        $today = now()->toDateString();
        $targetTime = Carbon::parse($today . ' 08:14:00');
        
        // This would typically check against actual login logs
        // For now, we'll simulate based on last activity
        $compliantDAs = DeliveryAgent::where('status', 'active')
            ->where('last_active_at', '>=', $targetTime)
            ->count();

        return round(($compliantDAs / $totalDAs) * 100, 1);
    }

    /**
     * Calculate photo compliance
     */
    private function calculatePhotoCompliance(): float
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) return 0;

        $today = now()->toDateString();
        
        // Check photo submissions for today
        $compliantDAs = PhotoAudit::whereDate('created_at', $today)
            ->where('status', 'approved')
            ->distinct('delivery_agent_id')
            ->count();

        return round(($compliantDAs / $totalDAs) * 100, 1);
    }

    /**
     * Calculate stock compliance
     */
    private function calculateStockCompliance(): float
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) return 0;

        $compliantDAs = DeliveryAgent::where('status', 'active')
            ->whereHas('zobin', function($query) {
                $query->whereRaw('MIN(shampoo_count, MIN(pomade_count, conditioner_count)) >= 3');
            })
            ->count();

        return round(($compliantDAs / $totalDAs) * 100, 1);
    }

    /**
     * Calculate delivery compliance
     */
    private function calculateDeliveryCompliance(): float
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) return 0;

        $compliantDAs = DeliveryAgent::where('status', 'active')
            ->where('success_rate', '>=', 80)
            ->count();

        return round(($compliantDAs / $totalDAs) * 100, 1);
    }

    /**
     * Calculate performance compliance
     */
    private function calculatePerformanceCompliance(): float
    {
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        
        if ($totalDAs === 0) return 0;

        $compliantDAs = DeliveryAgent::where('status', 'active')
            ->where('strikes_count', '<', 3)
            ->count();

        return round(($compliantDAs / $totalDAs) * 100, 1);
    }

    /**
     * Calculate performance score for an agent
     */
    private function calculatePerformanceScore($agent): float
    {
        $score = 0;
        
        // Success rate (40% weight)
        $score += ($agent->success_rate ?? 0) * 0.4;
        
        // Strike count (30% weight)
        $strikeScore = max(0, 100 - (($agent->strikes_count ?? 0) * 20));
        $score += $strikeScore * 0.3;
        
        // Stock compliance (20% weight)
        $stockScore = $agent->zobin && $agent->zobin->available_sets >= 3 ? 100 : 0;
        $score += $stockScore * 0.2;
        
        // Activity (10% weight)
        $activityScore = $agent->last_active_at && $agent->last_active_at->isToday() ? 100 : 0;
        $score += $activityScore * 0.1;
        
        return round($score, 1);
    }

    /**
     * Calculate individual compliance score
     */
    private function calculateIndividualComplianceScore($agent): float
    {
        $scores = [];
        
        // Login compliance
        $scores['login'] = $agent->last_active_at && $agent->last_active_at->isToday() ? 100 : 0;
        
        // Photo compliance
        $todayPhotos = PhotoAudit::where('delivery_agent_id', $agent->id)
            ->whereDate('created_at', today())
            ->where('status', 'approved')
            ->count();
        $scores['photo'] = $todayPhotos > 0 ? 100 : 0;
        
        // Stock compliance
        $scores['stock'] = $agent->zobin && $agent->zobin->available_sets >= 3 ? 100 : 0;
        
        // Strike compliance
        $scores['strikes'] = max(0, 100 - (($agent->strikes_count ?? 0) * 25));
        
        return round(array_sum($scores) / count($scores), 1);
    }

    /**
     * Get agent stock status
     */
    private function getAgentStockStatus($agent): array
    {
        if (!$agent->zobin) {
            return [
                'status' => 'no_bin',
                'message' => 'No bin assigned',
                'available_sets' => 0
            ];
        }

        $availableSets = $agent->zobin->available_sets;
        
        if ($availableSets >= 5) {
            return [
                'status' => 'excellent',
                'message' => 'Stock levels excellent',
                'available_sets' => $availableSets
            ];
        } elseif ($availableSets >= 3) {
            return [
                'status' => 'adequate',
                'message' => 'Stock levels adequate',
                'available_sets' => $availableSets
            ];
        } else {
            return [
                'status' => 'critical',
                'message' => 'Stock levels critical',
                'available_sets' => $availableSets
            ];
        }
    }

    /**
     * Get agent delivery stats
     */
    private function getAgentDeliveryStats($agent): array
    {
        $thirtyDaysAgo = now()->subDays(30);
        
        $recentDeliveries = DB::table('deliveries')
            ->where('delivery_agent_id', $agent->id)
            ->where('created_at', '>=', $thirtyDaysAgo)
            ->selectRaw('
                COUNT(*) as total_deliveries,
                SUM(CASE WHEN status = "delivered" THEN 1 ELSE 0 END) as successful_deliveries,
                AVG(CASE WHEN status = "delivered" THEN customer_rating ELSE NULL END) as avg_rating
            ')
            ->first();

        return [
            'total_deliveries' => $recentDeliveries->total_deliveries ?? 0,
            'successful_deliveries' => $recentDeliveries->successful_deliveries ?? 0,
            'success_rate' => $recentDeliveries->total_deliveries > 0 
                ? round(($recentDeliveries->successful_deliveries / $recentDeliveries->total_deliveries) * 100, 1)
                : 0,
            'avg_rating' => round($recentDeliveries->avg_rating ?? 0, 1)
        ];
    }

    /**
     * Get strike value for violation type
     */
    private function getStrikeValueForViolation(string $violationType): int
    {
        $strikeValues = [
            'late_login' => 1,
            'missed_photo' => 1,
            'low_stock' => 1,
            'delivery_failure' => 1,
            'customer_complaint' => 2,
            'unauthorized_absence' => 2,
            'inventory_misuse' => 3
        ];

        return $strikeValues[$violationType] ?? 1;
    }
} 
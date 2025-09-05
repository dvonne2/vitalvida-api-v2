<?php

namespace App\Http\Controllers;

use App\Models\DeliveryAgent;
use App\Models\ImDailyLog;
use App\Models\SystemRecommendation;
use Carbon\Carbon;
use Illuminate\Http\Request;

class InventoryManagerController extends Controller
{
    // GET /api/inventory/dashboard
    public function getDashboard(Request $request) 
    {
        $user = $request->user();
        $today = now()->toDateString();
        
        // Get or create today's log
        $dailyLog = ImDailyLog::firstOrCreate([
            'user_id' => $user->id,
            'log_date' => $today
        ]);
        
        // Check login penalty
        $loginPenalty = $this->calculateLoginPenalty($dailyLog);
        
        // Get DA review progress
        $totalDAs = DeliveryAgent::where('status', 'active')->count();
        $reviewedDAs = $dailyLog->das_reviewed_count;
        
        // Get pending recommendations
        $pendingRecommendations = SystemRecommendation::where('assigned_to', $user->id)
            ->where('status', 'pending')
            ->count();
            
        // Calculate penalty risk
        $penaltyRisk = $this->calculatePenaltyRisk($dailyLog, $totalDAs, $pendingRecommendations);
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'login_status' => [
                    'time' => $dailyLog->login_time,
                    'is_late' => $loginPenalty > 0,
                    'penalty' => $loginPenalty
                ],
                'da_review_progress' => [
                    'completed' => $reviewedDAs,
                    'total' => $totalDAs,
                    'percentage' => $totalDAs > 0 ? round(($reviewedDAs / $totalDAs) * 100) : 0
                ],
                'pending_actions' => $pendingRecommendations,
                'penalty_risk' => $penaltyRisk,
                'weekly_performance' => $this->calculateWeeklyPerformance($user->id)
            ]
        ]);
    }
    
    // POST /api/inventory/login-tracking
    public function trackLogin(Request $request) 
    {
        $user = $request->user();
        $today = now()->toDateString();
        $currentTime = now()->format('H:i:s');
        
        $dailyLog = ImDailyLog::updateOrCreate([
            'user_id' => $user->id,
            'log_date' => $today
        ], [
            'login_time' => $currentTime
        ]);
        
        // Calculate penalty if late
        $penalty = $this->calculateLoginPenalty($dailyLog);
        
        if ($penalty > 0) {
            $dailyLog->increment('penalty_amount', $penalty);
        }
        
        return response()->json([
            'status' => 'success',
            'login_time' => $currentTime,
            'penalty_applied' => $penalty,
            'message' => $penalty > 0 ? "Late login penalty: ₦{$penalty}" : 'Login recorded'
        ]);
    }
    
    // GET /api/inventory/weekly-performance
    public function getWeeklyPerformance(Request $request)
    {
        $user = $request->user();
        $performance = $this->calculateWeeklyPerformance($user->id);
        return response()->json([
            'status' => 'success',
            'data' => $performance
        ]);
    }

    private function calculateLoginPenalty($dailyLog) 
    {
        if (!$dailyLog->login_time) return 0;
        
        $deadline = '08:14:00';
        $loginTime = $dailyLog->login_time;
        
        if ($loginTime <= $deadline) return 0;
        
        // Calculate minutes late and apply ₦1,000 per 15-minute interval
        $deadlineCarbon = Carbon::createFromFormat('H:i:s', $deadline);
        $loginCarbon = Carbon::createFromFormat('H:i:s', $loginTime);
        $minutesLate = $loginCarbon->diffInMinutes($deadlineCarbon);
        
        return ceil($minutesLate / 15) * 1000;
    }

    private function calculatePenaltyRisk($dailyLog, $totalDAs, $pendingRecommendations)
    {
        $risk = 0;
        
        // Add risk for incomplete DA reviews
        $unreviewed = $totalDAs - $dailyLog->das_reviewed_count;
        $risk += $unreviewed * 300; // ₦300 per unreviewed DA
        
        // Add risk for pending recommendations
        $risk += $pendingRecommendations * 1000; // ₦1000 per pending recommendation
        
        return $risk;
    }

    private function calculateWeeklyPerformance($userId)
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();
        
        $weeklyLogs = ImDailyLog::where('user_id', $userId)
            ->whereBetween('log_date', [$weekStart->toDateString(), $weekEnd->toDateString()])
            ->get();
            
        return [
            'total_bonuses' => $weeklyLogs->sum('bonus_amount'),
            'total_penalties' => $weeklyLogs->sum('penalty_amount'),
            'net_performance' => $weeklyLogs->sum('bonus_amount') - $weeklyLogs->sum('penalty_amount')
        ];
    }
}

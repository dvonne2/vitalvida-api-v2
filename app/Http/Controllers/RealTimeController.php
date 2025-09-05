<?php

namespace App\Http\Controllers;

use App\Models\ImDailyLog;
use App\Models\DeliveryAgent;
use App\Models\SystemRecommendation;
use App\Models\PhotoAudit;
use Illuminate\Http\Request;
use Carbon\Carbon;

class RealTimeController extends Controller
{
    // GET /api/inventory/real-time/status
    public function getSystemStatus(Request $request)
    {
        $user = $request->user();
        $now = now();
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'current_time' => $now->format('H:i:s'),
                'current_date' => $now->format('Y-m-d'),
                'day_of_week' => $now->format('l'),
                'is_friday' => $now->format('l') === 'Friday',
                'system_deadlines' => $this->getActiveDeadlines($now),
                'live_penalties' => $this->getLivePenalties($user->id),
                'countdown_timers' => $this->getCountdownTimers($now),
                'system_health' => $this->getSystemHealth()
            ]
        ]);
    }
    
    // GET /api/inventory/real-time/countdown
    public function getCountdownTimers(Request $request)
    {
        $now = now();
        $timers = $this->getCountdownTimers($now);
        
        return response()->json([
            'status' => 'success',
            'data' => $timers,
            'server_time' => $now->toISOString(),
            'timezone' => $now->timezoneName
        ]);
    }
    
    // GET /api/inventory/real-time/penalties
    public function getLivePenalties(Request $request)
    {
        $user = $request->user();
        $today = today();
        
        $dailyLog = ImDailyLog::where('user_id', $user->id)
            ->where('log_date', $today)
            ->first();
            
        $penalties = [];
        
        if ($dailyLog) {
            // Login penalty
            if ($dailyLog->login_time && $dailyLog->login_time > '08:14:00') {
                $penalties[] = [
                    'type' => 'late_login',
                    'amount' => $this->calculateLoginPenalty($dailyLog->login_time),
                    'description' => 'Late login penalty',
                    'time' => $dailyLog->login_time
                ];
            }
            
            // Incomplete DA reviews
            $totalDAs = DeliveryAgent::where('status', 'active')->count();
            $unreviewed = max(0, $totalDAs - $dailyLog->das_reviewed_count);
            if ($unreviewed > 0) {
                $penalties[] = [
                    'type' => 'unreviewed_das',
                    'amount' => $unreviewed * 300,
                    'description' => "₦300 per unreviewed DA ({$unreviewed} remaining)",
                    'count' => $unreviewed
                ];
            }
            
            // Pending recommendations
            $pendingRecs = SystemRecommendation::where('assigned_to', $user->id)
                ->where('status', 'pending')
                ->count();
            if ($pendingRecs > 0) {
                $penalties[] = [
                    'type' => 'pending_recommendations',
                    'amount' => $pendingRecs * 1000,
                    'description' => "₦1000 per pending recommendation ({$pendingRecs} pending)",
                    'count' => $pendingRecs
                ];
            }
        }
        
        return response()->json([
            'status' => 'success',
            'data' => [
                'current_penalties' => $dailyLog->penalty_amount ?? 0,
                'potential_penalties' => $penalties,
                'total_risk' => collect($penalties)->sum('amount'),
                'current_bonuses' => $dailyLog->bonus_amount ?? 0,
                'net_performance' => ($dailyLog->bonus_amount ?? 0) - ($dailyLog->penalty_amount ?? 0)
            ]
        ]);
    }
    
    // POST /api/inventory/real-time/weekly-audit
    public function runWeeklyAudit(Request $request)
    {
        $now = now();
        
        if ($now->format('l') !== 'Friday' || $now->format('H') < '18') {
            return response()->json([
                'status' => 'error',
                'message' => 'Weekly audit only runs on Fridays at 6 PM or later'
            ], 422);
        }
        
        $user = $request->user();
        $weekStart = $now->startOfWeek();
        $weekEnd = $now->endOfWeek();
        
        $weeklyMetrics = $this->calculateWeeklyMetrics($user->id, $weekStart, $weekEnd);
        
        // Apply weekly bonuses/penalties
        $this->applyWeeklyScoring($user->id, $weeklyMetrics);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Weekly audit completed',
            'data' => $weeklyMetrics
        ]);
    }
    
    private function getActiveDeadlines($now)
    {
        $deadlines = [];
        $today = $now->format('Y-m-d');
        
        // Daily login deadline
        if ($now->format('H:i:s') < '08:14:00') {
            $deadlines['login'] = [
                'type' => 'login',
                'deadline' => $today . ' 08:14:00',
                'description' => 'Daily login deadline',
                'penalty_info' => '₦1,000 per 15-minute interval after deadline'
            ];
        }
        
        // Friday photo deadlines
        if ($now->format('l') === 'Friday') {
            if ($now->format('H:i:s') < '12:00:00') {
                $deadlines['photo_upload'] = [
                    'type' => 'photo_upload',
                    'deadline' => $today . ' 12:00:00',
                    'description' => 'DA photo upload deadline',
                    'penalty_info' => 'Affects weekly stock health score'
                ];
            }
            
            if ($now->format('H:i:s') < '16:00:00') {
                $deadlines['photo_review'] = [
                    'type' => 'photo_review',
                    'deadline' => $today . ' 16:00:00',
                    'description' => 'IM photo review deadline',
                    'penalty_info' => '₦2,500 penalty for mismatches'
                ];
            }
            
            if ($now->format('H:i:s') < '18:00:00') {
                $deadlines['weekly_audit'] = [
                    'type' => 'weekly_audit',
                    'deadline' => $today . ' 18:00:00',
                    'description' => 'Weekly stock health audit',
                    'penalty_info' => 'Final weekly scoring calculation'
                ];
            }
        }
        
        return $deadlines;
    }
    
    private function getCountdownTimers($now)
    {
        $timers = [];
        $deadlines = $this->getActiveDeadlines($now);
        
        foreach ($deadlines as $key => $deadline) {
            $deadlineTime = Carbon::parse($deadline['deadline']);
            $secondsRemaining = max(0, $deadlineTime->diffInSeconds($now));
            
            $timers[$key] = [
                'name' => $deadline['description'],
                'deadline' => $deadline['deadline'],
                'seconds_remaining' => $secondsRemaining,
                'formatted_time' => $this->formatCountdown($secondsRemaining),
                'is_urgent' => $secondsRemaining < 3600, // Less than 1 hour
                'penalty_info' => $deadline['penalty_info']
            ];
        }
        
        return $timers;
    }
    
    private function formatCountdown($seconds)
    {
        if ($seconds <= 0) return '00:00:00';
        
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        $secs = $seconds % 60;
        
        return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
    }
    
    private function calculateLoginPenalty($loginTime)
    {
        $deadline = Carbon::today()->setTimeFromTimeString('08:14:00');
        $login = Carbon::today()->setTimeFromTimeString($loginTime);
        
        if ($login <= $deadline) return 0;
        
        $minutesLate = $login->diffInMinutes($deadline);
        return ceil($minutesLate / 15) * 1000;
    }
    
    private function getSystemHealth()
    {
        return [
            'total_das' => DeliveryAgent::where('status', 'active')->count(),
            'critical_stock_das' => $this->getCriticalStockCount(),
            'pending_audits' => PhotoAudit::where('status', 'pending_im_review')->count(),
            'system_uptime' => '99.9%',
            'last_update' => now()->format('Y-m-d H:i:s')
        ];
    }
    
    private function getCriticalStockCount()
    {
        // Simplified count - can be enhanced with actual bin data
        return 0;
    }
    
    private function calculateWeeklyMetrics($userId, $weekStart, $weekEnd)
    {
        // Placeholder for weekly metrics calculation
        return [
            'week_period' => $weekStart->format('M d') . ' - ' . $weekEnd->format('M d'),
            'total_penalties' => 0,
            'total_bonuses' => 0,
            'net_performance' => 0
        ];
    }
    
    private function applyWeeklyScoring($userId, $metrics)
    {
        // Placeholder for weekly scoring application
        return true;
    }
}

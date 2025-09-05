<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\ActivityLog;
use App\Models\SystemLog;
use App\Models\SecurityEvent;

class DashboardController extends Controller
{
    /**
     * Display the admin dashboard
     */
    public function index()
    {
        // Get dashboard statistics
        $stats = [
            'total_users' => User::count(),
            'active_users' => User::where('status', 'active')->count(),
            'total_activities' => ActivityLog::count(),
            'todays_activities' => ActivityLog::whereDate('timestamp', today())->count(),
            'system_logs' => SystemLog::count(),
            'security_events' => SecurityEvent::count(),
            'recent_activities' => ActivityLog::with('user')
                ->orderBy('timestamp', 'desc')
                ->limit(10)
                ->get(),
            'recent_security_events' => SecurityEvent::with('user')
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get()
        ];

        return view('admin.dashboard', compact('stats'));
    }
} 
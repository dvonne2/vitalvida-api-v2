<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function getSummary()
    {
        // Mock dashboard data
        $summary = [
            'total_bins' => 156,
            'low_stock_alerts' => 23,
            'photo_verification_issues' => 8,
            'active_users' => 45,
            'revenue_today' => 2847.50,
            'pending_orders' => 12,
            'system_health' => 'good'
        ];

        return response()->json([
            'status' => 'success',
            'data' => $summary
        ]);
    }
}

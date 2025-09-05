<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class PerformanceController extends Controller
{
    public function getSummary()
    {
        $performanceData = [
            'total_delivery_agents' => 70,
            'on_time_updates' => 52,
            'late_updates' => 18,
            'average_inventory_resolution_time_mins' => 47,
            'strikes_this_week' => 14,
            'top_performers' => [
                ['name' => 'Agent A', 'deliveries' => 48, 'strikes' => 0],
                ['name' => 'Agent B', 'deliveries' => 45, 'strikes' => 1],
                ['name' => 'Agent C', 'deliveries' => 42, 'strikes' => 0],
                ['name' => 'Agent D', 'deliveries' => 40, 'strikes' => 2]
            ],
            'compliance_metrics' => [
                'update_compliance_rate' => 74.3,
                'zero_strike_agents' => 48,
                'avg_delivery_time_mins' => 28
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $performanceData
        ]);
    }

    public function getInventoryManagerSummary()
    {
        $performanceData = [
            'low_stock_alerts_unresolved' => 3,
            'restock_timeliness_percent' => 88,
            'photo_reviews_done' => 97,
            'flags_resolved' => 15,
            'on_time_package_rate' => 91,
            'strikes_received' => 1,
            'overall_score' => 87,
            'total_low_stock_alerts' => 28,
            'avg_resolution_time_hours' => 4.2,
            'photo_verification_accuracy' => 94,
            'inventory_discrepancies_found' => 8,
            'discrepancies_resolved' => 7,
            'package_accuracy_rate' => 96,
            'audit_compliance_score' => 92,
            'category_scores' => [
                'stock_management' => 89,
                'photo_verification' => 92,
                'package_handling' => 88,
                'audit_compliance' => 85,
                'communication' => 90
            ],
            'performance_period' => 'July 2025',
            'last_updated' => now()->format('Y-m-d H:i:s')
        ];

        return response()->json([
            'status' => 'success',
            'data' => $performanceData
        ]);
    }
}

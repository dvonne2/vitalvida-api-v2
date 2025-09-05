<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class InsightController extends Controller
{
    public function getBinSuggestions()
    {
        $binSuggestions = [
            [
                'bin_id' => 'BIN-101',
                'location' => 'Lagos Mainland - Emeka',
                'status' => 'Needs Restock',
                'reason' => 'Stock below 10% threshold',
                'priority' => 'high',
                'current_stock' => 8,
                'capacity' => 100,
                'stock_percentage' => 8,
                'last_movement' => '2025-07-09',
                'predicted_depletion' => '2025-07-11',
                'suggested_action' => 'Immediate restocking - 75 units'
            ],
            [
                'bin_id' => 'BIN-204',
                'location' => 'Ibadan - Zainab',
                'status' => 'Suspicious Inactivity',
                'reason' => 'No movement in last 7 days',
                'priority' => 'medium',
                'current_stock' => 45,
                'capacity' => 80,
                'stock_percentage' => 56,
                'last_movement' => '2025-07-02',
                'suggested_action' => 'Contact agent for status update'
            ],
            [
                'bin_id' => 'BIN-309',
                'location' => 'Port Harcourt - Musa',
                'status' => 'Excess Returns',
                'reason' => 'Return rate > 40% this week',
                'priority' => 'high',
                'current_stock' => 67,
                'capacity' => 90,
                'stock_percentage' => 74,
                'return_rate' => 42,
                'suggested_action' => 'Investigate quality issues and DA performance'
            ],
            [
                'bin_id' => 'BIN-156',
                'location' => 'Abuja Central - Fatima',
                'status' => 'Fast Depletion',
                'reason' => 'Stock decreasing 15% daily',
                'priority' => 'medium',
                'current_stock' => 22,
                'capacity' => 75,
                'stock_percentage' => 29,
                'depletion_rate' => 15,
                'suggested_action' => 'Schedule restocking within 24 hours'
            ]
        ];

        $summary = [
            'total_bins_flagged' => count($binSuggestions),
            'priority_breakdown' => [
                'high' => 2,
                'medium' => 2,
                'low' => 0
            ],
            'immediate_action_required' => 2,
            'avg_stock_level' => 42.3
        ];

        return response()->json([
            'status' => 'success',
            'data' => $binSuggestions,
            'summary' => $summary
        ]);
    }
}

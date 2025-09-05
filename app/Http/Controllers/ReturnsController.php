<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class ReturnsController extends Controller
{
    public function getMisreportedReturns()
    {
        $misreportedReturns = [
            [
                'return_id' => 'RET-203',
                'agent_id' => 501,
                'agent_name' => 'Emeka Ogbonna',
                'item' => 'FHG Shampoo',
                'claimed_return_qty' => 2,
                'actual_found_qty' => 0,
                'discrepancy_reason' => 'Item not in warehouse',
                'return_date_claimed' => '2025-07-08',
                'warehouse_audit_date' => '2025-07-09',
                'discrepancy_value' => 15000,
                'risk_level' => 'high',
                'investigation_status' => 'pending'
            ],
            [
                'return_id' => 'RET-210',
                'agent_id' => 504,
                'agent_name' => 'Chioma Onwudiwe',
                'item' => 'FHG Pomade',
                'claimed_return_qty' => 1,
                'actual_found_qty' => 1,
                'discrepancy_reason' => 'Returned late – beyond allowed window',
                'return_date_claimed' => '2025-07-05',
                'warehouse_audit_date' => '2025-07-09',
                'discrepancy_value' => 0,
                'risk_level' => 'medium',
                'investigation_status' => 'resolved'
            ],
            [
                'return_id' => 'RET-195',
                'agent_id' => 503,
                'agent_name' => 'Ibrahim Sani',
                'item' => 'Vitamin C Tablets',
                'claimed_return_qty' => 3,
                'actual_found_qty' => 1,
                'discrepancy_reason' => 'Quantity mismatch - only 1 unit found',
                'return_date_claimed' => '2025-07-07',
                'warehouse_audit_date' => '2025-07-09',
                'discrepancy_value' => 8600,
                'risk_level' => 'high',
                'investigation_status' => 'investigating'
            ]
        ];

        $summary = [
            'total_misreported_returns' => count($misreportedReturns),
            'total_financial_impact' => array_sum(array_column($misreportedReturns, 'discrepancy_value')),
            'critical_cases' => 0,
            'high_risk_cases' => 2
        ];

        return response()->json([
            'status' => 'success',
            'data' => $misreportedReturns,
            'summary' => $summary
        ]);
    }
}

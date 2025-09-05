<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class StrikeController extends Controller
{
    public function getLog()
    {
        $strikes = [
            [
                'id' => 1,
                'agent_name' => 'John Martinez',
                'role' => 'Delivery Agent',
                'reason' => 'Failed to update delivery status within 30 minutes',
                'strike_type' => 'Late Update',
                'strike_weight' => 2,
                'created_at' => '2025-01-09T14:23:00Z',
                'resolved' => false
            ],
            [
                'id' => 2,
                'agent_name' => 'Sarah Chen',
                'role' => 'Inventory Manager',
                'reason' => 'Stock count discrepancy of 15 units in bin A-12',
                'strike_type' => 'Inventory Mismatch',
                'strike_weight' => 3,
                'created_at' => '2025-01-09T11:45:00Z',
                'resolved' => true
            ],
            [
                'id' => 3,
                'agent_name' => 'Mike Thompson',
                'role' => 'Delivery Agent',
                'reason' => 'Missed delivery window by 2 hours without notification',
                'strike_type' => 'Delivery Delay',
                'strike_weight' => 3,
                'created_at' => '2025-01-09T09:15:00Z',
                'resolved' => false
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => [
                'strikes' => $strikes,
                'summary' => [
                    'total_strikes' => count($strikes),
                    'unresolved_strikes' => count(array_filter($strikes, fn($s) => !$s['resolved']))
                ]
            ]
        ]);
    }

    public function getStrikeLog($id)
    {
        $strikeDatabase = [
            501 => [
                'agent_id' => 501,
                'agent_name' => 'Emeka Ogbonna',
                'total_strikes' => 3,
                'current_status' => 'warning',
                'last_strike_date' => '2025-06-22',
                'strike_history' => [
                    [
                        'strike_id' => 'STK-501-001',
                        'date' => '2025-06-12',
                        'type' => 'Late Photo Submission',
                        'severity' => 'minor',
                        'notes' => 'Photo uploaded 3 hours late for bin verification',
                        'issued_by' => 'Benjamin (IM)',
                        'order_id' => 'DEL-19201',
                        'penalty_points' => 1
                    ],
                    [
                        'strike_id' => 'STK-501-002',
                        'date' => '2025-06-18',
                        'type' => 'Delivery without Payment',
                        'severity' => 'major',
                        'notes' => 'Order DEL-19288 was marked delivered before payment confirmation received',
                        'issued_by' => 'Sarah Chen (IM)',
                        'order_id' => 'DEL-19288',
                        'penalty_points' => 3
                    ],
                    [
                        'strike_id' => 'STK-501-003',
                        'date' => '2025-06-22',
                        'type' => 'Incorrect Return Record',
                        'severity' => 'major',
                        'notes' => 'Item marked as returned not found in warehouse during audit',
                        'issued_by' => 'Lisa Rodriguez (IM)',
                        'order_id' => 'RET-203',
                        'penalty_points' => 3
                    ]
                ],
                'strike_summary' => [
                    'minor_strikes' => 1,
                    'major_strikes' => 2,
                    'total_penalty_points' => 7
                ]
            ],
            502 => [
                'agent_id' => 502,
                'agent_name' => 'Zainab Ibrahim',
                'total_strikes' => 0,
                'current_status' => 'excellent',
                'strike_history' => [],
                'strike_summary' => [
                    'minor_strikes' => 0,
                    'major_strikes' => 0,
                    'total_penalty_points' => 0
                ]
            ]
        ];

        if (!isset($strikeDatabase[$id])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Delivery agent not found',
                'agent_id' => (int)$id
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'data' => $strikeDatabase[$id]
        ]);
    }
}

<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AuditController extends Controller
{
    public function resolveDiscrepancy(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bin_id' => 'required|integer',
            'resolution_note' => 'required|string|max:1000',
            'resolved_by' => 'required|string|max:100',
            'action_taken' => 'required|string|in:adjusted Zoho,DA penalized,no action,photo retaken,inventory recount'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $resolution = [
            'id' => rand(10000, 99999),
            'bin_id' => $request->bin_id,
            'resolved_by' => $request->resolved_by,
            'action_taken' => $request->action_taken,
            'resolution_note' => $request->resolution_note,
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'status' => 'resolved'
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Discrepancy resolved',
            'data' => $resolution
        ], 201);
    }

    public function getPendingReview()
    {
        $pendingReviews = [
            [
                'id' => 10245,
                'bin_id' => 305,
                'product' => 'Fulani Conditioner',
                'resolution_note' => 'Adjusted bin from 45 to 40 units after physical recount. Photo evidence confirms correct quantity.',
                'submitted_by' => 'Benjamin',
                'submitted_on' => '2025-07-08 14:10:00',
                'status' => 'pending',
                'action_taken' => 'adjusted Zoho',
                'financial_impact' => -150.00,
                'priority' => 'medium'
            ],
            [
                'id' => 10267,
                'bin_id' => 289,
                'product' => 'Fulani Pomade',
                'resolution_note' => 'DA confirmed mismatch during delivery check. Inventory adjustment needed to reflect actual stock.',
                'submitted_by' => 'Benjamin',
                'submitted_on' => '2025-07-09 08:22:00',
                'status' => 'pending',
                'action_taken' => 'inventory recount',
                'financial_impact' => -89.50,
                'priority' => 'high'
            ],
            [
                'id' => 10289,
                'bin_id' => 412,
                'product' => 'Vitamin C Tablets',
                'resolution_note' => 'Photo verification revealed overstocking. Updated Zoho records to match physical count.',
                'submitted_by' => 'Sarah Chen',
                'submitted_on' => '2025-07-09 11:45:00',
                'status' => 'pending',
                'action_taken' => 'adjusted Zoho',
                'financial_impact' => 245.75,
                'priority' => 'medium'
            ]
        ];

        $summary = [
            'total_pending_reviews' => count($pendingReviews),
            'total_financial_impact' => array_sum(array_column($pendingReviews, 'financial_impact')),
            'critical_priority' => 0,
            'high_priority' => 1
        ];

        return response()->json([
            'status' => 'success',
            'data' => $pendingReviews,
            'summary' => $summary
        ]);
    }

    public function processReview(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bin_id' => 'required|integer',
            'decision' => 'required|string|in:approved,rejected',
            'reviewed_by' => 'required|string|max:100',
            'comment' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $review = [
            'id' => rand(20000, 29999),
            'bin_id' => $request->bin_id,
            'decision' => $request->decision,
            'reviewed_by' => $request->reviewed_by,
            'comment' => $request->comment ?? 'No additional comments',
            'timestamp' => now()->format('Y-m-d H:i:s'),
            'review_status' => $request->decision === 'approved' ? 'completed' : 'escalated'
        ];

        $message = $request->decision === 'approved' 
            ? 'Audit resolution approved' 
            : 'Audit resolution rejected';

        if ($request->decision === 'approved') {
            $review['approval_code'] = 'FC-' . strtoupper(substr(md5($request->bin_id . time()), 0, 8));
        }

        return response()->json([
            'status' => 'success',
            'message' => $message,
            'data' => $review
        ], 201);
    }

    public function getLogs()
    {
        $auditLogs = [
            [
                'log_id' => 'LOG-001',
                'timestamp' => '2025-07-09T08:12:45Z',
                'action' => 'Stock Sent to BIN-204',
                'user' => 'Inventory Officer - Daniel',
                'notes' => '20 packs of FHG Conditioner sent to Ibadan location',
                'category' => 'stock_movement',
                'severity' => 'info',
                'bin_id' => 'BIN-204',
                'value' => 60000,
                'status' => 'completed'
            ],
            [
                'log_id' => 'LOG-002',
                'timestamp' => '2025-07-09T09:47:22Z',
                'action' => 'DA Return Flag Raised',
                'user' => 'System',
                'notes' => 'DA Emeka marked item returned, not found in warehouse audit',
                'category' => 'flag_raised',
                'severity' => 'warning',
                'return_id' => 'RET-203',
                'value' => 15000,
                'status' => 'investigating'
            ],
            [
                'log_id' => 'LOG-003',
                'timestamp' => '2025-07-09T10:05:19Z',
                'action' => 'Flag Resolved',
                'user' => 'Audit - Joy',
                'notes' => 'Flag on FHG Pomade marked false alarm - item found in different location',
                'category' => 'flag_resolved',
                'severity' => 'info',
                'resolution_type' => 'false_alarm',
                'status' => 'closed'
            ],
            [
                'log_id' => 'LOG-004',
                'timestamp' => '2025-07-09T11:23:17Z',
                'action' => 'Photo Verification Failed',
                'user' => 'System',
                'notes' => 'DA Ibrahim photo submission rejected - blurry image quality',
                'category' => 'photo_verification',
                'severity' => 'warning',
                'agent_id' => 503,
                'status' => 'pending_resubmission'
            ],
            [
                'log_id' => 'LOG-005',
                'timestamp' => '2025-07-09T12:45:33Z',
                'action' => 'Strike Issued',
                'user' => 'Inventory Manager - Benjamin',
                'notes' => 'Strike issued to DA Emeka for false return claim',
                'category' => 'disciplinary',
                'severity' => 'major',
                'agent_id' => 501,
                'penalty_points' => 3,
                'status' => 'active'
            ]
        ];

        $summary = [
            'total_log_entries' => count($auditLogs),
            'logs_today' => count($auditLogs),
            'critical_items' => 0,
            'pending_investigations' => 2
        ];

        return response()->json([
            'status' => 'success',
            'data' => $auditLogs,
            'summary' => $summary
        ]);
    }
}

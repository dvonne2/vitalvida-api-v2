<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PhotoVerificationController extends Controller
{
    public function create(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bin_id' => 'required|integer',
            'photo_url' => 'required|string|url',
            'notes' => 'nullable|string|max:500',
            'submitted_by' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $entry = [
            'id' => rand(1000, 9999),
            'bin_id' => $request->bin_id,
            'photo_url' => $request->photo_url,
            'notes' => $request->notes,
            'submitted_by' => $request->submitted_by,
            'timestamp' => now()->format('Y-m-d H:i:s')
        ];

        return response()->json([
            'status' => 'success',
            'message' => 'Photo verification entry saved.',
            'data' => $entry
        ], 201);
    }

    public function getComparison()
    {
        $comparisons = [
            [
                'bin_id' => 101,
                'product' => 'Fulani Shampoo',
                'zoho_quantity' => 50,
                'photo_quantity' => 45,
                'da_entry' => 60,
                'mismatch' => true,
                'mismatch_type' => 'DA Overcount',
                'severity' => 'medium'
            ],
            [
                'bin_id' => 102,
                'product' => 'Fulani Conditioner',
                'zoho_quantity' => 70,
                'photo_quantity' => 70,
                'da_entry' => 70,
                'mismatch' => false,
                'mismatch_type' => null,
                'severity' => 'none'
            ],
            [
                'bin_id' => 103,
                'product' => 'Vitamin C Tablets',
                'zoho_quantity' => 120,
                'photo_quantity' => 115,
                'da_entry' => 125,
                'mismatch' => true,
                'mismatch_type' => 'Multiple Discrepancies',
                'severity' => 'high'
            ]
        ];

        $summary = [
            'total_comparisons' => count($comparisons),
            'total_mismatches' => count(array_filter($comparisons, fn($c) => $c['mismatch'])),
            'accuracy_rate' => 33.3
        ];

        return response()->json([
            'status' => 'success',
            'data' => $comparisons,
            'summary' => $summary
        ]);
    }

    public function getFlags()
    {
        $flags = [
            [
                'bin_id' => 203,
                'product' => 'Fulani Pomade',
                'issue' => 'No photo submitted',
                'flagged_on' => '2025-07-08 13:24:00',
                'severity' => 'high',
                'days_overdue' => 2,
                'assigned_agent' => 'Mike Johnson'
            ],
            [
                'bin_id' => 207,
                'product' => 'Fulani Conditioner',
                'issue' => 'Photo quantity lower than Zoho',
                'flagged_on' => '2025-07-09 09:12:00',
                'severity' => 'medium',
                'discrepancy_amount' => -15,
                'assigned_agent' => 'Sarah Chen'
            ],
            [
                'bin_id' => 155,
                'product' => 'Vitamin C Tablets',
                'issue' => 'DA entry significantly higher than photo',
                'flagged_on' => '2025-07-09 11:30:00',
                'severity' => 'high',
                'discrepancy_amount' => 25,
                'assigned_agent' => 'John Martinez'
            ]
        ];

        $summary = [
            'total_flags' => count($flags),
            'critical_flags' => 0,
            'high_priority_flags' => 2,
            'overdue_submissions' => 1
        ];

        return response()->json([
            'status' => 'success',
            'data' => $flags,
            'summary' => $summary
        ]);
    }
}

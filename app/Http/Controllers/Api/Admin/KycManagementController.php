<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycLog;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class KycManagementController extends Controller
{
    /**
     * Get pending KYC applications
     */
    public function pendingApplications(Request $request)
    {
        $query = User::where('kyc_status', 'pending');

        // Apply filters
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $applications = $query->with(['kycLogs' => function ($query) {
            $query->orderBy('created_at', 'desc');
        }])
        ->orderBy('created_at', 'desc')
        ->paginate($request->get('per_page', 20));

        // Transform data for admin view
        $applications->getCollection()->transform(function ($user) {
            return [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => $user->created_at,
                'kyc_data' => $user->kyc_data,
                'documents_count' => $user->kycLogs->count(),
                'pending_documents' => $user->kycLogs->where('status', 'pending')->count(),
                'approved_documents' => $user->kycLogs->where('status', 'approved')->count(),
                'rejected_documents' => $user->kycLogs->where('status', 'rejected')->count(),
                'documents' => $user->kycLogs->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'document_type' => $log->document_type,
                        'status' => $log->status,
                        'submitted_at' => $log->created_at,
                        'verified_at' => $log->verified_at,
                        'rejection_reason' => $log->rejection_reason,
                    ];
                }),
            ];
        });

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Get all KYC applications with filters
     */
    public function allApplications(Request $request)
    {
        $query = User::whereNotNull('kyc_status');

        // Apply filters
        if ($request->has('kyc_status') && $request->kyc_status) {
            $query->where('kyc_status', $request->kyc_status);
        }

        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%")
                  ->orWhere('phone', 'like', "%{$search}%");
            });
        }

        if ($request->has('role') && $request->role) {
            $query->where('role', $request->role);
        }

        if ($request->has('date_from') && $request->date_from) {
            $query->where('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to') && $request->date_to) {
            $query->where('created_at', '<=', $request->date_to);
        }

        $applications = $query->with(['kycLogs'])
            ->orderBy('created_at', 'desc')
            ->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $applications,
            'filters' => [
                'kyc_statuses' => ['pending', 'approved', 'rejected'],
                'roles' => User::ROLES,
            ]
        ]);
    }

    /**
     * Approve KYC application
     */
    public function approveKyc(Request $request, $userId)
    {
        $user = User::findOrFail($userId);

        if ($user->kyc_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KYC application is not pending',
                'error_code' => 'KYC_NOT_PENDING'
            ], 400);
        }

        $user->update([
            'kyc_status' => 'approved',
            'kyc_data' => array_merge($user->kyc_data ?? [], [
                'approved_at' => now(),
                'approved_by' => $request->user()->id,
            ])
        ]);

        // Update all KYC logs to approved
        KycLog::where('user_id', $userId)->update([
            'status' => 'approved',
            'verified_at' => now(),
            'verified_by' => $request->user()->id,
        ]);

        // Log the action
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.kyc.approve',
            'model_type' => User::class,
            'model_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'approved_user_id' => $userId,
                'kyc_status' => 'approved'
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC application approved successfully'
        ]);
    }

    /**
     * Reject KYC application
     */
    public function rejectKyc(Request $request, $userId)
    {
        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::findOrFail($userId);

        if ($user->kyc_status !== 'pending') {
            return response()->json([
                'success' => false,
                'message' => 'KYC application is not pending',
                'error_code' => 'KYC_NOT_PENDING'
            ], 400);
        }

        $user->update([
            'kyc_status' => 'rejected',
            'kyc_data' => array_merge($user->kyc_data ?? [], [
                'rejected_at' => now(),
                'rejected_by' => $request->user()->id,
                'rejection_reason' => $request->reason,
            ])
        ]);

        // Update all KYC logs to rejected
        KycLog::where('user_id', $userId)->update([
            'status' => 'rejected',
            'rejection_reason' => $request->reason,
        ]);

        // Log the action
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.kyc.reject',
            'model_type' => User::class,
            'model_id' => $userId,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'rejected_user_id' => $userId,
                'kyc_status' => 'rejected',
                'reason' => $request->reason
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC application rejected successfully'
        ]);
    }

    /**
     * Get KYC statistics
     */
    public function kycStats(Request $request)
    {
        $stats = [
            'total_applications' => User::whereNotNull('kyc_status')->count(),
            'pending_applications' => User::where('kyc_status', 'pending')->count(),
            'approved_applications' => User::where('kyc_status', 'approved')->count(),
            'rejected_applications' => User::where('kyc_status', 'rejected')->count(),
            'approval_rate' => User::where('kyc_status', 'approved')->count() / max(User::whereNotNull('kyc_status')->count(), 1) * 100,
            'rejection_rate' => User::where('kyc_status', 'rejected')->count() / max(User::whereNotNull('kyc_status')->count(), 1) * 100,
        ];

        // Get KYC applications by role
        $byRole = User::selectRaw('role, kyc_status, COUNT(*) as count')
            ->whereNotNull('kyc_status')
            ->groupBy('role', 'kyc_status')
            ->get()
            ->groupBy('role')
            ->map(function ($group) {
                return $group->pluck('count', 'kyc_status');
            });

        // Get KYC applications by date (last 30 days)
        $byDate = User::selectRaw('DATE(created_at) as date, kyc_status, COUNT(*) as count')
            ->whereNotNull('kyc_status')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date', 'kyc_status')
            ->orderBy('date')
            ->get()
            ->groupBy('date');

        return response()->json([
            'success' => true,
            'data' => [
                'stats' => $stats,
                'by_role' => $byRole,
                'by_date' => $byDate,
            ]
        ]);
    }

    /**
     * Bulk approve KYC applications
     */
    public function bulkApprove(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $users = User::whereIn('id', $request->user_ids)
            ->where('kyc_status', 'pending')
            ->get();

        $approvedCount = 0;
        foreach ($users as $user) {
            $user->update([
                'kyc_status' => 'approved',
                'kyc_data' => array_merge($user->kyc_data ?? [], [
                    'approved_at' => now(),
                    'approved_by' => $request->user()->id,
                ])
            ]);

            KycLog::where('user_id', $user->id)->update([
                'status' => 'approved',
                'verified_at' => now(),
                'verified_by' => $request->user()->id,
            ]);

            $approvedCount++;
        }

        // Log the action
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.kyc.bulk_approve',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'approved_user_ids' => $request->user_ids,
                'approved_count' => $approvedCount,
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => "Successfully approved {$approvedCount} KYC applications",
            'data' => [
                'approved_count' => $approvedCount,
                'total_requested' => count($request->user_ids),
            ]
        ]);
    }

    /**
     * Bulk reject KYC applications
     */
    public function bulkReject(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'user_ids' => 'required|array|min:1',
            'user_ids.*' => 'integer|exists:users,id',
            'reason' => 'required|string|max:500',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $users = User::whereIn('id', $request->user_ids)
            ->where('kyc_status', 'pending')
            ->get();

        $rejectedCount = 0;
        foreach ($users as $user) {
            $user->update([
                'kyc_status' => 'rejected',
                'kyc_data' => array_merge($user->kyc_data ?? [], [
                    'rejected_at' => now(),
                    'rejected_by' => $request->user()->id,
                    'rejection_reason' => $request->reason,
                ])
            ]);

            KycLog::where('user_id', $user->id)->update([
                'status' => 'rejected',
                'rejection_reason' => $request->reason,
            ]);

            $rejectedCount++;
        }

        // Log the action
        ActionLog::create([
            'user_id' => $request->user()->id,
            'action' => 'admin.kyc.bulk_reject',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'rejected_user_ids' => $request->user_ids,
                'rejected_count' => $rejectedCount,
                'reason' => $request->reason,
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => "Successfully rejected {$rejectedCount} KYC applications",
            'data' => [
                'rejected_count' => $rejectedCount,
                'total_requested' => count($request->user_ids),
            ]
        ]);
    }
} 
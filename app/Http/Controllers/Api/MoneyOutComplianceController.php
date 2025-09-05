<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MoneyOutCompliance;
use App\Models\Order;
use App\Models\FileUpload;
use App\Services\FileUploadService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class MoneyOutComplianceController extends Controller
{
    protected $fileUploadService;

    public function __construct(FileUploadService $fileUploadService)
    {
        $this->fileUploadService = $fileUploadService;
    }

    /**
     * Display a listing of compliance records with filtering and pagination
     */
    public function index(Request $request)
    {
        $query = MoneyOutCompliance::with(['order', 'deliveryAgent', 'paidBy']);

        // Apply filters
        if ($request->has('status')) {
            $query->where('compliance_status', $request->status);
        }

        if ($request->has('delivery_agent_id')) {
            $query->where('delivery_agent_id', $request->delivery_agent_id);
        }

        if ($request->boolean('ready_for_payment')) {
            $query->readyForPayment();
        }

        if ($request->boolean('overdue')) {
            $query->overdue();
        }

        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'created_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        $perPage = $request->get('per_page', 20);
        $compliances = $query->paginate($perPage);

        // Add calculated fields to each record
        $compliances->getCollection()->transform(function ($compliance) {
            $compliance->compliance_score = $compliance->calculateComplianceScore();
            $compliance->missing_criteria = $compliance->getMissingCriteria();
            $compliance->priority_level = $compliance->getPriorityLevel();
            $compliance->is_overdue = $compliance->isOverdue();
            $compliance->status_description = $compliance->getComplianceStatusDescription();
            return $compliance;
        });

        // Summary statistics
        $summary = [
            'total_ready' => MoneyOutCompliance::ready()->count(),
            'total_locked' => MoneyOutCompliance::locked()->count(),
            'total_paid' => MoneyOutCompliance::paid()->count(),
            'total_overdue' => MoneyOutCompliance::overdue()->count(),
            'total_ready_for_payment' => MoneyOutCompliance::readyForPayment()->count(),
            'total_amount_locked' => MoneyOutCompliance::locked()->sum('amount'),
            'total_amount_paid_today' => MoneyOutCompliance::paid()
                ->whereDate('paid_at', today())
                ->sum('amount')
        ];

        return response()->json([
            'success' => true,
            'data' => $compliances,
            'summary' => $summary,
            'filters_applied' => $request->only([
                'status', 'delivery_agent_id', 'ready_for_payment', 
                'overdue', 'date_from', 'date_to'
            ])
        ]);
    }

    /**
     * Display the specified compliance record
     */
    public function show($id)
    {
        $compliance = MoneyOutCompliance::with([
            'order', 'deliveryAgent', 'paidBy', 'fileUploads', 'auditLogs'
        ])->findOrFail($id);

        // Add calculated fields
        $compliance->compliance_score = $compliance->calculateComplianceScore();
        $compliance->missing_criteria = $compliance->getMissingCriteria();
        $compliance->priority_level = $compliance->getPriorityLevel();
        $compliance->is_overdue = $compliance->isOverdue();
        $compliance->status_description = $compliance->getComplianceStatusDescription();

        return response()->json([
            'success' => true,
            'data' => $compliance
        ]);
    }

    /**
     * Update compliance verification status
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_verified' => 'boolean',
            'otp_submitted' => 'boolean',
            'friday_photo_approved' => 'boolean',
            'notes' => 'string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $compliance = MoneyOutCompliance::findOrFail($id);

        // Track what changed
        $changes = [];
        $oldValues = [];
        $newValues = [];

        if ($request->has('payment_verified') && $request->payment_verified !== $compliance->payment_verified) {
            $oldValues['payment_verified'] = $compliance->payment_verified;
            $newValues['payment_verified'] = $request->payment_verified;
            $changes[] = 'payment_verified';
        }

        if ($request->has('otp_submitted') && $request->otp_submitted !== $compliance->otp_submitted) {
            $oldValues['otp_submitted'] = $compliance->otp_submitted;
            $newValues['otp_submitted'] = $request->otp_submitted;
            $changes[] = 'otp_submitted';
        }

        if ($request->has('friday_photo_approved') && $request->friday_photo_approved !== $compliance->friday_photo_approved) {
            $oldValues['friday_photo_approved'] = $compliance->friday_photo_approved;
            $newValues['friday_photo_approved'] = $request->friday_photo_approved;
            $changes[] = 'friday_photo_approved';
        }

        // Update the record
        $compliance->update($request->only([
            'payment_verified', 'otp_submitted', 'friday_photo_approved'
        ]));

        // Log the changes
        if (!empty($changes)) {
            $compliance->auditLogs()->create([
                'event_type' => 'update',
                'user_id' => auth()->id(),
                'old_values' => $oldValues,
                'new_values' => $newValues,
                'metadata' => [
                    'changes' => $changes,
                    'notes' => $request->notes
                ]
            ]);
        }

        // Refresh the model to get updated relationships
        $compliance->refresh();
        $compliance->load(['order', 'deliveryAgent', 'paidBy']);

        return response()->json([
            'success' => true,
            'message' => 'Compliance updated successfully',
            'data' => $compliance,
            'changes' => $changes
        ]);
    }

    /**
     * Upload proof of payment
     */
    public function uploadProof(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'proof_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120', // 5MB max
            'description' => 'string|max:500'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $compliance = MoneyOutCompliance::findOrFail($id);

        // Check if compliance can accept proof upload
        if ($compliance->compliance_status !== 'locked') {
            return response()->json([
                'success' => false,
                'message' => 'Compliance must be locked before uploading proof of payment'
            ], 422);
        }

        try {
            // Upload file using service
            $uploadResult = $this->fileUploadService->uploadFile(
                $request->file('proof_file'),
                'proofs/money-out-compliance',
                MoneyOutCompliance::class,
                $compliance->id
            );

            // Update compliance record with file path
            $compliance->update([
                'proof_of_payment_path' => $uploadResult['file_path']
            ]);

            // Create file upload record
            $fileUpload = FileUpload::create([
                'file_id' => 'VV-FILE-' . str_pad(FileUpload::count() + 1, 6, '0', STR_PAD_LEFT),
                'uploadable_type' => MoneyOutCompliance::class,
                'uploadable_id' => $compliance->id,
                'file_name' => $request->file('proof_file')->getClientOriginalName(),
                'file_path' => $uploadResult['file_path'],
                'file_url' => $uploadResult['file_url'] ?? null,
                'file_size' => $request->file('proof_file')->getSize(),
                'mime_type' => $request->file('proof_file')->getMimeType(),
                'file_extension' => $request->file('proof_file')->getClientOriginalExtension(),
                'file_type' => 'proof_of_payment',
                'uploaded_by' => auth()->id(),
                'metadata' => [
                    'description' => $request->description,
                    'compliance_id' => $compliance->id
                ]
            ]);

            // Log the upload
            $compliance->auditLogs()->create([
                'event_type' => 'file_upload',
                'user_id' => auth()->id(),
                'new_values' => [
                    'proof_of_payment_path' => $uploadResult['file_path'],
                    'file_id' => $fileUpload->file_id
                ],
                'metadata' => [
                    'file_name' => $request->file('proof_file')->getClientOriginalName(),
                    'file_size' => $request->file('proof_file')->getSize(),
                    'description' => $request->description
                ]
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Proof of payment uploaded successfully',
                'data' => [
                    'file_path' => $uploadResult['file_path'],
                    'file_url' => $uploadResult['file_url'] ?? null,
                    'file_id' => $fileUpload->file_id,
                    'compliance' => $compliance->fresh()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'File upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Mark compliance as paid
     */
    public function markPaid(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'nullable|string|max:100',
            'notes' => 'nullable|string|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $compliance = MoneyOutCompliance::findOrFail($id);

        // Validate compliance can be marked as paid
        if (!$compliance->proof_of_payment_path) {
            return response()->json([
                'success' => false,
                'message' => 'Proof of payment must be uploaded before marking as paid'
            ], 422);
        }

        if ($compliance->compliance_status !== 'locked') {
            return response()->json([
                'success' => false,
                'message' => 'Compliance must be locked before marking as paid'
            ], 422);
        }

        // Mark as paid using model method
        $success = $compliance->markAsPaid(auth()->id());

        if ($success) {
            return response()->json([
                'success' => true,
                'message' => 'Payment marked as completed successfully',
                'data' => [
                    'compliance' => $compliance->fresh(['order', 'deliveryAgent', 'paidBy']),
                    'payment_reference' => $request->payment_reference,
                    'paid_at' => $compliance->paid_at,
                    'paid_by' => $compliance->paidBy
                ]
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Failed to mark payment as completed'
            ], 422);
        }
    }

    /**
     * Auto-lock compliances that meet all criteria
     */
    public function autoLock(Request $request)
    {
        $readyCompliances = MoneyOutCompliance::ready()
            ->readyForPayment()
            ->get();

        $lockedCount = 0;
        $lockedCompliances = [];

        foreach ($readyCompliances as $compliance) {
            if ($compliance->lockCompliance()) {
                $lockedCount++;
                $lockedCompliances[] = [
                    'id' => $compliance->id,
                    'order_id' => $compliance->order_id,
                    'delivery_agent_id' => $compliance->delivery_agent_id,
                    'amount' => $compliance->amount
                ];
            }
        }

        return response()->json([
            'success' => true,
            'message' => "Auto-locked {$lockedCount} compliance records",
            'data' => [
                'locked_count' => $lockedCount,
                'locked_compliances' => $lockedCompliances,
                'total_amount_locked' => collect($lockedCompliances)->sum('amount')
            ]
        ]);
    }

    /**
     * Get compliance mismatches and issues
     */
    public function getMismatches(Request $request)
    {
        $query = MoneyOutCompliance::with(['order', 'deliveryAgent']);

        // Find various types of mismatches
        $overdue = $query->clone()->overdue()->get();
        $incompleteVerification = $query->clone()->where('compliance_status', 'ready')
            ->where('created_at', '<', now()->subHours(24))
            ->get();
        $lockedWithoutProof = $query->clone()->locked()
            ->whereNull('proof_of_payment_path')
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'overdue_compliances' => $overdue,
                'incomplete_verifications' => $incompleteVerification,
                'locked_without_proof' => $lockedWithoutProof,
                'summary' => [
                    'total_overdue' => $overdue->count(),
                    'total_incomplete' => $incompleteVerification->count(),
                    'total_locked_without_proof' => $lockedWithoutProof->count(),
                    'total_amount_at_risk' => $overdue->sum('amount') + $incompleteVerification->sum('amount')
                ]
            ]
        ]);
    }

    /**
     * Get compliance statistics and analytics
     */
    public function getStats(Request $request)
    {
        $dateFrom = $request->get('date_from', now()->subDays(30)->toDateString());
        $dateTo = $request->get('date_to', now()->toDateString());

        $stats = [
            'total_compliances' => MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])->count(),
            'total_amount' => MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])->sum('amount'),
            'completion_rate' => $this->calculateCompletionRate($dateFrom, $dateTo),
            'average_processing_time' => $this->calculateAverageProcessingTime($dateFrom, $dateTo),
            'status_breakdown' => $this->getStatusBreakdown($dateFrom, $dateTo),
            'daily_trends' => $this->getDailyTrends($dateFrom, $dateTo)
        ];

        return response()->json([
            'success' => true,
            'data' => $stats,
            'period' => [
                'from' => $dateFrom,
                'to' => $dateTo
            ]
        ]);
    }

    private function calculateCompletionRate($dateFrom, $dateTo)
    {
        $total = MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])->count();
        $completed = MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])
            ->where('compliance_status', 'paid')->count();
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    private function calculateAverageProcessingTime($dateFrom, $dateTo)
    {
        $compliances = MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])
            ->whereNotNull('paid_at')
            ->get();

        if ($compliances->isEmpty()) {
            return 0;
        }

        $totalHours = $compliances->sum(function ($compliance) {
            return $compliance->created_at->diffInHours($compliance->paid_at);
        });

        return round($totalHours / $compliances->count(), 2);
    }

    private function getStatusBreakdown($dateFrom, $dateTo)
    {
        return MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('compliance_status, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('compliance_status')
            ->get()
            ->keyBy('compliance_status');
    }

    private function getDailyTrends($dateFrom, $dateTo)
    {
        return MoneyOutCompliance::whereBetween('created_at', [$dateFrom, $dateTo])
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, SUM(amount) as total_amount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();
    }
}

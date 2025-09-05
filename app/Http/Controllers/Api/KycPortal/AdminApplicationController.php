<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AdminApplicationController extends Controller
{
    /**
     * Get all applications with filtering
     */
    public function index(Request $request)
    {
        try {
            $query = \App\Models\AgentApplication::with('guarantors');

            // Apply filters
            if ($request->status) {
                $query->where('status', $request->status);
            }

            if ($request->ai_status) {
                $query->where('ai_status', $request->ai_status);
            }

            if ($request->state) {
                $query->where('state', $request->state);
            }

            if ($request->city) {
                $query->where('city', 'like', '%' . $request->city . '%');
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', '%' . $search . '%')
                      ->orWhere('phone_number', 'like', '%' . $search . '%')
                      ->orWhere('email', 'like', '%' . $search . '%')
                      ->orWhere('application_id', 'like', '%' . $search . '%');
                });
            }

            if ($request->date_from) {
                $query->where('created_at', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('created_at', '<=', $request->date_to);
            }

            // Apply sorting
            $sortBy = $request->sort_by ?? 'created_at';
            $sortOrder = $request->sort_order ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->per_page ?? 20;
            $applications = $query->paginate($perPage);

            // Transform data
            $applications->getCollection()->transform(function($application) {
                return [
                    'id' => $application->id,
                    'application_id' => $application->application_id,
                    'full_name' => $application->full_name,
                    'phone_number' => $application->phone_number,
                    'email' => $application->email,
                    'city' => $application->city,
                    'state' => $application->state,
                    'status' => $application->status,
                    'ai_score' => $application->ai_score,
                    'ai_status' => $application->ai_status,
                    'current_step' => $application->current_step,
                    'created_at' => $application->created_at,
                    'submitted_at' => $application->submitted_at,
                    'guarantors_count' => $application->guarantors->count(),
                    'verified_guarantors_count' => $application->guarantors->where('verification_status', 'verified')->count(),
                    'has_national_id' => !empty($application->national_id_path),
                    'has_selfie' => !empty($application->selfie_path),
                    'has_utility_bill' => !empty($application->utility_bill_path),
                    'has_vehicle_registration' => !empty($application->vehicle_registration_path)
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $applications
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Search applications
     */
    public function search(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'query' => 'required|string|min:2',
                'limit' => 'nullable|integer|min:1|max:50'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $query = $request->query;
            $limit = $request->limit ?? 10;

            $applications = \App\Models\AgentApplication::with('guarantors')
                ->where(function($q) use ($query) {
                    $q->where('full_name', 'like', '%' . $query . '%')
                      ->orWhere('phone_number', 'like', '%' . $query . '%')
                      ->orWhere('email', 'like', '%' . $query . '%')
                      ->orWhere('application_id', 'like', '%' . $query . '%')
                      ->orWhere('city', 'like', '%' . $query . '%')
                      ->orWhere('state', 'like', '%' . $query . '%');
                })
                ->orderBy('created_at', 'desc')
                ->limit($limit)
                ->get()
                ->map(function($application) {
                    return [
                        'id' => $application->id,
                        'application_id' => $application->application_id,
                        'full_name' => $application->full_name,
                        'phone_number' => $application->phone_number,
                        'email' => $application->email,
                        'city' => $application->city,
                        'state' => $application->state,
                        'status' => $application->status,
                        'ai_score' => $application->ai_score,
                        'ai_status' => $application->ai_status,
                        'created_at' => $application->created_at,
                        'guarantors_count' => $application->guarantors->count(),
                        'verified_guarantors_count' => $application->guarantors->where('verification_status', 'verified')->count()
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'applications' => $applications,
                    'total_results' => $applications->count(),
                    'search_query' => $query
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to search applications',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get application details
     */
    public function show($agentId)
    {
        try {
            $application = \App\Models\AgentApplication::with('guarantors')
                ->where('application_id', $agentId)
                ->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $application->id,
                    'application_id' => $application->application_id,
                    'full_name' => $application->full_name,
                    'phone_number' => $application->phone_number,
                    'email' => $application->email,
                    'date_of_birth' => $application->date_of_birth,
                    'gender' => $application->gender,
                    'address' => $application->address,
                    'city' => $application->city,
                    'state' => $application->state,
                    'emergency_contact_name' => $application->emergency_contact_name,
                    'emergency_contact_phone' => $application->emergency_contact_phone,
                    'emergency_contact_relationship' => $application->emergency_contact_relationship,
                    'has_vehicle' => $application->has_vehicle,
                    'vehicle_type' => $application->vehicle_type,
                    'vehicle_registration' => $application->vehicle_registration,
                    'has_smartphone' => $application->has_smartphone,
                    'smartphone_type' => $application->smartphone_type,
                    'has_bank_account' => $application->has_bank_account,
                    'bank_name' => $application->bank_name,
                    'account_number' => $application->account_number,
                    'availability_hours' => json_decode($application->availability_hours, true),
                    'preferred_delivery_areas' => json_decode($application->preferred_delivery_areas, true),
                    'experience_level' => $application->experience_level,
                    'previous_delivery_experience' => $application->previous_delivery_experience,
                    'status' => $application->status,
                    'current_step' => $application->current_step,
                    'ai_score' => $application->ai_score,
                    'ai_status' => $application->ai_status,
                    'created_at' => $application->created_at,
                    'submitted_at' => $application->submitted_at,
                    'ai_validated_at' => $application->ai_validated_at,
                    'guarantors_verified_at' => $application->guarantors_verified_at,
                    'documents' => [
                        'national_id_path' => $application->national_id_path,
                        'selfie_path' => $application->selfie_path,
                        'utility_bill_path' => $application->utility_bill_path,
                        'vehicle_registration_path' => $application->vehicle_registration_path
                    ],
                    'guarantors' => $application->guarantors->map(function($guarantor) {
                        return [
                            'id' => $guarantor->id,
                            'guarantor_type' => $guarantor->guarantor_type,
                            'full_name' => $guarantor->full_name,
                            'phone_number' => $guarantor->phone_number,
                            'email' => $guarantor->email,
                            'relationship' => $guarantor->relationship,
                            'address' => $guarantor->address,
                            'city' => $guarantor->city,
                            'state' => $guarantor->state,
                            'occupation' => $guarantor->occupation,
                            'employer' => $guarantor->employer,
                            'years_known' => $guarantor->years_known,
                            'verification_status' => $guarantor->verification_status,
                            'verification_sent_at' => $guarantor->verification_sent_at,
                            'verified_at' => $guarantor->verified_at,
                            'is_expired' => $guarantor->verification_sent_at->diffInHours(now()) > 24
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get application details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Approve application
     */
    public function approve(Request $request, $agentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'notes' => 'nullable|string|max:1000',
                'auto_approve' => 'boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Check if application can be approved
            if (!in_array($application->status, ['ai_validated', 'ai_review_required'])) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application cannot be approved in current status'
                ], 400);
            }

            // Update application status
            $application->update([
                'status' => 'admin_approved',
                'admin_approved_at' => now(),
                'admin_notes' => $request->notes,
                'auto_approved' => $request->auto_approve ?? false
            ]);

            // Log approval activity
            \Log::info('Application approved by admin', [
                'application_id' => $application->application_id,
                'admin_id' => auth()->id(),
                'notes' => $request->notes,
                'auto_approved' => $request->auto_approve ?? false
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application approved successfully',
                'data' => [
                    'application_id' => $application->application_id,
                    'status' => $application->status,
                    'approved_at' => $application->admin_approved_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to approve application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject application
     */
    public function reject(Request $request, $agentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:1000',
                'rejection_type' => 'required|in:document_quality,missing_information,fraud_suspicion,other'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Update application status
            $application->update([
                'status' => 'rejected',
                'rejected_at' => now(),
                'rejection_reason' => $request->reason,
                'rejection_type' => $request->rejection_type
            ]);

            // Log rejection activity
            \Log::info('Application rejected by admin', [
                'application_id' => $application->application_id,
                'admin_id' => auth()->id(),
                'reason' => $request->reason,
                'rejection_type' => $request->rejection_type
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Application rejected successfully',
                'data' => [
                    'application_id' => $application->application_id,
                    'status' => $application->status,
                    'rejected_at' => $application->rejected_at,
                    'rejection_reason' => $application->rejection_reason
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Request guarantors for application
     */
    public function requestGuarantors(Request $request, $agentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'required_count' => 'required|integer|min:1|max:3',
                'message' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Update application status
            $application->update([
                'status' => 'guarantors_requested',
                'required_guarantors_count' => $request->required_count,
                'guarantor_request_message' => $request->message,
                'guarantors_requested_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Guarantors requested successfully',
                'data' => [
                    'application_id' => $application->application_id,
                    'required_guarantors_count' => $request->required_count,
                    'requested_at' => $application->guarantors_requested_at
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to request guarantors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get application documents
     */
    public function getDocuments($agentId)
    {
        try {
            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $documents = [];

            if ($application->national_id_path) {
                $documents[] = [
                    'type' => 'national_id',
                    'name' => 'National ID',
                    'path' => $application->national_id_path,
                    'url' => asset('storage/' . $application->national_id_path),
                    'uploaded_at' => $application->submitted_at
                ];
            }

            if ($application->selfie_path) {
                $documents[] = [
                    'type' => 'selfie',
                    'name' => 'Selfie',
                    'path' => $application->selfie_path,
                    'url' => asset('storage/' . $application->selfie_path),
                    'uploaded_at' => $application->submitted_at
                ];
            }

            if ($application->utility_bill_path) {
                $documents[] = [
                    'type' => 'utility_bill',
                    'name' => 'Utility Bill',
                    'path' => $application->utility_bill_path,
                    'url' => asset('storage/' . $application->utility_bill_path),
                    'uploaded_at' => $application->submitted_at
                ];
            }

            if ($application->vehicle_registration_path) {
                $documents[] = [
                    'type' => 'vehicle_registration',
                    'name' => 'Vehicle Registration',
                    'path' => $application->vehicle_registration_path,
                    'url' => asset('storage/' . $application->vehicle_registration_path),
                    'uploaded_at' => $application->submitted_at
                ];
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'application_id' => $application->application_id,
                    'documents' => $documents,
                    'total_documents' => count($documents),
                    'ai_validation' => [
                        'ai_score' => $application->ai_score,
                        'ai_status' => $application->ai_status,
                        'validated_at' => $application->ai_validated_at
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get documents',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify document
     */
    public function verifyDocument(Request $request, $agentId, $documentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'verification_status' => 'required|in:verified,rejected',
                'notes' => 'nullable|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // For now, we'll just log the document verification
            // In a real implementation, you'd have a separate documents table
            \Log::info('Document verification by admin', [
                'application_id' => $application->application_id,
                'document_id' => $documentId,
                'verification_status' => $request->verification_status,
                'notes' => $request->notes,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document verification status updated',
                'data' => [
                    'application_id' => $application->application_id,
                    'document_id' => $documentId,
                    'verification_status' => $request->verification_status,
                    'verified_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Reject document
     */
    public function rejectDocument(Request $request, $agentId, $documentId)
    {
        try {
            $validator = Validator::make($request->all(), [
                'reason' => 'required|string|max:500'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation failed',
                    'errors' => $validator->errors()
                ], 422);
            }

            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();

            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Log document rejection
            \Log::info('Document rejected by admin', [
                'application_id' => $application->application_id,
                'document_id' => $documentId,
                'reason' => $request->reason,
                'admin_id' => auth()->id()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Document rejected successfully',
                'data' => [
                    'application_id' => $application->application_id,
                    'document_id' => $documentId,
                    'rejection_reason' => $request->reason,
                    'rejected_at' => now()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to reject document',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

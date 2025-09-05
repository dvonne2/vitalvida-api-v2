<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use App\Services\AiValidationService;
use App\Services\NotificationService;

class AgentApplicationController extends Controller
{
    protected $aiValidationService;
    protected $notificationService;

    public function __construct(
        AiValidationService $aiValidationService,
        NotificationService $notificationService
    ) {
        $this->aiValidationService = $aiValidationService;
        $this->notificationService = $notificationService;
    }

    /**
     * Start a new agent application
     */
    public function startApplication(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone_number' => 'required|string|unique:delivery_agents,phone_number',
            'email' => 'nullable|email|unique:delivery_agents,email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $applicationId = 'VV-' . date('Y') . '-' . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
            
            $agent = \App\Models\DeliveryAgent::create([
                'agent_id' => $applicationId,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'status' => 'started',
                'application_step' => 1,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);

            // Log activity
            \App\Models\SystemActivity::logActivity(
                'APPLICATION_STARTED',
                $agent->id,
                'SUCCESS',
                "New application started: {$applicationId}",
                ['phone_number' => $request->phone_number]
            );

            return response()->json([
                'success' => true,
                'message' => 'Application started successfully',
                'data' => [
                    'application_id' => $agent->agent_id,
                    'current_step' => $agent->application_step,
                    'next_step' => 'personal_info'
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start application',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save personal information (Step 1)
     */
    public function savePersonalInfo(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:delivery_agents,agent_id',
            'full_name' => 'required|string|max:255',
            'date_of_birth' => 'required|date|before:-18 years',
            'gender' => 'required|in:male,female,other',
            'address' => 'required|string',
            'city' => 'required|string|max:100',
            'state' => 'required|string|max:100',
            'whatsapp_number' => 'required|string|max:20',
            'emergency_contact' => 'required|string|max:20',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_relationship' => 'required|string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = \App\Models\DeliveryAgent::where('agent_id', $request->application_id)->first();
            
            $agent->update([
                'full_name' => $request->full_name,
                'date_of_birth' => $request->date_of_birth,
                'gender' => $request->gender,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'whatsapp_number' => $request->whatsapp_number,
                'emergency_contact' => $request->emergency_contact,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_relationship' => $request->emergency_contact_relationship,
                'application_step' => 2
            ]);

            // Log activity
            \App\Models\SystemActivity::logActivity(
                'PERSONAL_INFO_SAVED',
                $agent->id,
                'SUCCESS',
                "Personal information saved for {$agent->agent_id}",
                ['full_name' => $request->full_name, 'city' => $request->city]
            );

            return response()->json([
                'success' => true,
                'message' => 'Personal information saved successfully',
                'next_step' => 2,
                'next_step_url' => '/api/kyc-portal/agent-application/step-2/requirements'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save personal information',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save requirements (Step 2)
     */
    public function saveRequirements(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:delivery_agents,agent_id',
            'has_smartphone' => 'required|boolean',
            'has_transportation' => 'required|boolean',
            'can_store_products' => 'required|boolean',
            'comfortable_with_portal' => 'required|boolean'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = \App\Models\DeliveryAgent::where('agent_id', $request->application_id)->first();
            
            $requirements = \App\Models\AgentRequirement::create([
                'agent_id' => $agent->id,
                'has_smartphone' => $request->has_smartphone,
                'has_transportation' => $request->has_transportation,
                'can_store_products' => $request->can_store_products,
                'comfortable_with_portal' => $request->comfortable_with_portal
            ]);

            // Run AI validation on requirements
            $requirementScore = $requirements->getRequirementScore();
            
            if (!$requirements->meetsMinimumRequirements()) {
                $agent->update(['status' => 'rejected']);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Application does not meet minimum requirements',
                    'requirement_score' => $requirementScore,
                    'missing_requirements' => $this->getMissingRequirements($requirements)
                ], 400);
            }

            $agent->update(['application_step' => 3]);

            // Run AI validation
            $this->aiValidationService->validateRequirements($agent, $requirements);

            // Log activity
            \App\Models\SystemActivity::logActivity(
                'REQUIREMENTS_SAVED',
                $agent->id,
                'SUCCESS',
                "Requirements saved for {$agent->agent_id} with score {$requirementScore}%",
                ['requirement_score' => $requirementScore]
            );

            return response()->json([
                'success' => true,
                'message' => 'Requirements saved successfully',
                'requirement_score' => $requirementScore,
                'next_step' => 3,
                'next_step_url' => '/api/kyc-portal/agent-application/step-3/documents'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save requirements',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload documents (Step 3)
     */
    public function uploadDocuments(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:delivery_agents,agent_id',
            'document_type' => 'required|in:passport_photo,government_id,utility_bill',
            'document_file' => 'required|file|mimes:jpg,jpeg,png,pdf|max:5120' // 5MB max
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = \App\Models\DeliveryAgent::where('agent_id', $request->application_id)->first();
            $file = $request->file('document_file');
            
            // Store file
            $fileName = time() . '_' . $agent->agent_id . '_' . $request->document_type . '.' . $file->getClientOriginalExtension();
            $filePath = $file->storeAs('kyc-documents/' . $agent->agent_id, $fileName, 'public');

            // Delete existing document of same type
            \App\Models\AgentDocument::where('agent_id', $agent->id)
                        ->where('document_type', $request->document_type)
                        ->delete();

            // Create document record
            $document = \App\Models\AgentDocument::create([
                'agent_id' => $agent->id,
                'document_type' => $request->document_type,
                'file_path' => $filePath,
                'file_name' => $fileName,
                'file_size' => $file->getSize(),
                'mime_type' => $file->getMimeType(),
                'verification_status' => 'pending'
            ]);

            // Run AI validation on document
            $aiScore = $document->runAiValidation();

            // Check if all documents are uploaded
            $allDocsUploaded = $agent->isDocumentationComplete();
            
            if ($allDocsUploaded) {
                $agent->update(['application_step' => 4]);
                
                // Run overall AI validation
                $overallScore = $this->aiValidationService->runOverallValidation($agent);
                
                // Auto-approve if score is high enough
                if ($agent->canAutoApprove()) {
                    $this->processAutoApproval($agent);
                }
            }

            // Log activity
            \App\Models\SystemActivity::logActivity(
                'DOCUMENT_UPLOADED',
                $agent->id,
                'SUCCESS',
                "Document uploaded for {$agent->agent_id}: {$request->document_type}",
                ['document_type' => $request->document_type, 'ai_score' => $aiScore]
            );

            return response()->json([
                'success' => true,
                'message' => 'Document uploaded successfully',
                'document_id' => $document->id,
                'ai_score' => $aiScore,
                'verification_status' => $document->verification_status,
                'all_documents_complete' => $allDocsUploaded,
                'next_step' => $allDocsUploaded ? 4 : 3,
                'application_status' => $agent->fresh()->status
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload document',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Save delivery areas (Step 3)
     */
    public function saveDeliveryAreas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|exists:delivery_agents,agent_id',
            'delivery_areas' => 'required|array|min:1',
            'delivery_areas.*' => 'string|max:100'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $agent = \App\Models\DeliveryAgent::where('agent_id', $request->application_id)->first();
            $requirements = $agent->requirements;
            
            if (!$requirements) {
                return response()->json([
                    'success' => false,
                    'message' => 'Requirements must be completed first'
                ], 400);
            }

            $requirements->update([
                'delivery_areas' => $request->delivery_areas
            ]);

            $agent->update(['application_step' => 4]);

            // Log activity
            \App\Models\SystemActivity::logActivity(
                'DELIVERY_AREAS_SAVED',
                $agent->id,
                'SUCCESS',
                "Delivery areas saved for {$agent->agent_id}",
                ['delivery_areas' => $request->delivery_areas]
            );

            return response()->json([
                'success' => true,
                'message' => 'Delivery areas saved successfully',
                'delivery_areas' => $request->delivery_areas,
                'application_status' => $agent->status,
                'next_steps' => $this->getNextStepsForAgent($agent)
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to save delivery areas',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get application status
     */
    public function getApplicationStatus(Request $request, $applicationId)
    {
        try {
            $agent = \App\Models\DeliveryAgent::where('agent_id', $applicationId)
                              ->with(['requirements', 'documents', 'guarantors'])
                              ->first();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'application' => [
                    'agent_id' => $agent->agent_id,
                    'full_name' => $agent->full_name,
                    'phone_number' => $agent->phone_number,
                    'city' => $agent->city,
                    'state' => $agent->state,
                    'status' => $agent->status,
                    'application_step' => $agent->application_step,
                    'ai_score' => $agent->ai_score,
                    'created_at' => $agent->created_at,
                    'requirements_complete' => $agent->requirements ? true : false,
                    'documents_complete' => $agent->isDocumentationComplete(),
                    'guarantors_verified' => $agent->guarantors()->where('verification_status', 'verified')->count(),
                    'documents' => $agent->documents->map(function($doc) {
                        return [
                            'type' => $doc->document_type,
                            'status' => $doc->verification_status,
                            'ai_score' => $doc->ai_verification_score,
                            'uploaded_at' => $doc->uploaded_at
                        ];
                    })
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get application status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get next steps
     */
    public function getNextSteps(Request $request, $applicationId)
    {
        try {
            $agent = \App\Models\DeliveryAgent::where('agent_id', $applicationId)->first();

            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'next_steps' => $this->getNextStepsForAgent($agent),
                'what_happens_next' => [
                    'guarantor_verification' => 'Your guarantors will receive verification emails',
                    'automated_validation' => 'Automated verification will validate all information',
                    'whatsapp_updates' => 'You\'ll get updates via WhatsApp and email',
                    'processing_time' => 'Processing typically takes 24-48 hours'
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get next steps',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Helper methods
     */
    private function getMissingRequirements($requirements)
    {
        $missing = [];
        
        if (!$requirements->has_smartphone) $missing[] = 'Smartphone required for receiving delivery requests';
        if (!$requirements->has_transportation) $missing[] = 'Reliable transportation needed';
        if (!$requirements->can_store_products) $missing[] = 'Secure storage space required';
        if (!$requirements->comfortable_with_portal) $missing[] = 'Must be comfortable using delivery portal';
        
        return $missing;
    }

    private function processAutoApproval($agent)
    {
        $agent->update(['status' => 'approved']);
        
        // Log activity
        \App\Models\SystemActivity::logActivity(
            'AUTO_APPROVED',
            $agent->id,
            'SUCCESS',
            "Agent {$agent->agent_id} auto-approved with AI score {$agent->ai_score}%",
            ['ai_score' => $agent->ai_score, 'auto_approval' => true]
        );

        // Send notifications
        $this->notificationService->sendApprovalNotification($agent);
    }

    private function getNextStepsForAgent($agent)
    {
        return match($agent->status) {
            'approved' => [
                'status' => 'Congratulations! Your application has been approved.',
                'action' => 'You will receive portal access details via WhatsApp and email.',
                'timeline' => 'Portal access within 2 hours'
            ],
            'waiting_guarantors' => [
                'status' => 'Waiting for guarantor verification',
                'action' => 'Please contact your guarantors to complete their verification.',
                'timeline' => 'Usually completed within 24 hours'
            ],
            'rejected' => [
                'status' => 'Application rejected',
                'action' => 'Please review the requirements and reapply after addressing issues.',
                'timeline' => 'You can reapply immediately after improvements'
            ],
            default => [
                'status' => 'Application in review',
                'action' => 'Our team is reviewing your application.',
                'timeline' => 'Review typically takes 24-48 hours'
            ]
        };
    }
}

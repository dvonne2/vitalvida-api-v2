<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class GuarantorController extends Controller
{
    /**
     * Submit guarantor verification
     */
    public function submitVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'application_id' => 'required|string|exists:agent_applications,application_id',
            'guarantor_type' => 'required|in:family,friend,employer,landlord',
            'full_name' => 'required|string|max:255',
            'phone_number' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'relationship' => 'required|string|max:100',
            'address' => 'required|string',
            'city' => 'required|string',
            'state' => 'required|string',
            'occupation' => 'required|string|max:100',
            'employer' => 'nullable|string|max:255',
            'years_known' => 'required|integer|min:1|max:50',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $application = \App\Models\AgentApplication::where('application_id', $request->application_id)->first();
            
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            // Generate verification code
            $verificationCode = strtoupper(Str::random(8));

            // Create guarantor record
            $guarantor = \App\Models\AgentGuarantor::create([
                'application_id' => $application->id,
                'guarantor_type' => $request->guarantor_type,
                'full_name' => $request->full_name,
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'relationship' => $request->relationship,
                'address' => $request->address,
                'city' => $request->city,
                'state' => $request->state,
                'occupation' => $request->occupation,
                'employer' => $request->employer,
                'years_known' => $request->years_known,
                'verification_code' => $verificationCode,
                'verification_status' => 'pending',
                'verification_sent_at' => now(),
            ]);

            // Send verification SMS/Email
            $this->sendVerificationCode($guarantor);

            return response()->json([
                'success' => true,
                'message' => 'Guarantor verification submitted successfully',
                'data' => [
                    'guarantor_id' => $guarantor->id,
                    'verification_code' => $verificationCode, // For testing purposes
                    'verification_sent_to' => $request->phone_number
                ]
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to submit guarantor verification',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Verify guarantor code
     */
    public function verifyCode($verificationCode)
    {
        try {
            $guarantor = \App\Models\AgentGuarantor::where('verification_code', $verificationCode)
                                                   ->where('verification_status', 'pending')
                                                   ->first();

            if (!$guarantor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid or expired verification code'
                ], 404);
            }

            // Check if code is expired (24 hours)
            if ($guarantor->verification_sent_at->diffInHours(now()) > 24) {
                $guarantor->update(['verification_status' => 'expired']);
                
                return response()->json([
                    'success' => false,
                    'message' => 'Verification code has expired'
                ], 400);
            }

            // Update guarantor status
            $guarantor->update([
                'verification_status' => 'verified',
                'verified_at' => now()
            ]);

            // Check if all guarantors are verified
            $this->checkApplicationGuarantorStatus($guarantor->application_id);

            return response()->json([
                'success' => true,
                'message' => 'Guarantor verification successful',
                'data' => [
                    'guarantor_name' => $guarantor->full_name,
                    'application_id' => $guarantor->application->application_id ?? null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to verify code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resend verification code
     */
    public function resendVerification(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guarantor_id' => 'required|exists:agent_guarantors,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $guarantor = \App\Models\AgentGuarantor::find($request->guarantor_id);
            
            if (!$guarantor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guarantor not found'
                ], 404);
            }

            // Generate new verification code
            $newVerificationCode = strtoupper(Str::random(8));

            // Update guarantor with new code
            $guarantor->update([
                'verification_code' => $newVerificationCode,
                'verification_status' => 'pending',
                'verification_sent_at' => now(),
            ]);

            // Send new verification code
            $this->sendVerificationCode($guarantor);

            return response()->json([
                'success' => true,
                'message' => 'Verification code resent successfully',
                'data' => [
                    'verification_code' => $newVerificationCode, // For testing purposes
                    'verification_sent_to' => $guarantor->phone_number
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to resend verification code',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get guarantor status
     */
    public function getGuarantorStatus($guarantorId)
    {
        try {
            $guarantor = \App\Models\AgentGuarantor::with('application')->find($guarantorId);
            
            if (!$guarantor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guarantor not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'guarantor_id' => $guarantor->id,
                    'full_name' => $guarantor->full_name,
                    'phone_number' => $guarantor->phone_number,
                    'verification_status' => $guarantor->verification_status,
                    'verification_sent_at' => $guarantor->verification_sent_at,
                    'verified_at' => $guarantor->verified_at,
                    'application_id' => $guarantor->application->application_id ?? null,
                    'is_expired' => $guarantor->verification_sent_at->diffInHours(now()) > 24
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get guarantor status',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update guarantor information
     */
    public function updateGuarantor(Request $request, $guarantorId)
    {
        $validator = Validator::make($request->all(), [
            'full_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'address' => 'sometimes|string',
            'city' => 'sometimes|string',
            'state' => 'sometimes|string',
            'occupation' => 'sometimes|string|max:100',
            'employer' => 'sometimes|nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $guarantor = \App\Models\AgentGuarantor::find($guarantorId);
            
            if (!$guarantor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guarantor not found'
                ], 404);
            }

            // Only allow updates if not verified
            if ($guarantor->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot update verified guarantor'
                ], 400);
            }

            $guarantor->update($request->only([
                'full_name', 'phone_number', 'email', 'address', 
                'city', 'state', 'occupation', 'employer'
            ]));

            return response()->json([
                'success' => true,
                'message' => 'Guarantor updated successfully',
                'data' => [
                    'guarantor_id' => $guarantor->id,
                    'full_name' => $guarantor->full_name,
                    'verification_status' => $guarantor->verification_status
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update guarantor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Remove guarantor
     */
    public function removeGuarantor($guarantorId)
    {
        try {
            $guarantor = \App\Models\AgentGuarantor::find($guarantorId);
            
            if (!$guarantor) {
                return response()->json([
                    'success' => false,
                    'message' => 'Guarantor not found'
                ], 404);
            }

            // Only allow removal if not verified
            if ($guarantor->verification_status === 'verified') {
                return response()->json([
                    'success' => false,
                    'message' => 'Cannot remove verified guarantor'
                ], 400);
            }

            $applicationId = $guarantor->application_id;
            $guarantor->delete();

            return response()->json([
                'success' => true,
                'message' => 'Guarantor removed successfully',
                'data' => [
                    'application_id' => $applicationId
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to remove guarantor',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get all guarantors for an agent
     */
    public function getAgentGuarantors($agentId)
    {
        try {
            $application = \App\Models\AgentApplication::where('application_id', $agentId)->first();
            
            if (!$application) {
                return response()->json([
                    'success' => false,
                    'message' => 'Application not found'
                ], 404);
            }

            $guarantors = \App\Models\AgentGuarantor::where('application_id', $application->id)
                                                   ->orderBy('created_at', 'desc')
                                                   ->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'application_id' => $application->application_id,
                    'guarantors' => $guarantors->map(function($guarantor) {
                        return [
                            'id' => $guarantor->id,
                            'guarantor_type' => $guarantor->guarantor_type,
                            'full_name' => $guarantor->full_name,
                            'phone_number' => $guarantor->phone_number,
                            'relationship' => $guarantor->relationship,
                            'verification_status' => $guarantor->verification_status,
                            'verification_sent_at' => $guarantor->verification_sent_at,
                            'verified_at' => $guarantor->verified_at,
                            'is_expired' => $guarantor->verification_sent_at->diffInHours(now()) > 24
                        ];
                    }),
                    'summary' => [
                        'total_guarantors' => $guarantors->count(),
                        'verified_guarantors' => $guarantors->where('verification_status', 'verified')->count(),
                        'pending_guarantors' => $guarantors->where('verification_status', 'pending')->count(),
                        'expired_guarantors' => $guarantors->where('verification_status', 'expired')->count()
                    ]
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get agent guarantors',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Send verification code via SMS/Email
     */
    private function sendVerificationCode($guarantor)
    {
        try {
            // Simulate sending SMS/Email
            $message = "Hello {$guarantor->full_name}, your verification code for Vitalvida agent application is: {$guarantor->verification_code}. Valid for 24 hours.";
            
            // In production, integrate with SMS/Email service
            \Log::info('Verification code sent', [
                'guarantor_id' => $guarantor->id,
                'phone_number' => $guarantor->phone_number,
                'verification_code' => $guarantor->verification_code,
                'message' => $message
            ]);

        } catch (\Exception $e) {
            \Log::error('Failed to send verification code', [
                'guarantor_id' => $guarantor->id,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Check if all guarantors for an application are verified
     */
    private function checkApplicationGuarantorStatus($applicationId)
    {
        try {
            $guarantors = \App\Models\AgentGuarantor::where('application_id', $applicationId)->get();
            $verifiedCount = $guarantors->where('verification_status', 'verified')->count();
            $totalCount = $guarantors->count();

            if ($totalCount > 0 && $verifiedCount === $totalCount) {
                // All guarantors verified, update application status
                $application = \App\Models\AgentApplication::find($applicationId);
                if ($application) {
                    $application->update([
                        'status' => 'guarantors_verified',
                        'guarantors_verified_at' => now()
                    ]);

                    \Log::info('All guarantors verified for application', [
                        'application_id' => $application->application_id,
                        'guarantors_count' => $totalCount
                    ]);
                }
            }

        } catch (\Exception $e) {
            \Log::error('Failed to check guarantor status', [
                'application_id' => $applicationId,
                'error' => $e->getMessage()
            ]);
        }
    }
}

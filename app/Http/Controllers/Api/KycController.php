<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\KycLog;
use App\Models\OtpLog;
use App\Models\ActionLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class KycController extends Controller
{
    /**
     * Submit KYC application (public route)
     */
    public function submit(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'phone' => 'required|string|regex:/^[0-9]{11}$/',
            'email' => 'required|email',
            'national_id_number' => 'required|string',
            'national_id_expiry' => 'required|date|after:today',
            'address' => 'required|string',
            'date_of_birth' => 'required|date|before:today',
            'gender' => 'required|in:male,female,other',
            'national_id_front' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'national_id_back' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'selfie' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            'utility_bill' => 'nullable|image|mimes:jpeg,png,jpg,pdf|max:2048',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user already exists
        $user = User::where('phone', $request->phone)->orWhere('email', $request->email)->first();

        if ($user && $user->kyc_status === 'approved') {
            return response()->json([
                'success' => false,
                'message' => 'KYC already approved for this phone/email',
                'error_code' => 'KYC_ALREADY_APPROVED'
            ], 400);
        }

        // Upload documents
        $documents = [];
        
        if ($request->hasFile('national_id_front')) {
            $documents['national_id_front'] = $request->file('national_id_front')->store('kyc/national_id', 'public');
        }
        
        if ($request->hasFile('national_id_back')) {
            $documents['national_id_back'] = $request->file('national_id_back')->store('kyc/national_id', 'public');
        }
        
        if ($request->hasFile('selfie')) {
            $documents['selfie'] = $request->file('selfie')->store('kyc/selfie', 'public');
        }
        
        if ($request->hasFile('utility_bill')) {
            $documents['utility_bill'] = $request->file('utility_bill')->store('kyc/utility_bill', 'public');
        }

        // Create or update user
        if (!$user) {
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => bcrypt(Str::random(12)), // Temporary password
                'role' => 'DA', // Default role for KYC applicants
                'kyc_status' => 'pending',
                'kyc_data' => [
                    'national_id_number' => $request->national_id_number,
                    'national_id_expiry' => $request->national_id_expiry,
                    'address' => $request->address,
                    'date_of_birth' => $request->date_of_birth,
                    'gender' => $request->gender,
                    'documents' => $documents,
                    'submitted_at' => now(),
                ]
            ]);
        } else {
            $user->update([
                'name' => $request->name,
                'email' => $request->email,
                'kyc_status' => 'pending',
                'kyc_data' => [
                    'national_id_number' => $request->national_id_number,
                    'national_id_expiry' => $request->national_id_expiry,
                    'address' => $request->address,
                    'date_of_birth' => $request->date_of_birth,
                    'gender' => $request->gender,
                    'documents' => $documents,
                    'submitted_at' => now(),
                ]
            ]);
        }

        // Create KYC logs for each document
        foreach ($documents as $type => $path) {
            KycLog::create([
                'user_id' => $user->id,
                'document_type' => $type,
                'status' => 'pending',
                'document_data' => [
                    'file_path' => $path,
                    'file_size' => Storage::disk('public')->size($path),
                    'mime_type' => Storage::disk('public')->mimeType($path),
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        // Log the action
        ActionLog::create([
            'user_id' => $user->id,
            'action' => 'kyc.submit',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'document_types' => array_keys($documents),
                'kyc_status' => 'pending'
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'KYC application submitted successfully. Please verify your phone number.',
            'data' => [
                'user_id' => $user->id,
                'kyc_status' => $user->kyc_status,
                'phone' => $user->phone,
                'next_step' => 'phone_verification'
            ]
        ], 201);
    }

    /**
     * Send OTP for KYC verification (public route)
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{11}$/',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = User::where('phone', $request->phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No KYC application found for this phone number',
                'error_code' => 'KYC_NOT_FOUND'
            ], 404);
        }

        // Check if OTP was recently sent
        $recentOtp = OtpLog::where('phone_number', $request->phone)
            ->where('type', 'kyc')
            ->where('created_at', '>', now()->subMinutes(2))
            ->first();

        if ($recentOtp) {
            return response()->json([
                'success' => false,
                'message' => 'Please wait 2 minutes before requesting another OTP',
                'error_code' => 'OTP_RATE_LIMIT'
            ], 429);
        }

        // Generate OTP
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);

        // Store OTP
        OtpLog::create([
            'otp_code' => $otp,
            'phone_number' => $request->phone,
            'type' => 'kyc',
            'status' => 'sent',
            'expires_at' => now()->addMinutes(10),
            'user_id' => $user->id,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // TODO: Integrate with SMS service to send OTP
        // For now, we'll return the OTP in response (remove in production)
        
        return response()->json([
            'success' => true,
            'message' => 'OTP sent successfully',
            'data' => [
                'phone' => $request->phone,
                'expires_in' => 600, // 10 minutes
                'otp' => $otp // Remove this in production
            ]
        ]);
    }

    /**
     * Verify OTP for KYC (public route)
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'phone' => 'required|string|regex:/^[0-9]{11}$/',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $otpLog = OtpLog::where('phone_number', $request->phone)
            ->where('otp_code', $request->otp)
            ->where('type', 'kyc')
            ->where('status', 'sent')
            ->where('expires_at', '>', now())
            ->first();

        if (!$otpLog) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired OTP',
                'error_code' => 'INVALID_OTP'
            ], 400);
        }

        // Mark OTP as verified
        $otpLog->update([
            'status' => 'verified',
            'verified_at' => now(),
        ]);

        // Update user phone verification
        $user = User::find($otpLog->user_id);
        $user->update([
            'phone_verified_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Phone number verified successfully. Your KYC application is under review.',
            'data' => [
                'user_id' => $user->id,
                'kyc_status' => $user->kyc_status,
                'phone_verified' => true,
                'next_step' => 'wait_for_approval'
            ]
        ]);
    }

    /**
     * Check KYC status (public route)
     */
    public function checkStatus(Request $request, $phone)
    {
        $user = User::where('phone', $phone)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'No KYC application found for this phone number',
                'error_code' => 'KYC_NOT_FOUND'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'kyc_status' => $user->kyc_status,
                'phone_verified' => !is_null($user->phone_verified_at),
                'submitted_at' => $user->kyc_data['submitted_at'] ?? null,
                'approved_at' => $user->kyc_data['approved_at'] ?? null,
                'rejection_reason' => $user->kyc_data['rejection_reason'] ?? null,
            ]
        ]);
    }

    /**
     * Get authenticated user's KYC status
     */
    public function myStatus(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'success' => true,
            'data' => [
                'user_id' => $user->id,
                'name' => $user->name,
                'phone' => $user->phone,
                'kyc_status' => $user->kyc_status,
                'phone_verified' => !is_null($user->phone_verified_at),
                'submitted_at' => $user->kyc_data['submitted_at'] ?? null,
                'approved_at' => $user->kyc_data['approved_at'] ?? null,
                'rejection_reason' => $user->kyc_data['rejection_reason'] ?? null,
            ]
        ]);
    }

    /**
     * Get authenticated user's KYC documents
     */
    public function myDocuments(Request $request)
    {
        $user = $request->user();

        $kycLogs = KycLog::where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(function ($log) {
                return [
                    'id' => $log->id,
                    'document_type' => $log->document_type,
                    'status' => $log->status,
                    'submitted_at' => $log->created_at,
                    'verified_at' => $log->verified_at,
                    'rejection_reason' => $log->rejection_reason,
                ];
            });

        return response()->json([
            'success' => true,
            'data' => [
                'documents' => $kycLogs,
                'kyc_status' => $user->kyc_status,
            ]
        ]);
    }

    /**
     * Upload additional KYC document
     */
    public function uploadDocument(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'document_type' => 'required|in:national_id,passport,drivers_license,utility_bill,bank_statement,selfie',
            'document' => 'required|file|mimes:jpeg,png,jpg,pdf|max:2048',
            'document_number' => 'nullable|string',
            'expiry_date' => 'nullable|date|after:today',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $user = $request->user();

        // Upload document
        $filePath = $request->file('document')->store('kyc/' . $request->document_type, 'public');

        // Create KYC log
        $kycLog = KycLog::create([
            'user_id' => $user->id,
            'document_type' => $request->document_type,
            'status' => 'pending',
            'document_number' => $request->document_number,
            'expiry_date' => $request->expiry_date,
            'document_data' => [
                'file_path' => $filePath,
                'file_size' => Storage::disk('public')->size($filePath),
                'mime_type' => Storage::disk('public')->mimeType($filePath),
            ],
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
        ]);

        // Log the action
        ActionLog::create([
            'user_id' => $user->id,
            'action' => 'kyc.upload_document',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'document_type' => $request->document_type,
                'document_id' => $kycLog->id,
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document uploaded successfully',
            'data' => [
                'document_id' => $kycLog->id,
                'document_type' => $kycLog->document_type,
                'status' => $kycLog->status,
            ]
        ], 201);
    }

    /**
     * Delete KYC document
     */
    public function deleteDocument(Request $request, $id)
    {
        $user = $request->user();

        $kycLog = KycLog::where('user_id', $user->id)
            ->where('id', $id)
            ->where('status', 'pending')
            ->first();

        if (!$kycLog) {
            return response()->json([
                'success' => false,
                'message' => 'Document not found or cannot be deleted',
                'error_code' => 'DOCUMENT_NOT_FOUND'
            ], 404);
        }

        // Delete file from storage
        if (isset($kycLog->document_data['file_path'])) {
            Storage::disk('public')->delete($kycLog->document_data['file_path']);
        }

        // Delete KYC log
        $kycLog->delete();

        // Log the action
        ActionLog::create([
            'user_id' => $user->id,
            'action' => 'kyc.delete_document',
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'metadata' => [
                'document_type' => $kycLog->document_type,
                'document_id' => $id,
            ]
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Document deleted successfully'
        ]);
    }

    /**
     * Admin: Get pending KYC applications
     */
    public function pendingApplications(Request $request)
    {
        $applications = User::where('kyc_status', 'pending')
            ->with(['kycLogs' => function ($query) {
                $query->orderBy('created_at', 'desc');
            }])
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return response()->json([
            'success' => true,
            'data' => $applications
        ]);
    }

    /**
     * Admin: Approve KYC application
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
            'action' => 'kyc.approve',
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
     * Admin: Reject KYC application
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
            'action' => 'kyc.reject',
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
} 
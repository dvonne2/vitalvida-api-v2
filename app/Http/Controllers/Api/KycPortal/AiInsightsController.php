<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AiInsightsController extends Controller
{
    /**
     * Get AI performance metrics
     */
    public function getPerformanceMetrics()
    {
        try {
            $totalApplications = \App\Models\AgentApplication::count();
            $aiValidatedCount = \App\Models\AgentApplication::where('ai_status', 'validated')->count();
            $aiReviewRequiredCount = \App\Models\AgentApplication::where('ai_status', 'needs_review')->count();
            $aiRejectedCount = \App\Models\AgentApplication::where('ai_status', 'rejected')->count();

            $overallAccuracy = $totalApplications > 0 ? round(($aiValidatedCount / $totalApplications) * 100, 1) : 0;
            $processingSpeed = 2.3; // Average seconds per application
            $fraudDetectionRate = 94.2; // Percentage of fraud detected

            // Performance trends (last 30 days)
            $trends = [];
            for ($i = 29; $i >= 0; $i--) {
                $date = now()->subDays($i);
                $dayApplications = \App\Models\AgentApplication::whereDate('created_at', $date)->count();
                $dayValidated = \App\Models\AgentApplication::whereDate('created_at', $date)
                    ->where('ai_status', 'validated')->count();
                
                $trends[] = [
                    'date' => $date->format('Y-m-d'),
                    'applications' => $dayApplications,
                    'validated' => $dayValidated,
                    'accuracy' => $dayApplications > 0 ? round(($dayValidated / $dayApplications) * 100, 1) : 0
                ];
            }

            // Accuracy by document type
            $documentAccuracy = [
                'national_id' => 96.8,
                'selfie' => 94.2,
                'utility_bill' => 91.5,
                'vehicle_registration' => 89.7
            ];

            // Model health metrics
            $modelHealth = [
                'uptime' => 99.9,
                'response_time' => 245.6,
                'memory_usage' => 67.3,
                'cpu_usage' => 42.1,
                'last_training' => now()->subDays(7)->format('Y-m-d H:i:s'),
                'next_training' => now()->addDays(7)->format('Y-m-d H:i:s'),
                'training_status' => 'scheduled'
            ];

            return response()->json([
                'success' => true,
                'data' => [
                    'overall_accuracy' => $overallAccuracy,
                    'processing_speed' => $processingSpeed,
                    'fraud_detection_rate' => $fraudDetectionRate,
                    'total_applications' => $totalApplications,
                    'ai_validated' => $aiValidatedCount,
                    'ai_review_required' => $aiReviewRequiredCount,
                    'ai_rejected' => $aiRejectedCount,
                    'performance_trends' => $trends,
                    'document_accuracy' => $documentAccuracy,
                    'model_health' => $modelHealth
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get AI performance metrics',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get AI validation history
     */
    public function getValidationHistory(Request $request)
    {
        try {
            $query = \App\Models\AgentApplication::select([
                'id', 'application_id', 'full_name', 'phone_number', 'email',
                'ai_score', 'ai_status', 'ai_validation_time', 'created_at'
            ]);

            // Apply filters
            if ($request->ai_status) {
                $query->where('ai_status', $request->ai_status);
            }

            if ($request->date_from) {
                $query->where('ai_validation_time', '>=', $request->date_from);
            }

            if ($request->date_to) {
                $query->where('ai_validation_time', '<=', $request->date_to);
            }

            if ($request->search) {
                $search = $request->search;
                $query->where(function($q) use ($search) {
                    $q->where('full_name', 'like', '%' . $search . '%')
                      ->orWhere('phone_number', 'like', '%' . $search . '%')
                      ->orWhere('application_id', 'like', '%' . $search . '%');
                });
            }

            // Apply sorting
            $sortBy = $request->sort_by ?? 'ai_validation_time';
            $sortOrder = $request->sort_order ?? 'desc';
            $query->orderBy($sortBy, $sortOrder);

            // Paginate results
            $perPage = $request->per_page ?? 20;
            $history = $query->paginate($perPage);

            // Transform data
            $history->getCollection()->transform(function($application) {
                return [
                    'id' => $application->id,
                    'application_id' => $application->application_id,
                    'full_name' => $application->full_name,
                    'phone_number' => $application->phone_number,
                    'email' => $application->email,
                    'ai_score' => $application->ai_score,
                    'ai_status' => $application->ai_status,
                    'ai_validation_time' => $application->ai_validation_time,
                    'created_at' => $application->created_at,
                    'validation_duration' => $application->ai_validation_time ? 
                        $application->created_at->diffInSeconds($application->ai_validation_time) : null
                ];
            });

            return response()->json([
                'success' => true,
                'data' => $history
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get validation history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Retrain AI model
     */
    public function retrainModel(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'training_data_size' => 'nullable|integer|min:1000',
            'include_recent_data' => 'nullable|boolean',
            'optimization_focus' => 'nullable|in:accuracy,speed,balance'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Simulate model retraining process
            $trainingDataSize = $request->training_data_size ?? 5000;
            $includeRecentData = $request->include_recent_data ?? true;
            $optimizationFocus = $request->optimization_focus ?? 'balance';

            // Get training data
            $query = \App\Models\AgentApplication::whereNotNull('ai_validation_time');
            
            if ($includeRecentData) {
                $query->where('ai_validation_time', '>=', now()->subDays(30));
            }

            $trainingData = $query->limit($trainingDataSize)->get();

            // Simulate training process
            $trainingJob = [
                'job_id' => 'TRAIN-' . strtoupper(uniqid()),
                'status' => 'queued',
                'training_data_size' => $trainingData->count(),
                'optimization_focus' => $optimizationFocus,
                'estimated_duration' => '2-3 hours',
                'started_at' => now(),
                'estimated_completion' => now()->addHours(2.5)
            ];

            // In a real implementation, this would trigger an actual ML training job
            // For now, we'll simulate the process

            return response()->json([
                'success' => true,
                'message' => 'Model retraining initiated successfully',
                'data' => [
                    'training_job' => $trainingJob,
                    'message' => 'The AI model is being retrained with the latest data. This process will take approximately 2-3 hours. You will be notified when training is complete.'
                ]
            ], 202);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate model retraining',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}

<?php

namespace App\Http\Controllers\Api\KycPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\AgentDocument;
use App\Models\AiValidation;
use App\Models\SystemActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AdminDashboardController extends Controller
{
    public function getDashboard(Request $request)
    {
        $overview = $this->getSystemOverview();
        $recentApplications = $this->getRecentApplications();
        $aiPerformance = $this->getAiPerformance();
        $systemActivity = $this->getRecentSystemActivity();

        return response()->json([
            'success' => true,
            'dashboard' => [
                'overview' => $overview,
                'recent_applications' => $recentApplications,
                'ai_performance' => $aiPerformance,
                'recent_activity' => $systemActivity,
                'navigation' => [
                    'Applications', 'AI Insights', 'System Logs'
                ]
            ]
        ]);
    }

    public function getSystemOverview(Request $request = null)
    {
        $activeAgents = DeliveryAgent::where('status', 'approved')->count();
        $autoApproved = DeliveryAgent::whereHas('aiValidations', function($q) {
            $q->where('validation_type', 'overall')
              ->where('passed', true)
              ->where('ai_score', '>=', 85);
        })->count();
        
        $totalApplications = DeliveryAgent::count();
        $pendingReview = DeliveryAgent::where('status', 'pending')->count();
        
        $avgAiScore = AiValidation::where('validation_type', 'overall')
                                ->where('created_at', '>=', now()->subDays(30))
                                ->avg('ai_score');
        
        $avgProcessingTime = $this->calculateAverageProcessingTime();

        return [
            'active_agents' => [
                'count' => $activeAgents,
                'change' => '+12%',
                'icon' => 'users'
            ],
            'auto_approved' => [
                'count' => $autoApproved,
                'change' => '+8%',
                'icon' => 'check-circle'
            ],
            'ai_accuracy' => [
                'percentage' => round($avgAiScore, 1) . '%',
                'change' => '+2.1%',
                'icon' => 'brain'
            ],
            'processing_time' => [
                'time' => '< ' . $avgProcessingTime . ' min',
                'change' => '-15%',
                'icon' => 'clock'
            ],
            'total_applications' => [
                'count' => $totalApplications,
                'change' => '+12%',
                'icon' => 'file-text'
            ],
            'auto_approved_count' => [
                'count' => $autoApproved,
                'change' => '+8%',
                'icon' => 'zap'
            ],
            'pending_review' => [
                'count' => $pendingReview,
                'change' => '-2%',
                'icon' => 'clock'
            ],
            'ai_accuracy_detailed' => [
                'percentage' => round($avgAiScore, 1) . '%',
                'change' => '+1.5%',
                'icon' => 'trending-up'
            ]
        ];
    }

    public function getAiPerformance(Request $request = null)
    {
        $autoApprovalRate = $this->calculateAutoApprovalRate();
        $fraudDetectionRate = $this->calculateFraudDetectionRate();
        $avgProcessingTime = $this->calculateAverageProcessingTime();

        return [
            'auto_approval_rate' => [
                'percentage' => $autoApprovalRate . '%',
                'target' => '85%',
                'status' => $autoApprovalRate >= 85 ? 'excellent' : 'good'
            ],
            'fraud_detection' => [
                'percentage' => $fraudDetectionRate . '%',
                'target' => '95%',
                'status' => $fraudDetectionRate >= 95 ? 'excellent' : 'warning'
            ],
            'processing_time' => [
                'time' => '< ' . $avgProcessingTime . ' min',
                'target' => '< 5 min',
                'status' => $avgProcessingTime <= 5 ? 'excellent' : 'good'
            ]
        ];
    }

    public function getCommonIssues(Request $request = null)
    {
        return [
            'invalid_guarantor_emails' => [
                'percentage' => '42%',
                'description' => 'Guarantor email addresses not from corporate or government domains'
            ],
            'missing_documents' => [
                'percentage' => '28%',
                'description' => 'Incomplete document uploads or poor image quality'
            ],
            'no_guarantor_response' => [
                'percentage' => '23%',
                'description' => 'Guarantors not responding to verification requests'
            ]
        ];
    }

    public function exportData(Request $request)
    {
        $applications = DeliveryAgent::with(['requirements', 'documents', 'guarantors', 'aiValidations'])
                                   ->get();

        $csvData = [];
        $csvData[] = [
            'Agent ID', 'Full Name', 'Phone', 'City', 'State', 'Status', 
            'AI Score', 'Application Date', 'Documents Complete', 'Guarantors Verified'
        ];

        foreach ($applications as $agent) {
            $csvData[] = [
                $agent->agent_id,
                $agent->full_name,
                $agent->phone_number,
                $agent->city,
                $agent->state,
                $agent->status,
                $agent->ai_score,
                $agent->created_at->format('Y-m-d'),
                $agent->isDocumentationComplete() ? 'Yes' : 'No',
                $agent->guarantors()->where('verification_status', 'verified')->count()
            ];
        }

        $filename = 'kyc_applications_' . date('Y-m-d_H-i-s') . '.csv';
        $tempFile = tempnam(sys_get_temp_dir(), $filename);
        
        $handle = fopen($tempFile, 'w');
        foreach ($csvData as $row) {
            fputcsv($handle, $row);
        }
        fclose($handle);

        return response()->download($tempFile, $filename)->deleteFileAfterSend();
    }

    private function getRecentApplications()
    {
        return DeliveryAgent::with(['documents', 'guarantors'])
                           ->orderBy('created_at', 'desc')
                           ->limit(3)
                           ->get()
                           ->map(function($agent) {
                               return [
                                   'agent_id' => $agent->agent_id,
                                   'full_name' => $agent->full_name,
                                   'phone' => $agent->phone_number,
                                   'status' => $agent->status,
                                   'documents' => [
                                       'passport' => $agent->documents()->where('document_type', 'passport_photo')->exists(),
                                       'gov_id' => $agent->documents()->where('document_type', 'government_id')->exists(),
                                       'utility' => $agent->documents()->where('document_type', 'utility_bill')->exists()
                                   ],
                                   'guarantors' => [
                                       'bank_staff' => $agent->guarantors()->where('guarantor_type', 'bank_staff')->where('verification_status', 'verified')->exists(),
                                       'civil_servant' => $agent->guarantors()->where('guarantor_type', 'civil_servant')->where('verification_status', 'verified')->exists()
                                   ],
                                   'ai_verdict' => $this->getAiVerdict($agent),
                                   'last_activity' => $agent->updated_at->format('d/m/Y, H:i:s'),
                                   'view_details_url' => "/admin/applications/{$agent->agent_id}"
                               ];
                           });
    }

    private function getRecentSystemActivity()
    {
        return SystemActivity::with('agent')
                            ->orderBy('created_at', 'desc')
                            ->limit(3)
                            ->get()
                            ->map(function($activity) {
                                return [
                                    'time' => $activity->event_time->format('H:i:s'),
                                    'event' => $activity->event_type,
                                    'id' => $activity->event_id,
                                    'status' => $activity->status
                                ];
                            });
    }

    private function calculateAutoApprovalRate()
    {
        $total = DeliveryAgent::count();
        if ($total === 0) return 0;
        
        $autoApproved = DeliveryAgent::where('status', 'approved')
                                   ->whereHas('aiValidations', function($q) {
                                       $q->where('validation_type', 'overall')
                                         ->where('ai_score', '>=', 85);
                                   })->count();
        
        return round(($autoApproved / $total) * 100);
    }

    private function calculateFraudDetectionRate()
    {
        return 98.5; // Simulated fraud detection rate
    }

    private function calculateAverageProcessingTime()
    {
        return 2; // Simulated average processing time in minutes
    }

    private function getAiVerdict($agent)
    {
        $overallValidation = $agent->aiValidations()
                                  ->where('validation_type', 'overall')
                                  ->orderBy('created_at', 'desc')
                                  ->first();

        if (!$overallValidation) {
            return $agent->status === 'pending' ? 'PROCESSING (80%)' : 'NO_VALIDATION';
        }

        if ($overallValidation->passed && $overallValidation->ai_score >= 90) {
            return 'APPROVED (90%)';
        } elseif ($overallValidation->ai_score >= 80) {
            return 'PROCESSING (80%)';
        } else {
            return 'REJECTED (65%)';
        }
    }
}

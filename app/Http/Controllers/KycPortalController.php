<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Auth;

class KycPortalController extends Controller
{
    protected $apiBaseUrl;

    public function __construct()
    {
        $this->apiBaseUrl = config('app.url') . '/api/kyc-portal';
    }

    /**
     * Show the agent application form
     */
    public function showApplicationForm()
    {
        return view('kyc-portal.application.form');
    }

    /**
     * Show application status
     */
    public function showApplicationStatus($applicationId)
    {
        try {
            $response = Http::get("{$this->apiBaseUrl}/agent-application/status/{$applicationId}");
            $data = $response->json();
            
            return view('kyc-portal.application.status', [
                'application' => $data['data'] ?? [],
                'status' => $data['success'] ?? false
            ]);
        } catch (\Exception $e) {
            return view('kyc-portal.application.status', [
                'application' => [],
                'status' => false,
                'error' => 'Unable to load application status'
            ]);
        }
    }

    /**
     * Show next steps for application
     */
    public function showNextSteps($applicationId)
    {
        try {
            $response = Http::get("{$this->apiBaseUrl}/agent-application/next-steps/{$applicationId}");
            $data = $response->json();
            
            return view('kyc-portal.application.next-steps', [
                'nextSteps' => $data['data'] ?? [],
                'status' => $data['success'] ?? false
            ]);
        } catch (\Exception $e) {
            return view('kyc-portal.application.next-steps', [
                'nextSteps' => [],
                'status' => false,
                'error' => 'Unable to load next steps'
            ]);
        }
    }

    /**
     * Show admin dashboard
     */
    public function adminDashboard()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . session('auth_token')
            ])->get("{$this->apiBaseUrl}/admin/dashboard");
            
            $data = $response->json();
            
            return view('kyc-portal.admin.dashboard', [
                'metrics' => $data['data']['metrics'] ?? [],
                'recentApplications' => $data['data']['recent_applications'] ?? []
            ]);
        } catch (\Exception $e) {
            // Return mock data for demonstration
            return view('kyc-portal.admin.dashboard', [
                'metrics' => [
                    'total_applications' => 1247,
                    'pending_review' => 23,
                    'ai_approval_rate' => 87.5,
                    'system_performance' => 94.2
                ],
                'recentApplications' => [
                    [
                        'id' => 1,
                        'full_name' => 'John Doe',
                        'phone_number' => '08012345678',
                        'email' => 'john.doe@example.com',
                        'status' => 'pending',
                        'ai_score' => 85,
                        'created_at' => now()->subHours(2)
                    ],
                    [
                        'id' => 2,
                        'full_name' => 'Jane Smith',
                        'phone_number' => '08087654321',
                        'email' => 'jane.smith@example.com',
                        'status' => 'approved',
                        'ai_score' => 92,
                        'created_at' => now()->subHours(4)
                    ],
                    [
                        'id' => 3,
                        'full_name' => 'Mike Johnson',
                        'phone_number' => '08011223344',
                        'email' => 'mike.johnson@example.com',
                        'status' => 'rejected',
                        'ai_score' => 45,
                        'created_at' => now()->subHours(6)
                    ]
                ]
            ]);
        }
    }

    /**
     * Show admin login form
     */
    public function showLogin()
    {
        return view('kyc-portal.auth.login');
    }

    /**
     * Authenticate admin user
     */
    public function authenticate(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Demo authentication for development
        if ($request->email === 'admin@vitalvida.com' && $request->password === 'admin123') {
            session(['auth_token' => 'demo_token_' . time()]);
            session(['admin_user' => [
                'id' => 1,
                'name' => 'Admin User',
                'email' => 'admin@vitalvida.com',
                'role' => 'admin'
            ]]);
            
            return redirect()->route('kyc.admin.dashboard')
                ->with('success', 'Welcome back, Admin!');
        }

        return back()->withErrors([
            'email' => 'The provided credentials do not match our records.',
        ])->withInput($request->only('email'));
    }

    /**
     * Logout admin user
     */
    public function logout(Request $request)
    {
        session()->forget(['auth_token', 'admin_user']);
        
        return redirect()->route('kyc.admin.login')
            ->with('success', 'You have been successfully logged out.');
    }

    /**
     * Show admin applications list
     */
    public function adminApplications(Request $request)
    {
        try {
            $params = $request->all();
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . session('auth_token')
            ])->get("{$this->apiBaseUrl}/admin/applications", $params);
            
            $data = $response->json();
            
            return view('kyc-portal.admin.applications', [
                'applications' => $data['data'] ?? []
            ]);
        } catch (\Exception $e) {
            // Return mock data for demonstration
            return view('kyc-portal.admin.applications', [
                'applications' => collect([
                    [
                        'id' => 1,
                        'full_name' => 'John Doe',
                        'phone_number' => '08012345678',
                        'email' => 'john.doe@example.com',
                        'city' => 'Lagos',
                        'state' => 'Lagos',
                        'address' => '123 Main Street, Lagos',
                        'kyc_status' => 'pending',
                        'ai_score' => 85.5,
                        'has_national_id' => true,
                        'has_selfie' => true,
                        'has_utility_bill' => false,
                        'created_at' => now()->subHours(2)
                    ],
                    [
                        'id' => 2,
                        'full_name' => 'Jane Smith',
                        'phone_number' => '08087654321',
                        'email' => 'jane.smith@example.com',
                        'city' => 'Abuja',
                        'state' => 'FCT',
                        'address' => '456 Central Avenue, Abuja',
                        'kyc_status' => 'approved',
                        'ai_score' => 92.3,
                        'has_national_id' => true,
                        'has_selfie' => true,
                        'has_utility_bill' => true,
                        'created_at' => now()->subHours(4)
                    ],
                    [
                        'id' => 3,
                        'full_name' => 'Mike Johnson',
                        'phone_number' => '08011223344',
                        'email' => 'mike.johnson@example.com',
                        'city' => 'Port Harcourt',
                        'state' => 'Rivers',
                        'address' => '789 Harbor Road, Port Harcourt',
                        'kyc_status' => 'in_review',
                        'ai_score' => 78.9,
                        'has_national_id' => true,
                        'has_selfie' => false,
                        'has_utility_bill' => true,
                        'created_at' => now()->subHours(6)
                    ]
                ])
            ]);
        }
    }

    /**
     * Show individual application details
     */
    public function showApplication($applicationId)
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . session('auth_token')
            ])->get("{$this->apiBaseUrl}/admin/applications/{$applicationId}");
            
            $data = $response->json();
            
            return view('kyc-portal.admin.application-details', [
                'application' => $data['data'] ?? []
            ]);
        } catch (\Exception $e) {
            return view('kyc-portal.admin.application-details', [
                'application' => [],
                'error' => 'Unable to load application details'
            ]);
        }
    }

    /**
     * Show AI insights
     */
    public function aiInsights()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . session('auth_token')
            ])->get("{$this->apiBaseUrl}/admin/ai-insights/performance");
            
            $data = $response->json();
            
            return view('kyc-portal.admin.ai-insights', [
                'performanceMetrics' => $data['data']['performance'] ?? [],
                'validationHistory' => $data['data']['validation_history'] ?? []
            ]);
        } catch (\Exception $e) {
            // Return mock data for demonstration
            return view('kyc-portal.admin.ai-insights', [
                'performanceMetrics' => [
                    'overall_accuracy' => 87.5,
                    'processing_speed' => 94.2,
                    'fraud_detection' => 82.1
                ],
                'validationHistory' => [
                    [
                        'application_id' => 'VV-2024-001',
                        'document_type' => 'national_id',
                        'ai_score' => 92.5,
                        'confidence' => 89.3,
                        'processing_time' => 2.3,
                        'status' => 'validated',
                        'created_at' => now()->subMinutes(30)
                    ],
                    [
                        'application_id' => 'VV-2024-002',
                        'document_type' => 'selfie',
                        'ai_score' => 78.9,
                        'confidence' => 76.2,
                        'processing_time' => 3.1,
                        'status' => 'pending',
                        'created_at' => now()->subMinutes(45)
                    ],
                    [
                        'application_id' => 'VV-2024-003',
                        'document_type' => 'utility_bill',
                        'ai_score' => 85.7,
                        'confidence' => 82.1,
                        'processing_time' => 2.8,
                        'status' => 'validated',
                        'created_at' => now()->subMinutes(60)
                    ]
                ]
            ]);
        }
    }

    /**
     * Show system logs
     */
    public function systemLogs()
    {
        try {
            $response = Http::withHeaders([
                'Authorization' => 'Bearer ' . session('auth_token')
            ])->get("{$this->apiBaseUrl}/admin/system-logs");
            
            $data = $response->json();
            
            return view('kyc-portal.admin.system-logs', [
                'metrics' => $data['data']['metrics'] ?? [],
                'recentActivities' => $data['data']['recent_activities'] ?? [],
                'errorLogs' => $data['data']['error_logs'] ?? []
            ]);
        } catch (\Exception $e) {
            // Return mock data for demonstration
            return view('kyc-portal.admin.system-logs', [
                'metrics' => [
                    'total_activities' => 1247,
                    'active_users' => 23,
                    'system_errors' => 2,
                    'avg_response_time' => 245.6
                ],
                'recentActivities' => [
                    [
                        'type' => 'application_submitted',
                        'description' => 'New application submitted by John Doe',
                        'user' => 'John Doe',
                        'ip_address' => '192.168.1.100',
                        'created_at' => now()->subMinutes(5)
                    ],
                    [
                        'type' => 'application_approved',
                        'description' => 'Application approved for Jane Smith',
                        'user' => 'Admin User',
                        'ip_address' => '192.168.1.101',
                        'created_at' => now()->subMinutes(15)
                    ],
                    [
                        'type' => 'login',
                        'description' => 'User logged in successfully',
                        'user' => 'Mike Johnson',
                        'ip_address' => '192.168.1.102',
                        'created_at' => now()->subMinutes(30)
                    ]
                ],
                'errorLogs' => [
                    [
                        'severity' => 'low',
                        'message' => 'Document upload timeout',
                        'file' => 'DocumentController.php',
                        'line' => 45,
                        'user' => 'System',
                        'resolved' => false,
                        'created_at' => now()->subHours(2)
                    ],
                    [
                        'severity' => 'medium',
                        'message' => 'AI validation service temporarily unavailable',
                        'file' => 'AiValidationService.php',
                        'line' => 123,
                        'user' => 'System',
                        'resolved' => true,
                        'created_at' => now()->subHours(4)
                    ]
                ]
            ]);
        }
    }
} 
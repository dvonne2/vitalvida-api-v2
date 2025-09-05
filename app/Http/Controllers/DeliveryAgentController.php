<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class DeliveryAgentController extends Controller
{
    public function getProfile($id)
    {
        // Mock delivery agent profiles
        $profiles = [
            501 => [
                'agent_id' => 501,
                'name' => 'Emeka Okafor',
                'email' => 'emeka.okafor@vitalvida.com',
                'phone' => '+234-803-1234-567',
                'status' => 'active',
                'location' => 'Lagos - Ikeja',
                'zone' => 'Zone A',
                'hire_date' => '2024-03-15',
                'employee_id' => 'DA-501-LG',
                'profile_image' => 'https://via.placeholder.com/150/4f46e5/white?text=EO',
                'performance' => [
                    'rating' => 4.2,
                    'deliveries_completed' => 847,
                    'on_time_rate' => 87.5,
                    'customer_satisfaction' => 4.3,
                    'returns_processed' => 23
                ],
                'current_stats' => [
                    'penalty_points' => 5,
                    'active_strikes' => 1,
                    'total_fines' => 15000,
                    'last_fine_date' => '2025-07-09',
                    'warnings_this_month' => 2
                ],
                'recent_activity' => [
                    'last_delivery' => '2025-07-09T11:30:00Z',
                    'last_login' => '2025-07-09T08:15:00Z',
                    'deliveries_today' => 8,
                    'deliveries_this_week' => 42
                ],
                'assigned_areas' => ['Ikeja', 'Ogba', 'Agege'],
                'vehicle_info' => [
                    'type' => 'Motorcycle',
                    'plate_number' => 'EKY-123-AB',
                    'insurance_expiry' => '2025-12-31'
                ],
                'emergency_contact' => [
                    'name' => 'Chioma Okafor',
                    'relationship' => 'Wife',
                    'phone' => '+234-803-7654-321'
                ]
            ],
            502 => [
                'agent_id' => 502,
                'name' => 'Fatima Abdullahi',
                'email' => 'fatima.abdullahi@vitalvida.com',
                'phone' => '+234-806-2345-678',
                'status' => 'active',
                'location' => 'Abuja - Wuse',
                'zone' => 'Zone B',
                'hire_date' => '2024-01-20',
                'employee_id' => 'DA-502-AB',
                'profile_image' => 'https://via.placeholder.com/150/10b981/white?text=FA',
                'performance' => [
                    'rating' => 4.7,
                    'deliveries_completed' => 1203,
                    'on_time_rate' => 92.3,
                    'customer_satisfaction' => 4.6,
                    'returns_processed' => 18
                ],
                'current_stats' => [
                    'penalty_points' => 1,
                    'active_strikes' => 0,
                    'total_fines' => 2000,
                    'last_fine_date' => '2025-06-15',
                    'warnings_this_month' => 0
                ],
                'recent_activity' => [
                    'last_delivery' => '2025-07-09T10:45:00Z',
                    'last_login' => '2025-07-09T07:20:00Z',
                    'deliveries_today' => 12,
                    'deliveries_this_week' => 58
                ],
                'assigned_areas' => ['Wuse', 'Garki', 'Maitama'],
                'vehicle_info' => [
                    'type' => 'Motorcycle',
                    'plate_number' => 'FCT-456-CD',
                    'insurance_expiry' => '2026-03-15'
                ],
                'emergency_contact' => [
                    'name' => 'Musa Abdullahi',
                    'relationship' => 'Brother',
                    'phone' => '+234-806-8765-432'
                ]
            ],
            503 => [
                'agent_id' => 503,
                'name' => 'Ibrahim Musa',
                'email' => 'ibrahim.musa@vitalvida.com',
                'phone' => '+234-809-3456-789',
                'status' => 'on_probation',
                'location' => 'Kano - Sabon Gari',
                'zone' => 'Zone C',
                'hire_date' => '2024-06-10',
                'employee_id' => 'DA-503-KN',
                'profile_image' => 'https://via.placeholder.com/150/f59e0b/white?text=IM',
                'performance' => [
                    'rating' => 3.1,
                    'deliveries_completed' => 324,
                    'on_time_rate' => 68.4,
                    'customer_satisfaction' => 3.2,
                    'returns_processed' => 45
                ],
                'current_stats' => [
                    'penalty_points' => 12,
                    'active_strikes' => 2,
                    'total_fines' => 28000,
                    'last_fine_date' => '2025-07-08',
                    'warnings_this_month' => 5
                ],
                'recent_activity' => [
                    'last_delivery' => '2025-07-08T16:20:00Z',
                    'last_login' => '2025-07-09T09:05:00Z',
                    'deliveries_today' => 4,
                    'deliveries_this_week' => 28
                ],
                'assigned_areas' => ['Sabon Gari', 'Fagge'],
                'vehicle_info' => [
                    'type' => 'Motorcycle',
                    'plate_number' => 'KN-789-EF',
                    'insurance_expiry' => '2025-09-30'
                ],
                'emergency_contact' => [
                    'name' => 'Aisha Musa',
                    'relationship' => 'Sister',
                    'phone' => '+234-809-9876-543'
                ]
            ]
        ];

        // Check if agent exists
        if (!isset($profiles[$id])) {
            return response()->json([
                'status' => 'error',
                'message' => 'Delivery agent not found',
                'error_code' => 'AGENT_NOT_FOUND'
            ], 404);
        }

        $profile = $profiles[$id];

        // Add computed fields
        $profile['account_health'] = $this->calculateAccountHealth($profile);
        $profile['next_review_date'] = $this->getNextReviewDate($profile);
        $profile['probation_end_date'] = $profile['status'] === 'on_probation' ? '2025-08-10' : null;

        return response()->json([
            'status' => 'success',
            'data' => $profile,
            'meta' => [
                'last_updated' => now()->toISOString(),
                'data_source' => 'hr_system',
                'access_level' => 'full'
            ]
        ]);
    }

    private function calculateAccountHealth($profile)
    {
        $score = 100;
        
        // Deduct for penalty points
        $score -= $profile['current_stats']['penalty_points'] * 5;
        
        // Deduct for active strikes
        $score -= $profile['current_stats']['active_strikes'] * 15;
        
        // Bonus for good performance
        if ($profile['performance']['rating'] > 4.0) {
            $score += 10;
        }
        
        // Bonus for on-time delivery
        if ($profile['performance']['on_time_rate'] > 85) {
            $score += 5;
        }

        $score = max(0, min(100, $score)); // Clamp between 0-100

        if ($score >= 80) return 'excellent';
        if ($score >= 60) return 'good';
        if ($score >= 40) return 'fair';
        return 'poor';
    }

    private function getNextReviewDate($profile)
    {
        if ($profile['status'] === 'on_probation') {
            return '2025-07-15'; // Weekly reviews during probation
        }
        
        if ($profile['current_stats']['penalty_points'] > 8) {
            return '2025-07-20'; // Bi-weekly for high penalty points
        }
        
        return '2025-08-01'; // Monthly review for regular agents
    }
}

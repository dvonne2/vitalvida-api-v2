<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\DeliveryAgent;
use App\Models\AgentRequirement;
use App\Models\AgentDocument;
use App\Models\AgentGuarantor;
use App\Models\AiValidation;
use App\Models\SystemActivity;
use App\Models\CompensationSetting;

class KycPortalSeeder extends Seeder
{
    public function run()
    {
        // Create compensation settings
        CompensationSetting::create([
            'pickup_return_amount' => 1500.00,
            'maximum_per_delivery' => 2500.00,
            'minimum_per_delivery' => 500.00,
            'payment_frequency' => 'weekly',
            'payment_method' => 'portal',
            'payment_threshold' => 5000.00,
            'base_commission_rate' => 10.00,
            'bonus_commission_rate' => 5.00,
            'bonus_delivery_threshold' => 50,
            'on_time_delivery_bonus' => 200.00,
            'customer_satisfaction_bonus' => 300.00,
            'referral_bonus' => 1000.00,
            'late_delivery_penalty' => 100.00,
            'customer_complaint_penalty' => 200.00,
            'damage_penalty' => 500.00,
            'active' => true,
            'effective_from' => now(),
            'created_by' => 'System'
        ]);

        // Create sample agents with different statuses
        $agents = [
            [
                'full_name' => 'John Adebayo',
                'phone_number' => '+2348012345678',
                'whatsapp_number' => '+2348012345678',
                'city' => 'Yaba',
                'state' => 'Lagos',
                'email' => 'john.adebayo@email.com',
                'kyc_status' => 'approved',
                'ai_score' => 96.2,
                'application_step' => 4,
                'auto_approved' => true,
                'auto_approved_at' => now()->subDays(2),
                'kyc_approved_at' => now()->subDays(2)
            ],
            [
                'full_name' => 'Grace Okonkwo',
                'phone_number' => '+2348013456789',
                'whatsapp_number' => '+2348013456789',
                'city' => 'Ibadan',
                'state' => 'Oyo',
                'email' => 'grace.okonkwo@email.com',
                'kyc_status' => 'waiting_guarantors',
                'ai_score' => 88.5,
                'application_step' => 3,
                'application_submitted_at' => now()->subDays(1)
            ],
            [
                'full_name' => 'Ahmed Hassan',
                'phone_number' => '+2348014567890',
                'whatsapp_number' => '+2348014567890',
                'city' => 'Kano',
                'state' => 'Kano',
                'email' => 'ahmed.hassan@email.com',
                'kyc_status' => 'rejected',
                'ai_score' => 65.3,
                'application_step' => 3,
                'kyc_rejected_at' => now()->subDays(3),
                'rejection_reason' => 'Incomplete documentation and low AI score'
            ],
            [
                'full_name' => 'Fatima Yusuf',
                'phone_number' => '+2348015678901',
                'whatsapp_number' => '+2348015678901',
                'city' => 'Kaduna',
                'state' => 'Kaduna',
                'email' => 'fatima.yusuf@email.com',
                'kyc_status' => 'pending',
                'ai_score' => 72.8,
                'application_step' => 2,
                'application_submitted_at' => now()->subHours(6)
            ],
            [
                'full_name' => 'Emeka Okechukwu',
                'phone_number' => '+2348016789012',
                'whatsapp_number' => '+2348016789012',
                'city' => 'Enugu',
                'state' => 'Enugu',
                'email' => 'emeka.okechukwu@email.com',
                'kyc_status' => 'approved',
                'ai_score' => 91.7,
                'application_step' => 4,
                'auto_approved' => true,
                'auto_approved_at' => now()->subDays(5),
                'kyc_approved_at' => now()->subDays(5)
            ],
            [
                'full_name' => 'Aisha Bello',
                'phone_number' => '+2348017890123',
                'whatsapp_number' => '+2348017890123',
                'city' => 'Sokoto',
                'state' => 'Sokoto',
                'email' => 'aisha.bello@email.com',
                'kyc_status' => 'waiting_guarantors',
                'ai_score' => 85.2,
                'application_step' => 3,
                'application_submitted_at' => now()->subDays(2)
            ],
            [
                'full_name' => 'Chukwudi Nwankwo',
                'phone_number' => '+2348018901234',
                'whatsapp_number' => '+2348018901234',
                'city' => 'Awka',
                'state' => 'Anambra',
                'email' => 'chukwudi.nwankwo@email.com',
                'kyc_status' => 'pending',
                'ai_score' => 78.9,
                'application_step' => 1,
                'application_submitted_at' => now()->subHours(12)
            ],
            [
                'full_name' => 'Hauwa Abdullahi',
                'phone_number' => '+2348019012345',
                'whatsapp_number' => '+2348019012345',
                'city' => 'Maiduguri',
                'state' => 'Borno',
                'email' => 'hauwa.abdullahi@email.com',
                'kyc_status' => 'rejected',
                'ai_score' => 58.4,
                'application_step' => 2,
                'kyc_rejected_at' => now()->subDays(1),
                'rejection_reason' => 'Failed background check and document verification'
            ]
        ];

        foreach ($agents as $index => $agentData) {
            $agent = DeliveryAgent::create(array_merge($agentData, [
                'agent_id' => 'DA' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'status' => 'active',
                'da_code' => 'DA' . str_pad($index + 1, 3, '0', STR_PAD_LEFT),
                'current_location' => $agentData['city'],
                'total_deliveries' => rand(0, 150),
                'successful_deliveries' => rand(0, 120),
                'rating' => rand(35, 50) / 10,
                'total_earnings' => rand(50000, 500000),
                'commission_rate' => 10.00
            ]));

            // Create requirements
            AgentRequirement::create([
                'agent_id' => $agent->id,
                'has_smartphone' => true,
                'has_transportation' => true,
                'transportation_type' => ['motorcycle', 'car', 'bicycle'][rand(0, 2)],
                'has_drivers_license' => rand(0, 1),
                'can_store_products' => true,
                'comfortable_with_portal' => true,
                'delivery_areas' => [$agentData['city'], $agentData['state'], 'Other Cities'],
                'has_bank_account' => rand(0, 1),
                'bank_name' => ['GT Bank', 'Zenith Bank', 'Access Bank', 'First Bank'][rand(0, 3)],
                'account_number' => '0' . rand(1000000000, 9999999999),
                'account_name' => $agentData['full_name'],
                'preferred_communication' => ['whatsapp', 'phone', 'email'][rand(0, 2)],
                'can_receive_notifications' => true,
                'availability_hours' => ['09:00-17:00', '08:00-18:00', '10:00-16:00'],
                'available_weekends' => rand(0, 1),
                'available_holidays' => rand(0, 1),
                'delivery_experience' => ['none', 'less_than_1_year', '1_3_years', '3_5_years', '5_plus_years'][rand(0, 4)],
                'previous_experience' => rand(0, 1) ? 'Previous delivery experience with other companies' : null,
                'requirements_score' => rand(60, 100),
                'meets_minimum_requirements' => true
            ]);

            // Create guarantors for some agents
            if (in_array($agent->kyc_status, ['approved', 'waiting_guarantors'])) {
                $guarantorTypes = ['bank_staff', 'civil_servant', 'business_owner', 'professional'];
                $guarantorType = $guarantorTypes[rand(0, 3)];
                
                AgentGuarantor::create([
                    'agent_id' => $agent->id,
                    'guarantor_type' => $guarantorType,
                    'full_name' => $this->generateGuarantorName(),
                    'email' => 'guarantor.' . $agent->agent_id . '@email.com',
                    'phone_number' => '+23480' . rand(10000000, 99999999),
                    'organization' => $this->getOrganizationByType($guarantorType),
                    'position' => $this->getPositionByType($guarantorType),
                    'employee_id' => 'EMP' . rand(10000, 99999),
                    'address' => $agentData['city'] . ', ' . $agentData['state'],
                    'city' => $agentData['city'],
                    'state' => $agentData['state'],
                    'verification_status' => $agent->kyc_status === 'approved' ? 'verified' : 'pending',
                    'verification_code' => strtoupper(substr(md5(rand()), 0, 6)),
                    'verified_at' => $agent->kyc_status === 'approved' ? now()->subDays(rand(1, 5)) : null,
                    'relationship' => ['family', 'friend', 'colleague', 'employer'][rand(0, 3)],
                    'years_known' => rand(1, 10),
                    'guarantor_score' => rand(70, 95),
                    'is_primary_guarantor' => true
                ]);
            }

            // Create AI validations
            $validationTypes = ['document', 'data', 'guarantor', 'overall'];
            foreach ($validationTypes as $type) {
                $score = $type === 'overall' ? $agent->ai_score : rand(70, 98);
                
                AiValidation::create([
                    'agent_id' => $agent->id,
                    'validation_type' => $type,
                    'ai_score' => $score,
                    'confidence_level' => rand(85, 99),
                    'validation_result' => [
                        'clarity_score' => rand(80, 95),
                        'authenticity_score' => rand(85, 98),
                        'completeness_score' => rand(90, 100),
                        'risk_factors' => $score < 80 ? ['low_score', 'incomplete_data'] : []
                    ],
                    'passed' => $score >= 85,
                    'status' => 'completed',
                    'validation_date' => now()->subDays(rand(1, 7)),
                    'processing_completed_at' => now()->subDays(rand(1, 7)),
                    'processing_duration_ms' => rand(1000, 5000),
                    'ai_model_version' => 'v2.1.0',
                    'ai_provider' => 'OpenAI',
                    'risk_level' => $score >= 90 ? 'low' : ($score >= 80 ? 'medium' : ($score >= 70 ? 'high' : 'critical')),
                    'requires_manual_review' => $score < 80
                ]);
            }

            // Create system activities
            $this->createSystemActivities($agent);
        }
    }

    private function generateGuarantorName()
    {
        $firstNames = ['Adebayo', 'Okonkwo', 'Hassan', 'Yusuf', 'Okechukwu', 'Bello', 'Nwankwo', 'Abdullahi'];
        $lastNames = ['Johnson', 'Williams', 'Brown', 'Jones', 'Garcia', 'Miller', 'Davis', 'Rodriguez'];
        
        return $firstNames[rand(0, 7)] . ' ' . $lastNames[rand(0, 7)];
    }

    private function getOrganizationByType($type)
    {
        return match($type) {
            'bank_staff' => ['GT Bank', 'Zenith Bank', 'Access Bank', 'First Bank'][rand(0, 3)],
            'civil_servant' => ['Ministry of Finance', 'Ministry of Education', 'Ministry of Health', 'Local Government'][rand(0, 3)],
            'business_owner' => ['ABC Enterprises', 'XYZ Corporation', 'Global Solutions', 'Innovation Ltd'][rand(0, 3)],
            'professional' => ['Law Firm Associates', 'Medical Center', 'Engineering Corp', 'Consulting Group'][rand(0, 3)],
            default => 'Unknown Organization'
        };
    }

    private function getPositionByType($type)
    {
        return match($type) {
            'bank_staff' => ['Account Officer', 'Branch Manager', 'Customer Service', 'Operations Officer'][rand(0, 3)],
            'civil_servant' => ['Administrative Officer', 'Director', 'Deputy Director', 'Senior Officer'][rand(0, 3)],
            'business_owner' => ['CEO', 'Managing Director', 'Owner', 'Director'][rand(0, 3)],
            'professional' => ['Senior Partner', 'Consultant', 'Manager', 'Specialist'][rand(0, 3)],
            default => 'Staff'
        };
    }

    private function createSystemActivities($agent)
    {
        $activities = [];
        
        // Application submitted
        $activities[] = [
            'event_type' => 'APPLICATION_SUBMITTED',
            'status' => 'SUCCESS',
            'description' => "Agent {$agent->agent_id} submitted application",
            'event_time' => $agent->application_submitted_at ?? now()->subDays(rand(1, 7))
        ];

        // AI validation activities
        if ($agent->ai_score >= 85) {
            $activities[] = [
                'event_type' => 'AUTO_APPROVED',
                'status' => 'SUCCESS',
                'description' => "Agent {$agent->agent_id} auto-approved with AI score: {$agent->ai_score}",
                'event_time' => $agent->auto_approved_at ?? now()->subDays(rand(1, 5))
            ];
        } elseif ($agent->kyc_status === 'rejected') {
            $activities[] = [
                'event_type' => 'APPLICATION_REJECTED',
                'status' => 'REJECTED',
                'description' => "Agent {$agent->agent_id} application rejected: {$agent->rejection_reason}",
                'event_time' => $agent->kyc_rejected_at ?? now()->subDays(rand(1, 3))
            ];
        } elseif ($agent->kyc_status === 'waiting_guarantors') {
            $activities[] = [
                'event_type' => 'GUARANTOR_REMINDED',
                'status' => 'PENDING',
                'description' => "Guarantor reminder sent for agent {$agent->agent_id}",
                'event_time' => now()->subDays(rand(1, 2))
            ];
        }

        // Document verification activities
        if ($agent->kyc_status === 'approved') {
            $activities[] = [
                'event_type' => 'DOCUMENT_VERIFIED',
                'status' => 'SUCCESS',
                'description' => "Documents verified for agent {$agent->agent_id}",
                'event_time' => now()->subDays(rand(1, 3))
            ];
        }

        // Create activities
        foreach ($activities as $activity) {
            SystemActivity::create([
                'event_type' => $activity['event_type'],
                'agent_id' => $agent->id,
                'event_id' => $agent->agent_id,
                'status' => $activity['status'],
                'event_time' => $activity['event_time'],
                'description' => $activity['description'],
                'metadata' => ['ai_score' => $agent->ai_score]
            ]);
        }
    }
}

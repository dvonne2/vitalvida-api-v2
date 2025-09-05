<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Accountant;
use App\Models\PaymentRecord;
use App\Models\StrikeRecord;
use App\Models\BonusTracking;
use App\Models\ExpenseRequest;
use App\Models\EscalationRequest;
use App\Models\SystemCompliance;
use App\Models\DailyProgressTracking;
use App\Models\User;

class AccountantPortalSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create demo accountants
        $accountants = [
            [
                'employee_id' => 'ACC001',
                'full_name' => 'Sarah Johnson',
                'email' => 'sarah.johnson@vitalvida.ng',
                'phone_number' => '+2348012345678',
                'department' => 'Financial Management',
                'role' => 'accountant',
                'status' => 'active',
                'hire_date' => '2024-01-15',
                'current_strikes' => 0,
                'total_penalties' => 0
            ],
            [
                'employee_id' => 'ACC002',
                'full_name' => 'Michael Chen',
                'email' => 'michael.chen@vitalvida.ng',
                'phone_number' => '+2348023456789',
                'department' => 'Financial Management',
                'role' => 'financial_controller',
                'status' => 'active',
                'hire_date' => '2023-08-20',
                'current_strikes' => 1,
                'total_penalties' => 20000
            ],
            [
                'employee_id' => 'ACC003',
                'full_name' => 'David Okechukwu',
                'email' => 'david.okechukwu@vitalvida.ng',
                'phone_number' => '+2348034567890',
                'department' => 'Financial Management',
                'role' => 'accountant',
                'status' => 'active',
                'hire_date' => '2024-03-10',
                'current_strikes' => 0,
                'total_penalties' => 0
            ]
        ];

        foreach ($accountants as $accountantData) {
            $accountant = Accountant::create($accountantData);
            
            // Create demo payment records
            $this->createPaymentRecords($accountant);
            
            // Create demo strike records
            $this->createStrikeRecords($accountant);
            
            // Create demo bonus tracking
            $this->createBonusTracking($accountant);
            
            // Create demo expense requests
            $this->createExpenseRequests($accountant);
            
            // Create demo escalation requests
            $this->createEscalationRequests($accountant);
            
            // Create demo compliance records
            $this->createComplianceRecords($accountant);
            
            // Create demo daily progress
            $this->createDailyProgress($accountant);
        }
    }

    private function createPaymentRecords($accountant)
    {
        $paymentRecords = [
            [
                'order_id' => 'VV-2024-001',
                'customer_payment_received' => true,
                'da_name' => 'John Delivery',
                'da_phone' => '+2348045678901',
                'delivery_amount' => 15000.00,
                'payment_method' => 'cash',
                'verification_status' => '3_way_match',
                'zoho_status' => 'synced',
                'im_says' => '₦ 15,000',
                'da_says' => '₦ 15,000',
                'zoho_shows' => '₦ 15,000',
                'processed_by' => $accountant->id,
                'processed_at' => now(),
                'receipt_uploaded' => true,
                'receipt_path' => 'receipts/VV-2024-001/receipt_001.jpg'
            ],
            [
                'order_id' => 'VV-2024-002',
                'customer_payment_received' => true,
                'da_name' => 'Mary Transport',
                'da_phone' => '+2348056789012',
                'delivery_amount' => 25000.00,
                'payment_method' => 'transfer',
                'verification_status' => 'mismatch',
                'zoho_status' => 'error',
                'im_says' => '₦ 25,000',
                'da_says' => '₦ 20,000',
                'zoho_shows' => '₦ 25,000',
                'processed_by' => $accountant->id,
                'processed_at' => now(),
                'receipt_uploaded' => false
            ],
            [
                'order_id' => 'VV-2024-003',
                'customer_payment_received' => true,
                'da_name' => 'Peter Logistics',
                'da_phone' => '+2348067890123',
                'delivery_amount' => 12000.00,
                'payment_method' => 'pos',
                'verification_status' => '3_way_match',
                'zoho_status' => 'synced',
                'im_says' => '₦ 12,000',
                'da_says' => '₦ 12,000',
                'zoho_shows' => '₦ 12,000',
                'processed_by' => $accountant->id,
                'processed_at' => now(),
                'receipt_uploaded' => true,
                'receipt_path' => 'receipts/VV-2024-003/receipt_003.jpg'
            ]
        ];

        foreach ($paymentRecords as $record) {
            PaymentRecord::create($record);
        }
    }

    private function createStrikeRecords($accountant)
    {
        if ($accountant->current_strikes > 0) {
            StrikeRecord::create([
                'accountant_id' => $accountant->id,
                'strike_number' => 1,
                'violation_type' => 'payment_mismatch',
                'violation_description' => 'Payment mismatch on Order VV-2024-002: IM=₦25,000, DA=₦20,000, Zoho=₦25,000',
                'penalty_amount' => 20000.00,
                'order_id' => 'VV-2024-002',
                'evidence' => [
                    'im_amount' => '₦25,000',
                    'da_amount' => '₦20,000',
                    'zoho_amount' => '₦25,000',
                    'discrepancy' => '₦5,000'
                ],
                'status' => 'active',
                'issued_date' => now()->subDays(3),
                'issued_by' => 1
            ]);
        }
    }

    private function createBonusTracking($accountant)
    {
        $weekStart = now()->startOfWeek();
        $weekEnd = now()->endOfWeek();

        BonusTracking::create([
            'accountant_id' => $accountant->id,
            'week_start_date' => $weekStart->toDateString(),
            'week_end_date' => $weekEnd->toDateString(),
            'goal_amount' => 10000.00,
            'criteria_met' => $accountant->current_strikes === 0 ? 4 : 2,
            'total_criteria' => 4,
            'payment_matching_accuracy' => 98.5,
            'escalation_discipline_score' => $accountant->current_strikes === 0 ? 100 : 0,
            'documentation_integrity_score' => 100,
            'bonus_log_accuracy' => 100,
            'bonus_amount' => $accountant->current_strikes === 0 ? 10000.00 : 5000.00,
            'bonus_status' => $accountant->current_strikes === 0 ? 'eligible' : 'not_eligible',
            'fc_approved' => $accountant->current_strikes === 0,
            'fc_approved_at' => $accountant->current_strikes === 0 ? now() : null
        ]);
    }

    private function createExpenseRequests($accountant)
    {
        $expenseRequests = [
            [
                'expense_id' => 'EXP-001',
                'requested_by' => $accountant->id,
                'department' => 'Financial Management',
                'expense_type' => 'Office Supplies',
                'amount' => 3500.00,
                'vendor_supplier' => 'OfficeMax Nigeria',
                'vendor_phone' => '+2348078901234',
                'description' => 'Purchase of printer cartridges and paper',
                'business_justification' => 'Essential office supplies for daily operations',
                'urgency_level' => 'normal',
                'approval_status' => 'approved',
                'fc_decision' => 'approved',
                'final_status' => 'auto_approve',
                'submitted_at' => now()->subDays(2),
                'approved_at' => now()->subDays(1)
            ],
            [
                'expense_id' => 'EXP-002',
                'requested_by' => $accountant->id,
                'department' => 'Financial Management',
                'expense_type' => 'Software License',
                'amount' => 15000.00,
                'vendor_supplier' => 'Microsoft Nigeria',
                'vendor_phone' => '+2348089012345',
                'description' => 'Annual Microsoft Office 365 license renewal',
                'business_justification' => 'Required for team productivity and document management',
                'urgency_level' => 'urgent',
                'approval_status' => 'pending',
                'fc_decision' => 'pending',
                'final_status' => 'escalation',
                'submitted_at' => now()->subHours(6)
            ]
        ];

        foreach ($expenseRequests as $request) {
            ExpenseRequest::create($request);
        }
    }

    private function createEscalationRequests($accountant)
    {
        $escalationRequests = [
            [
                'threshold_violation_id' => null,
                'escalation_type' => 'storekeeper_fee',
                'reference_id' => 'VV-2024-004',
                'location' => 'Lagos Warehouse',
                'amount_requested' => 2500.00,
                'threshold_limit' => 1000.00,
                'overage_amount' => 1500.00,
                'escalation_reason' => 'Special handling required for fragile items',
                'approval_required' => ['fc', 'gm'],
                'contact_info' => [
                    'storekeeper_phone' => '+2348090123456',
                    'transport_phone' => '+2348091234567'
                ],
                'status' => 'pending_approval',
                'priority' => 'medium',
                'expires_at' => now()->addDays(7),
                'submitted_by' => $accountant->id
            ],
            [
                'threshold_violation_id' => null,
                'escalation_type' => 'transport_cost',
                'reference_id' => 'VV-2024-005',
                'location' => 'Abuja Distribution Center',
                'amount_requested' => 8000.00,
                'threshold_limit' => 1500.00,
                'overage_amount' => 6500.00,
                'escalation_reason' => 'Emergency delivery to remote location',
                'approval_required' => ['fc', 'gm'],
                'contact_info' => [
                    'transport_phone' => '+2348092345678',
                    'storekeeper_phone' => '+2348093456789'
                ],
                'status' => 'approved',
                'priority' => 'high',
                'expires_at' => now()->addDays(7),
                'final_decision_at' => now()->subHours(12),
                'final_outcome' => 'approved',
                'submitted_by' => $accountant->id,
                'fc_reviewed_at' => now()->subHours(18),
                'gm_reviewed_at' => now()->subHours(12)
            ]
        ];

        foreach ($escalationRequests as $request) {
            EscalationRequest::create($request);
        }
    }

    private function createComplianceRecords($accountant)
    {
        SystemCompliance::create([
            'accountant_id' => $accountant->id,
            'compliance_date' => now()->toDateString(),
            'payment_matching_rate' => 98.5,
            'escalation_discipline_rate' => $accountant->current_strikes === 0 ? 100 : 0,
            'documentation_integrity_rate' => 100,
            'bonus_log_accuracy_rate' => 100,
            'overall_compliance_score' => $accountant->current_strikes === 0 ? 99.6 : 74.6,
            'system_health_score' => 95.2,
            'cache_hit_rate' => 87.3,
            'strikes_count' => $accountant->current_strikes,
            'penalties_total' => $accountant->total_penalties
        ]);
    }

    private function createDailyProgress($accountant)
    {
        $tasks = [
            [
                'task_type' => 'upload_proofs',
                'task_description' => 'Upload payment proofs for 15 orders',
                'amount' => 150000.00,
                'status' => 'completed',
                'completed_at' => now()->subHours(2)
            ],
            [
                'task_type' => 'process_payments',
                'task_description' => 'Process 3-way verification for 8 payments',
                'amount' => 85000.00,
                'status' => 'completed',
                'completed_at' => now()->subHours(4)
            ],
            [
                'task_type' => 'upload_receipt',
                'task_description' => 'Upload receipts for 5 pending payments',
                'amount' => 45000.00,
                'status' => 'in_progress'
            ],
            [
                'task_type' => 'escalation_review',
                'task_description' => 'Review 2 pending escalation requests',
                'amount' => 10500.00,
                'status' => 'pending'
            ]
        ];

        foreach ($tasks as $task) {
            DailyProgressTracking::create(array_merge($task, [
                'accountant_id' => $accountant->id,
                'task_date' => now()->toDateString()
            ]));
        }
    }
} 
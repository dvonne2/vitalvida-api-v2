<?php

namespace App\Services;

use App\Models\ThresholdViolation;
use App\Models\EscalationRequest;
use Illuminate\Support\Facades\Log;

class ThresholdValidationService
{
    // Hard-coded business thresholds (non-negotiable)
    private const THRESHOLDS = [
        'logistics' => [
            'cost_per_unit' => 100,
            'storekeeper_fee' => 1000,
            'transport_fare' => 1500,
            'total_for_120_items' => 12000
        ],
        'expenses' => [
            'fc_limit' => 5000,
            'gm_limit' => 10000,
            'ceo_required' => 10001
        ],
        'special_categories' => [
            'generator_fuel' => ['always_escalate' => true, 'dual_approval' => true],
            'equipment_repair' => ['threshold' => 7500, 'approvers' => ['gm', 'ceo']],
            'vehicle_maintenance' => ['threshold' => 15000, 'approvers' => ['fc', 'gm']]
        ]
    ];

    /**
     * Validate any cost against business thresholds
     * CRITICAL: This is called before ANY payment is processed
     */
    public function validateCost(array $costData): array
    {
        try {
            Log::info('Threshold validation started', [
                'cost_type' => $costData['type'],
                'amount' => $costData['amount'],
                'category' => $costData['category'] ?? null
            ]);

            $validationResult = match($costData['type']) {
                'logistics' => $this->validateLogisticsCost($costData),
                'expense' => $this->validateExpense($costData),
                'bonus' => $this->validateBonus($costData),
                default => ['valid' => false, 'error' => 'Unknown cost type']
            };

            // If validation fails, create violation record and escalation
            if (!$validationResult['valid']) {
                $this->handleThresholdViolation($costData, $validationResult);
            }

            Log::info('Threshold validation completed', [
                'cost_type' => $costData['type'],
                'amount' => $costData['amount'],
                'valid' => $validationResult['valid'],
                'requires_escalation' => $validationResult['requires_escalation'] ?? false
            ]);

            return $validationResult;

        } catch (\Exception $e) {
            Log::error('Threshold validation failed', [
                'cost_data' => $costData,
                'error' => $e->getMessage()
            ]);

            // Fail-safe: block payment if validation fails
            return [
                'valid' => false,
                'error' => 'Validation system error - payment blocked for safety',
                'requires_escalation' => true,
                'escalation_reason' => 'System validation failure'
            ];
        }
    }

    /**
     * Validate logistics costs against multiple thresholds
     */
    private function validateLogisticsCost(array $costData): array
    {
        $violations = [];
        $amount = $costData['amount'];
        $quantity = $costData['quantity'] ?? 1;
        $storekeeperFee = $costData['storekeeper_fee'] ?? 0;
        $transportFare = $costData['transport_fare'] ?? 0;

        // Check cost per unit
        $costPerUnit = $quantity > 0 ? $amount / $quantity : $amount;
        if ($costPerUnit > self::THRESHOLDS['logistics']['cost_per_unit']) {
            $violations[] = [
                'type' => 'cost_per_unit',
                'limit' => self::THRESHOLDS['logistics']['cost_per_unit'],
                'actual' => $costPerUnit,
                'overage' => $costPerUnit - self::THRESHOLDS['logistics']['cost_per_unit']
            ];
        }

        // Check storekeeper fee
        if ($storekeeperFee > self::THRESHOLDS['logistics']['storekeeper_fee']) {
            $violations[] = [
                'type' => 'storekeeper_fee',
                'limit' => self::THRESHOLDS['logistics']['storekeeper_fee'],
                'actual' => $storekeeperFee,
                'overage' => $storekeeperFee - self::THRESHOLDS['logistics']['storekeeper_fee']
            ];
        }

        // Check transport fare
        if ($transportFare > self::THRESHOLDS['logistics']['transport_fare']) {
            $violations[] = [
                'type' => 'transport_fare',
                'limit' => self::THRESHOLDS['logistics']['transport_fare'],
                'actual' => $transportFare,
                'overage' => $transportFare - self::THRESHOLDS['logistics']['transport_fare']
            ];
        }

        // Check total cost for standard package (120 items)
        if ($quantity == 120 && $amount > self::THRESHOLDS['logistics']['total_for_120_items']) {
            $violations[] = [
                'type' => 'total_cost_120_items',
                'limit' => self::THRESHOLDS['logistics']['total_for_120_items'],
                'actual' => $amount,
                'overage' => $amount - self::THRESHOLDS['logistics']['total_for_120_items']
            ];
        }

        if (!empty($violations)) {
            return [
                'valid' => false,
                'violations' => $violations,
                'requires_escalation' => true,
                'escalation_type' => 'logistics_threshold_violation',
                'total_overage' => array_sum(array_column($violations, 'overage')),
                'approval_required' => ['fc', 'gm'], // Dual approval required
                'payment_blocked' => true
            ];
        }

        return [
            'valid' => true,
            'message' => 'All logistics thresholds met',
            'payment_authorized' => true
        ];
    }

    /**
     * Validate general expenses against approval tiers
     */
    private function validateExpense(array $costData): array
    {
        $amount = $costData['amount'];
        $category = $costData['category'] ?? 'general';

        // Check special categories first
        if (isset(self::THRESHOLDS['special_categories'][$category])) {
            return $this->validateSpecialCategory($costData);
        }

        // Standard expense validation
        if ($amount <= self::THRESHOLDS['expenses']['fc_limit']) {
            return [
                'valid' => true,
                'approval_required' => ['fc'],
                'approval_tier' => 'fc_only',
                'message' => 'FC approval required'
            ];
        }

        if ($amount <= self::THRESHOLDS['expenses']['gm_limit']) {
            return [
                'valid' => true,
                'approval_required' => ['gm'],
                'approval_tier' => 'gm_only', 
                'message' => 'GM approval required'
            ];
        }

        // Above GM limit - requires CEO
        return [
            'valid' => true,
            'approval_required' => ['ceo'],
            'approval_tier' => 'ceo_required',
            'message' => 'CEO approval required for amount above ₦10,000',
            'requires_escalation' => true
        ];
    }

    /**
     * Validate special category expenses with custom rules
     */
    private function validateSpecialCategory(array $costData): array
    {
        $category = $costData['category'];
        $amount = $costData['amount'];
        $rules = self::THRESHOLDS['special_categories'][$category];

        // Generator fuel always requires dual approval
        if ($category === 'generator_fuel') {
            return [
                'valid' => true,
                'approval_required' => ['fc', 'gm'],
                'approval_tier' => 'dual_required',
                'message' => 'Generator fuel requires FC+GM dual approval',
                'requires_escalation' => true,
                'escalation_reason' => 'Special category requires dual approval'
            ];
        }

        // Equipment repair above threshold
        if ($category === 'equipment_repair' && $amount > $rules['threshold']) {
            return [
                'valid' => true,
                'approval_required' => $rules['approvers'],
                'approval_tier' => 'special_dual',
                'message' => "Equipment repair above ₦{$rules['threshold']} requires GM+CEO approval",
                'requires_escalation' => true,
                'threshold_exceeded' => $amount - $rules['threshold']
            ];
        }

        // Vehicle maintenance above threshold  
        if ($category === 'vehicle_maintenance' && $amount > $rules['threshold']) {
            return [
                'valid' => true,
                'approval_required' => $rules['approvers'],
                'approval_tier' => 'special_dual',
                'message' => "Vehicle maintenance above ₦{$rules['threshold']} requires FC+GM approval",
                'requires_escalation' => true,
                'threshold_exceeded' => $amount - $rules['threshold']
            ];
        }

        // Within special category limits
        return [
            'valid' => true,
            'approval_required' => ['fc'], // Default to FC for special categories
            'approval_tier' => 'special_normal',
            'message' => 'Special category within normal limits'
        ];
    }

    /**
     * Handle threshold violations - create records and escalations
     */
    private function handleThresholdViolation(array $costData, array $validationResult): void
    {
        // Create violation record for audit
        $violation = ThresholdViolation::create([
            'cost_type' => $costData['type'],
            'cost_category' => $costData['category'] ?? null,
            'amount' => $costData['amount'],
            'threshold_limit' => $this->getApplicableLimit($costData),
            'overage_amount' => $validationResult['total_overage'] ?? 0,
            'violation_details' => json_encode($validationResult['violations'] ?? []),
            'status' => 'blocked',
            'created_by' => $costData['user_id'] ?? null,
            'reference_id' => $costData['reference_id'] ?? null,
            'reference_type' => $costData['reference_type'] ?? null
        ]);

        // Create escalation request if required
        if ($validationResult['requires_escalation'] ?? false) {
            $this->createEscalationRequest($violation, $validationResult);
        }

        // Log violation for immediate attention
        Log::warning('Threshold violation detected and blocked', [
            'violation_id' => $violation->id,
            'cost_type' => $costData['type'],
            'amount' => $costData['amount'],
            'overage' => $validationResult['total_overage'] ?? 0,
            'escalation_required' => $validationResult['requires_escalation'] ?? false
        ]);
    }

    /**
     * Create escalation request for FC+GM approval
     */
    private function createEscalationRequest(ThresholdViolation $violation, array $validationResult): void
    {
        $escalation = EscalationRequest::create([
            'threshold_violation_id' => $violation->id,
            'escalation_type' => $validationResult['escalation_type'] ?? 'threshold_violation',
            'amount_requested' => $violation->amount,
            'threshold_limit' => $violation->threshold_limit,
            'overage_amount' => $violation->overage_amount,
            'approval_required' => json_encode($validationResult['approval_required'] ?? ['fc', 'gm']),
            'escalation_reason' => $validationResult['escalation_reason'] ?? 'Amount exceeds business threshold',
            'business_justification' => $validationResult['justification'] ?? null,
            'status' => 'pending_approval',
            'priority' => $this->calculateEscalationPriority($violation),
            'expires_at' => now()->addHours(48), // Auto-reject after 48 hours
            'created_by' => $violation->created_by
        ]);

        // Notify required approvers immediately
        $this->notifyApprovers($escalation, $validationResult['approval_required'] ?? ['fc', 'gm']);
    }

    /**
     * Calculate escalation priority based on amount and type
     */
    private function calculateEscalationPriority(ThresholdViolation $violation): string
    {
        $overagePercentage = $violation->threshold_limit > 0 ? 
            ($violation->overage_amount / $violation->threshold_limit) * 100 : 0;

        if ($overagePercentage > 100) {
            return 'critical'; // More than double the limit
        } elseif ($overagePercentage > 50) {
            return 'high'; // 50%+ over limit
        } elseif ($overagePercentage > 25) {
            return 'medium'; // 25%+ over limit
        } else {
            return 'normal'; // Under 25% over limit
        }
    }

    /**
     * Notify required approvers of escalation
     */
    private function notifyApprovers(EscalationRequest $escalation, array $requiredApprovers): void
    {
        foreach ($requiredApprovers as $approverRole) {
            $approver = \App\Models\User::where('role', $approverRole)
                ->where('is_active', true)
                ->first();

            if ($approver) {
                $approver->notify(new \App\Notifications\ThresholdViolationEscalation($escalation));
            }
        }
    }

    private function getApplicableLimit(array $costData): float
    {
        // Return the specific threshold that was violated
        return match($costData['type']) {
            'logistics' => self::THRESHOLDS['logistics']['cost_per_unit'], // Simplified
            'expense' => self::THRESHOLDS['expenses']['fc_limit'], // Simplified
            default => 0
        };
    }

    /**
     * Validate bonus payments
     */
    private function validateBonus(array $costData): array
    {
        $amount = $costData['amount'];

        // All bonuses require approval based on amount
        if ($amount <= self::THRESHOLDS['expenses']['fc_limit']) {
            return [
                'valid' => true,
                'approval_required' => ['fc'],
                'approval_tier' => 'fc_bonus',
                'message' => 'FC approval required for bonus'
            ];
        }

        if ($amount <= self::THRESHOLDS['expenses']['gm_limit']) {
            return [
                'valid' => true,
                'approval_required' => ['gm'],
                'approval_tier' => 'gm_bonus',
                'message' => 'GM approval required for bonus'
            ];
        }

        // High value bonus requires escalation
        return [
            'valid' => false,
            'requires_escalation' => true,
            'approval_required' => ['fc', 'gm'],
            'escalation_type' => 'high_value_bonus',
            'escalation_reason' => 'Bonus amount exceeds single approval limit',
            'total_overage' => $amount - self::THRESHOLDS['expenses']['gm_limit'],
            'payment_blocked' => true
        ];
    }
} 
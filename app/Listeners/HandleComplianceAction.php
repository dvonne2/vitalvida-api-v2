<?php

namespace App\Listeners;

use App\Events\ComplianceActionEvent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Services\IntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class HandleComplianceAction implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'compliance-sync';
    public $tries = 3;
    public $backoff = [15, 45, 90];

    private $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function handle(ComplianceActionEvent $event)
    {
        try {
            Log::info('Processing compliance action', [
                'agent_id' => $event->agent->id,
                'action_type' => $event->actionType,
                'severity' => $event->severity
            ]);

            // Update compliance in Role system
            $complianceResult = $this->integrationService->updateAgentCompliance(
                $event->agent, 
                $event->actionType
            );

            // Handle enforcement workflow
            $this->processEnforcementWorkflow($event);

            // Update compliance metrics
            $this->updateComplianceMetrics($event);

            // Send notifications if required
            $this->sendComplianceNotifications($event);

            Log::info('Compliance action processed successfully', [
                'agent_id' => $event->agent->id,
                'compliance_result' => $complianceResult
            ]);

        } catch (\Exception $e) {
            Log::error('Compliance action processing failed', [
                'agent_id' => $event->agent->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    private function processEnforcementWorkflow($event)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $event->agent->id)->first();
        
        if (!$roleAgent) {
            return;
        }

        switch ($event->actionType) {
            case 'suspend':
                $this->processSuspension($roleAgent, $event);
                break;
                
            case 'reduce_allocation':
                $this->processAllocationReduction($roleAgent, $event);
                break;
                
            case 'warning':
                $this->processWarning($roleAgent, $event);
                break;
                
            case 'mandatory_training':
                $this->processMandatoryTraining($roleAgent, $event);
                break;
        }
    }

    private function processSuspension($roleAgent, $event)
    {
        // Suspend all bins
        \App\Models\Bin::where('da_id', $roleAgent->id)->update([
            'bin_status' => 'suspended',
            'suspended_at' => now(),
            'suspension_reason' => $event->reason
        ]);

        // Create enforcement record
        \App\Models\EnforcementAction::create([
            'da_id' => $roleAgent->id,
            'action_type' => 'suspension',
            'severity' => $event->severity,
            'reason' => $event->reason,
            'executed_at' => now(),
            'executed_by' => 'system'
        ]);

        // Update compliance score
        $roleAgent->update([
            'compliance_score' => max(0, $roleAgent->compliance_score - 25),
            'status' => 'suspended',
            'suspended_at' => now()
        ]);
    }

    private function processAllocationReduction($roleAgent, $event)
    {
        // Reduce bin capacities
        \App\Models\Bin::where('da_id', $roleAgent->id)->update([
            'max_capacity' => \DB::raw('max_capacity * 0.75'), // 25% reduction
            'allocation_restricted' => true,
            'restriction_reason' => $event->reason,
            'restricted_at' => now()
        ]);

        $roleAgent->update([
            'compliance_score' => max(0, $roleAgent->compliance_score - 15),
            'allocation_restricted' => true
        ]);
    }

    private function processWarning($roleAgent, $event)
    {
        // Create violation record
        \App\Models\ComplianceViolation::create([
            'da_id' => $roleAgent->id,
            'violation_type' => 'warning',
            'description' => $event->reason,
            'severity' => $event->severity,
            'issued_at' => now(),
            'auto_generated' => true
        ]);

        $roleAgent->increment('violation_count');
        $roleAgent->update([
            'compliance_score' => max(0, $roleAgent->compliance_score - 5),
            'last_warning_at' => now()
        ]);
    }

    private function processMandatoryTraining($roleAgent, $event)
    {
        $roleAgent->update([
            'training_required' => true,
            'training_type' => $this->determineTrainingType($event->reason),
            'training_assigned_at' => now(),
            'compliance_score' => max(0, $roleAgent->compliance_score - 10)
        ]);

        // Create training assignment
        \App\Models\TrainingAssignment::create([
            'da_id' => $roleAgent->id,
            'training_type' => $this->determineTrainingType($event->reason),
            'reason' => $event->reason,
            'assigned_at' => now(),
            'due_date' => now()->addDays(7),
            'status' => 'assigned'
        ]);
    }

    private function updateComplianceMetrics($event)
    {
        $zone = $this->mapLocationToZone($event->agent->location);
        
        // Update zone compliance metrics
        $cacheKey = "zone_compliance_metrics:{$zone}";
        $metrics = \Cache::get($cacheKey, [
            'total_actions_today' => 0,
            'critical_actions' => 0,
            'agents_affected' => [],
            'last_updated' => now()
        ]);

        $metrics['total_actions_today']++;
        if ($event->severity === 'critical') {
            $metrics['critical_actions']++;
        }
        
        if (!in_array($event->agent->id, $metrics['agents_affected'])) {
            $metrics['agents_affected'][] = $event->agent->id;
        }
        
        $metrics['last_updated'] = now();

        \Cache::put($cacheKey, $metrics, 3600);
    }

    private function sendComplianceNotifications($event)
    {
        // Send notifications for critical actions
        if ($event->severity === 'critical') {
            $this->sendCriticalActionNotification($event);
        }

        // Send zone manager notifications
        if (in_array($event->actionType, ['suspend', 'reduce_allocation'])) {
            $this->sendZoneManagerNotification($event);
        }
    }

    private function sendCriticalActionNotification($event)
    {
        \App\Models\SystemNotification::create([
            'type' => 'critical_compliance_action',
            'title' => 'Critical Compliance Action Taken',
            'message' => "Agent {$event->agent->name} received {$event->actionType} action due to: {$event->reason}",
            'data' => json_encode([
                'agent_id' => $event->agent->id,
                'action_type' => $event->actionType,
                'severity' => $event->severity,
                'zone' => $this->mapLocationToZone($event->agent->location)
            ]),
            'priority' => 'high',
            'requires_acknowledgment' => true,
            'created_at' => now()
        ]);
    }

    private function sendZoneManagerNotification($event)
    {
        $zone = $this->mapLocationToZone($event->agent->location);
        
        \App\Models\ZoneNotification::create([
            'zone' => $zone,
            'type' => 'enforcement_action',
            'title' => "Enforcement Action in {$zone} Zone",
            'message' => "Agent {$event->agent->name} has been subject to {$event->actionType}",
            'data' => json_encode([
                'agent_id' => $event->agent->id,
                'action_type' => $event->actionType,
                'reason' => $event->reason
            ]),
            'created_at' => now()
        ]);
    }

    private function determineTrainingType($reason): string
    {
        if (stripos($reason, 'photo') !== false) {
            return 'photo_compliance_training';
        } elseif (stripos($reason, 'delivery') !== false) {
            return 'delivery_excellence_training';
        } elseif (stripos($reason, 'performance') !== false) {
            return 'performance_improvement_training';
        } else {
            return 'general_compliance_training';
        }
    }

    private function mapLocationToZone($location): string
    {
        $zoneMapping = [
            'Lagos' => 'Lagos',
            'Victoria Island' => 'Lagos', 
            'Ikeja' => 'Lagos',
            'Lekki' => 'Lagos',
            'Surulere' => 'Lagos',
            'Abuja' => 'Abuja',
            'FCT' => 'Abuja',
            'Kano' => 'Kano',
            'Port Harcourt' => 'Port Harcourt',
            'Rivers' => 'Port Harcourt'
        ];
        
        foreach ($zoneMapping as $keyword => $zone) {
            if (stripos($location, $keyword) !== false) {
                return $zone;
            }
        }
        
        return 'Lagos';
    }

    public function failed(\Throwable $exception)
    {
        Log::critical('Compliance action listener failed permanently', [
            'agent_id' => $this->event->agent->id ?? 'unknown',
            'action_type' => $this->event->actionType ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->tries
        ]);
    }
}

<?php

namespace App\Listeners;

use App\Events\AgentUpdatedEvent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Services\IntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncAgentToRoleSystem implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'high-priority-sync';
    public $tries = 3;
    public $backoff = [10, 30, 60];

    private $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function handle(AgentUpdatedEvent $event)
    {
        try {
            Log::info('Processing agent update sync', [
                'agent_id' => $event->agent->id,
                'update_type' => $event->updateType
            ]);

            // Sync agent data to Role system
            $syncResult = $this->integrationService->syncSingleAgent($event->agent);

            // Handle specific update types
            switch ($event->updateType) {
                case 'performance_update':
                    $this->handlePerformanceUpdate($event->agent, $event->previousData);
                    break;
                    
                case 'status_change':
                    $this->handleStatusChange($event->agent, $event->previousData);
                    break;
                    
                case 'location_change':
                    $this->handleLocationChange($event->agent, $event->previousData);
                    break;
                    
                case 'compliance_update':
                    $this->handleComplianceUpdate($event->agent, $event->previousData);
                    break;
            }

            Log::info('Agent sync completed successfully', [
                'agent_id' => $event->agent->id,
                'sync_result' => $syncResult
            ]);

        } catch (\Exception $e) {
            Log::error('Agent sync failed', [
                'agent_id' => $event->agent->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e; // Re-throw to trigger retry
        }
    }

    private function handlePerformanceUpdate($agent, $previousData)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $agent->id)->first();
        
        if ($roleAgent) {
            $roleAgent->update([
                'performance_score' => $agent->rating,
                'performance_updated_at' => now()
            ]);

            // Log performance change
            \App\Models\AgentActivityLog::create([
                'da_id' => $roleAgent->id,
                'action_type' => 'performance_sync',
                'action_details' => json_encode([
                    'previous_rating' => $previousData['rating'] ?? null,
                    'new_rating' => $agent->rating,
                    'synced_from' => 'vitalvida'
                ]),
                'performed_by' => 'system',
                'performed_at' => now()
            ]);
        }
    }

    private function handleStatusChange($agent, $previousData)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $agent->id)->first();
        
        if ($roleAgent) {
            $roleAgent->update([
                'status' => $this->mapVitalVidaStatusToRole($agent->status),
                'status_updated_at' => now()
            ]);

            // Handle special status changes
            if ($agent->status === 'Suspended') {
                $this->handleAgentSuspension($roleAgent, $agent);
            }
        }
    }

    private function handleLocationChange($agent, $previousData)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $agent->id)->first();
        
        if ($roleAgent) {
            $newZone = $this->mapLocationToZone($agent->location);
            $oldZone = isset($previousData['location']) ? 
                $this->mapLocationToZone($previousData['location']) : null;

            $roleAgent->update([
                'zone' => $newZone,
                'location_updated_at' => now()
            ]);

            // If zone changed, update bin assignments
            if ($oldZone !== $newZone) {
                $this->updateBinZoneAssignments($roleAgent, $newZone);
            }
        }
    }

    private function handleComplianceUpdate($agent, $previousData)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $agent->id)->first();
        
        if ($roleAgent && isset($agent->compliance_score)) {
            $roleAgent->update([
                'compliance_score' => $agent->compliance_score,
                'compliance_updated_at' => now()
            ]);

            // Check if compliance score dropped significantly
            $previousScore = $previousData['compliance_score'] ?? 100;
            if ($agent->compliance_score < $previousScore - 20) {
                $this->triggerComplianceAlert($roleAgent, $agent);
            }
        }
    }

    private function handleAgentSuspension($roleAgent, $vitalAgent)
    {
        // Suspend all active bins for this agent
        \App\Models\Bin::where('da_id', $roleAgent->id)
            ->where('bin_status', 'active')
            ->update([
                'bin_status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => $vitalAgent->suspension_reason ?? 'Agent suspended'
            ]);

        // Create compliance violation record
        \App\Models\ComplianceViolation::create([
            'da_id' => $roleAgent->id,
            'violation_type' => 'suspension',
            'description' => 'Agent suspended in VitalVida system',
            'severity' => 'critical',
            'issued_at' => now(),
            'auto_generated' => true
        ]);
    }

    private function updateBinZoneAssignments($roleAgent, $newZone)
    {
        \App\Models\Bin::where('da_id', $roleAgent->id)->update([
            'zone' => $newZone,
            'zone_updated_at' => now()
        ]);
    }

    private function triggerComplianceAlert($roleAgent, $vitalAgent)
    {
        // Create alert for significant compliance drop
        \App\Models\SystemAlert::create([
            'alert_type' => 'compliance_drop',
            'severity' => 'high',
            'title' => 'Significant Compliance Score Drop',
            'message' => "Agent {$vitalAgent->name} compliance score dropped to {$vitalAgent->compliance_score}%",
            'data' => json_encode([
                'agent_id' => $vitalAgent->id,
                'role_agent_id' => $roleAgent->id,
                'new_score' => $vitalAgent->compliance_score
            ]),
            'requires_action' => true,
            'created_at' => now()
        ]);
    }

    private function mapVitalVidaStatusToRole($vitalVidaStatus): string
    {
        return match($vitalVidaStatus) {
            'Active' => 'active',
            'Inactive' => 'inactive',
            'On Delivery' => 'on_delivery',
            'Break' => 'on_break',
            'Suspended' => 'suspended',
            'Training Required' => 'training',
            default => 'active'
        };
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
        Log::critical('Agent sync listener failed permanently', [
            'agent_id' => $this->event->agent->id ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->tries
        ]);
    }
}

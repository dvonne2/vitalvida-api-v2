<?php

namespace App\Events;

use App\Models\VitalVidaInventory\DeliveryAgent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ComplianceActionEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;
    public $actionType;
    public $severity;
    public $reason;

    public function __construct(DeliveryAgent $agent, string $actionType, string $severity, string $reason = '')
    {
        $this->agent = $agent;
        $this->actionType = $actionType;
        $this->severity = $severity;
        $this->reason = $reason;
    }

    public function broadcastOn()
    {
        return [
            new Channel('compliance-sync'),
            new PrivateChannel('agent.' . $this->agent->id),
            new Channel('zone.' . $this->mapLocationToZone($this->agent->location)),
            new Channel('enforcement-alerts')
        ];
    }

    public function broadcastAs()
    {
        return 'compliance.action';
    }

    public function broadcastWith()
    {
        return [
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'action_type' => $this->actionType,
            'severity' => $this->severity,
            'reason' => $this->reason,
            'zone' => $this->mapLocationToZone($this->agent->location),
            'compliance_score' => $this->agent->compliance_score ?? 100,
            'requires_role_sync' => true,
            'timestamp' => now(),
            'alert_level' => $this->getAlertLevel($this->severity)
        ];
    }

    private function getAlertLevel($severity): string
    {
        return match($severity) {
            'critical' => 'urgent',
            'high' => 'high',
            'medium' => 'medium',
            'low' => 'info',
            default => 'info'
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
}

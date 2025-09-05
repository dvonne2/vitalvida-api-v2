<?php

namespace App\Events;

use App\Models\VitalVidaInventory\DeliveryAgent;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AgentUpdatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $agent;
    public $updateType;
    public $previousData;

    public function __construct(DeliveryAgent $agent, string $updateType, array $previousData = [])
    {
        $this->agent = $agent;
        $this->updateType = $updateType;
        $this->previousData = $previousData;
    }

    public function broadcastOn()
    {
        return [
            new Channel('inventory-sync'),
            new PrivateChannel('agent.' . $this->agent->id)
        ];
    }

    public function broadcastAs()
    {
        return 'agent.updated';
    }

    public function broadcastWith()
    {
        return [
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'update_type' => $this->updateType,
            'zone' => $this->mapLocationToZone($this->agent->location),
            'status' => $this->agent->status,
            'rating' => $this->agent->rating,
            'previous_data' => $this->previousData,
            'timestamp' => now(),
            'requires_sync' => true
        ];
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
        
        return 'Lagos'; // Default zone
    }
}

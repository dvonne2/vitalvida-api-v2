<?php

namespace App\Events;

use App\Models\VitalVidaInventory\DeliveryAgent;
use App\Models\VitalVidaInventory\Product;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StockAllocatedEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $allocation;
    public $agent;
    public $product;

    public function __construct($allocation, DeliveryAgent $agent, Product $product)
    {
        $this->allocation = $allocation;
        $this->agent = $agent;
        $this->product = $product;
    }

    public function broadcastOn()
    {
        return [
            new Channel('inventory-sync'),
            new PrivateChannel('agent.' . $this->agent->id),
            new Channel('zone.' . $this->mapLocationToZone($this->agent->location))
        ];
    }

    public function broadcastAs()
    {
        return 'stock.allocated';
    }

    public function broadcastWith()
    {
        return [
            'allocation_id' => $this->allocation->id,
            'agent_id' => $this->agent->id,
            'agent_name' => $this->agent->name,
            'product_id' => $this->product->id,
            'product_name' => $this->product->name,
            'product_code' => $this->product->code,
            'quantity' => $this->allocation->quantity,
            'zone' => $this->mapLocationToZone($this->agent->location),
            'allocated_at' => $this->allocation->allocated_at,
            'requires_bin_sync' => true,
            'timestamp' => now()
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
        
        return 'Lagos';
    }
}

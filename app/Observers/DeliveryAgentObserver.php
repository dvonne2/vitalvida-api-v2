<?php

namespace App\Observers;

use App\Models\DeliveryAgent;
use App\Models\AgentActivityLog;
use App\Models\Bin;

class DeliveryAgentObserver
{
    /**
     * Handle the DeliveryAgent "created" event.
     */
    public function created(DeliveryAgent $deliveryAgent): void
    {
        // Create a bin for this delivery agent automatically
        Bin::create([
            'name' => "DA Bin - {$deliveryAgent->user->name}",
            'bin_code' => $deliveryAgent->da_code,
            'delivery_agent_id' => $deliveryAgent->id,
            'location' => $deliveryAgent->current_location,
            'state' => $deliveryAgent->state,
            'status' => 'active',
            'bin_type' => 'delivery_agent',
            'capacity' => 1000, // Default capacity
            'is_active' => true,
        ]);

        // Log the creation
        AgentActivityLog::logActivity(
            $deliveryAgent->id,
            'status_change',
            'Delivery agent profile created',
            ['status' => $deliveryAgent->status]
        );
    }

    /**
     * Handle the DeliveryAgent "updated" event.
     */
    public function updated(DeliveryAgent $deliveryAgent): void
    {
        $changes = $deliveryAgent->getChanges();
        
        // Log status changes
        if (isset($changes['status'])) {
            AgentActivityLog::logActivity(
                $deliveryAgent->id,
                'status_change',
                "Status changed from {$deliveryAgent->getOriginal('status')} to {$deliveryAgent->status}",
                $changes
            );
        }

        // Log location changes
        if (isset($changes['current_location'])) {
            AgentActivityLog::logActivity(
                $deliveryAgent->id,
                'location_update',
                "Location updated to {$deliveryAgent->current_location}",
                [
                    'old_location' => $deliveryAgent->getOriginal('current_location'),
                    'new_location' => $deliveryAgent->current_location
                ]
            );
        }

        // Update associated bin if location changed
        if (isset($changes['current_location']) || isset($changes['state'])) {
            $deliveryAgent->bins()->update([
                'location' => $deliveryAgent->current_location,
                'state' => $deliveryAgent->state,
            ]);
        }

        // Auto-suspend if too many strikes
        if (isset($changes['strikes_count']) && $deliveryAgent->strikes_count >= 5) {
            $deliveryAgent->update([
                'status' => 'suspended',
                'suspended_at' => now(),
                'suspension_reason' => 'Auto-suspended: Maximum strikes reached'
            ]);
        }
    }

    /**
     * Handle the DeliveryAgent "deleting" event.
     */
    public function deleting(DeliveryAgent $deliveryAgent): void
    {
        // Log the deletion
        AgentActivityLog::logActivity(
            $deliveryAgent->id,
            'status_change',
            'Delivery agent profile deleted',
            ['reason' => 'Account deletion']
        );

        // Soft delete associated bins
        $deliveryAgent->bins()->delete();
    }
}

<?php

namespace App\Observers;

use App\Models\Delivery;
use App\Models\AgentActivityLog;

class DeliveryObserver
{
    /**
     * Handle the Delivery "created" event.
     */
    public function created(Delivery $delivery): void
    {
        // Log delivery assignment
        AgentActivityLog::logActivity(
            $delivery->delivery_agent_id,
            AgentActivityLog::ACTIVITY_ORDER_ACCEPTANCE,
            "New delivery assigned: {$delivery->delivery_code}",
            [
                'delivery_id' => $delivery->id,
                'recipient' => $delivery->recipient_name,
                'location' => $delivery->delivery_location
            ],
            $delivery->order_id
        );

        // Update agent's delivery count
        $delivery->deliveryAgent->increment('total_deliveries');
    }

    /**
     * Handle the Delivery "updated" event.
     */
    public function updated(Delivery $delivery): void
    {
        $changes = $delivery->getChanges();
        
        if (isset($changes['status'])) {
            $oldStatus = $delivery->getOriginal('status');
            $newStatus = $delivery->status;
            
            // Log status change
            AgentActivityLog::logActivity(
                $delivery->delivery_agent_id,
                $this->getActivityTypeForStatus($newStatus),
                "Delivery {$delivery->delivery_code} status changed from {$oldStatus} to {$newStatus}",
                [
                    'delivery_id' => $delivery->id,
                    'old_status' => $oldStatus,
                    'new_status' => $newStatus,
                    'timestamp' => now()
                ],
                $delivery->order_id
            );

            // Update agent stats based on status
            $agent = $delivery->deliveryAgent;
            
            if ($newStatus === Delivery::STATUS_DELIVERED) {
                $agent->increment('successful_deliveries');
                
                // Calculate delivery time if we have pickup time
                if ($delivery->picked_up_at && $delivery->delivered_at) {
                    $deliveryTime = $delivery->picked_up_at->diffInMinutes($delivery->delivered_at);
                    $delivery->update(['delivery_time_minutes' => $deliveryTime]);
                    
                    // Update agent's average delivery time
                    $this->updateAverageDeliveryTime($agent);
                }
                
            } elseif (in_array($newStatus, [Delivery::STATUS_FAILED, Delivery::STATUS_RETURNED])) {
                $agent->increment('returns_count');
            }

            // Update agent rating if customer rated
            if (isset($changes['customer_rating']) && $delivery->customer_rating) {
                $this->updateAgentRating($agent);
            }
        }
    }

    /**
     * Get activity type based on delivery status
     */
    private function getActivityTypeForStatus($status)
    {
        switch ($status) {
            case Delivery::STATUS_PICKED_UP:
                return AgentActivityLog::ACTIVITY_PICKUP;
            case Delivery::STATUS_DELIVERED:
                return AgentActivityLog::ACTIVITY_DELIVERY;
            default:
                return AgentActivityLog::ACTIVITY_STATUS_CHANGE;
        }
    }

    /**
     * Update agent's average delivery time
     */
    private function updateAverageDeliveryTime($agent)
    {
        $avgTime = $agent->deliveries()
            ->where('status', Delivery::STATUS_DELIVERED)
            ->whereNotNull('delivery_time_minutes')
            ->avg('delivery_time_minutes');
            
        $agent->update(['average_delivery_time' => $avgTime]);
    }

    /**
     * Update agent's rating based on all customer ratings
     */
    private function updateAgentRating($agent)
    {
        $avgRating = $agent->deliveries()
            ->whereNotNull('customer_rating')
            ->avg('customer_rating');
            
        $agent->update(['rating' => round($avgRating, 2)]);
    }
}

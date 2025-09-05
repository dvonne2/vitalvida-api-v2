<?php

namespace App\Services;

use App\Models\Order;
use App\Models\DeliveryAgent;
use App\Models\TelesalesAgent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Exception;

class OrderAssignmentService
{
    public function assignOrderToAgent($order, $deliveryAgent)
    {
        DB::beginTransaction();
        
        try {
            // Update order
            $order->delivery_agent_id = $deliveryAgent->id;
            $order->delivery_status = 'assigned';
            $order->assigned_at = now();
            $order->save();
            
            // Update agent's active orders count
            $deliveryAgent->increment('active_orders_count');
            
            // Deduct stock from agent's inventory
            $this->deductStock($deliveryAgent, $order->product_details);
            
            DB::commit();
            
            Log::info("Order {$order->id} assigned to DA {$deliveryAgent->id}");
            
            return true;
            
        } catch (Exception $e) {
            DB::rollback();
            Log::error("Failed to assign order {$order->id}: " . $e->getMessage());
            return false;
        }
    }
    
    public function findAvailableAgent($order)
    {
        // Find available delivery agents in customer's location
        $availableAgents = DeliveryAgent::where('status', 'active')
            ->where('location', 'like', '%' . $order->customer_location . '%')
            ->get();
            
        foreach ($availableAgents as $agent) {
            if ($this->hasRequiredStock($agent, $order->product_details)) {
                return $agent;
            }
        }
        
        return null;
    }
    
    public function autoAssignOrder($order)
    {
        $availableAgent = $this->findAvailableAgent($order);
        
        if ($availableAgent) {
            return $this->assignOrderToAgent($order, $availableAgent);
        }
        
        Log::warning("No available delivery agent found for order {$order->id}");
        return false;
    }
    
    private function hasRequiredStock($agent, $productDetails)
    {
        $currentStock = $agent->current_stock;
        
        foreach ($productDetails as $item => $requiredQty) {
            if (!isset($currentStock[$item]) || $currentStock[$item] < $requiredQty) {
                return false;
            }
        }
        
        return true;
    }
    
    private function deductStock($agent, $productDetails)
    {
        $currentStock = $agent->current_stock;
        
        foreach ($productDetails as $item => $qty) {
            if (isset($currentStock[$item])) {
                $currentStock[$item] -= $qty;
            }
        }
        
        $agent->current_stock = $currentStock;
        $agent->save();
    }
} 
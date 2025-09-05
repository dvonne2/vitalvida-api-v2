<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Customer;
use App\Models\Notification;

class RecoveryService
{
    public function processPaymentProof(Order $order)
    {
        $customer = $order->customer;
        
        // Update order
        $order->update([
            'is_prepaid' => true,
            'payment_received_at' => now(),
            'can_auto_progress' => true,
            'ai_restrictions' => null
        ]);
        
        // Increment recovery count
        $customer->increment('recovery_orders');
        
        // Process through Kanban AI
        $kanbanService = new KanbanMovementService();
        $kanbanService->processOrder($order);
        
        // Check if customer can restore POD
        if ($customer->canRestorePayOnDelivery()) {
            $customer->update(['requires_prepayment' => false]);
            
            // Create notification
            Notification::create([
                'type' => 'performance_alert',
                'priority' => 'medium',
                'title' => 'Customer Recovery Complete',
                'message' => "{$customer->name} has completed recovery and can now use Pay on Delivery",
                'data' => ['customer_id' => $customer->id, 'order_id' => $order->id]
            ]);
        }
        
        // Create payment notification
        Notification::create([
            'type' => 'payment_received',
            'priority' => 'high',
            'title' => 'RISK³ Payment Received',
            'message' => "Payment confirmed for {$customer->name} - ₦" . number_format($order->amount),
            'data' => ['customer_id' => $customer->id, 'order_id' => $order->id]
        ]);
        
        return $order;
    }
} 
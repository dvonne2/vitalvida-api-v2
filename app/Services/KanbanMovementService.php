<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Customer;
use App\Models\AccountManager;
use App\Models\DeliveryAgent;
use App\Models\KanbanMovement;
use App\Models\Notification;
use App\Models\WhatsappMessage;
use Illuminate\Support\Facades\Log;

class KanbanMovementService
{
    public function processOrder(Order $order)
    {
        // Check current status and determine next action
        switch ($order->status) {
            case 'received':
                return $this->handleReceivedOrder($order);
            
            case 'assigned_to_am':
                return $this->handleAMAssignedOrder($order);
            
            case 'assigned_to_da':
                return $this->handleDAAssignedOrder($order);
            
            case 'payment_received':
                return $this->handlePaymentReceived($order);
        }
    }

    private function handleReceivedOrder(Order $order)
    {
        $customer = $order->customer;
        
        // RISK³ Customers - Block until prepayment
        if ($customer->shouldRequirePrepayment() && !$order->is_prepaid) {
            return $this->blockForPrepayment($order);
        }
        
        // High-value RISK² orders - Require manual review
        if ($customer->risk_level === 'RISK2' && $order->amount > 50000) {
            return $this->blockForManualReview($order, 'High-value RISK² customer');
        }
        
        // Auto-assign to Account Manager
        return $this->autoAssignToAM($order);
    }

    private function handleAMAssignedOrder(Order $order)
    {
        $customer = $order->customer;
        
        // RISK² customers require verification before delivery assignment
        if ($customer->risk_level === 'RISK2' && !$order->verified_at) {
            return $this->requireVerification($order);
        }
        
        // RISK¹ customers - conditional progression after 2 hours
        if ($customer->risk_level === 'RISK1') {
            $hoursAssigned = $order->assigned_at->diffInHours(now());
            if ($hoursAssigned < 2) {
                return $this->conditionalHold($order, 'RISK¹ customer - awaiting 2-hour confirmation window');
            }
        }
        
        // Auto-assign to Delivery Agent
        return $this->autoAssignToDA($order);
    }

    private function handleDAAssignedOrder(Order $order)
    {
        $customer = $order->customer;
        
        // Check delivery confirmation requirements
        if ($this->requiresDeliveryConfirmation($order)) {
            return $this->awaitDeliveryConfirmation($order);
        }
        
        // Auto-progress to payment received (for prepaid orders)
        if ($order->is_prepaid) {
            return $this->autoProgressToPaymentReceived($order);
        }
        
        // For POD orders, wait for actual delivery and payment
        return $this->awaitPaymentOnDelivery($order);
    }

    private function handlePaymentReceived(Order $order)
    {
        // Mark as completed
        return $this->markCompleted($order);
    }

    // AI Movement Conditions
    private function blockForPrepayment(Order $order)
    {
        $order->update([
            'can_auto_progress' => false,
            'ai_restrictions' => [
                'type' => 'prepayment_required',
                'reason' => 'RISK³ customer requires prepayment before processing',
                'action_required' => 'Payment proof upload'
            ]
        ]);

        $this->createMovement($order, 'received', 'received', 'ai_blocked', 
            'RISK³ customer blocked for prepayment requirement');

        // Send prepayment request
        $this->sendPrepaymentRequest($order);

        return false;
    }

    private function blockForManualReview(Order $order, $reason)
    {
        $order->update([
            'status' => 'blocked_for_review',
            'can_auto_progress' => false,
            'ai_restrictions' => [
                'type' => 'manual_review_required',
                'reason' => $reason,
                'action_required' => 'Manager approval'
            ]
        ]);

        $this->createMovement($order, 'received', 'blocked_for_review', 'ai_blocked', $reason);

        // Create notification for managers
        Notification::create([
            'type' => 'manual_review_required',
            'priority' => 'high',
            'title' => 'Order Blocked for Manual Review',
            'message' => "Order {$order->order_id} requires manual review: {$reason}",
            'data' => ['order_id' => $order->id, 'reason' => $reason]
        ]);

        return false;
    }

    private function requireVerification(Order $order)
    {
        $order->update([
            'verification_required' => true,
            'ai_restrictions' => [
                'type' => 'verification_required',
                'reason' => 'RISK² customer requires phone verification',
                'action_required' => 'Account Manager verification call'
            ]
        ]);

        return false;
    }

    private function conditionalHold(Order $order, $reason)
    {
        $order->update([
            'ai_restrictions' => [
                'type' => 'conditional_hold',
                'reason' => $reason,
                'release_time' => $order->assigned_at->addHours(2)
            ]
        ]);

        return false;
    }

    private function autoAssignToAM(Order $order)
    {
        $assignmentService = new AIAssignmentService();
        $assigned = $assignmentService->assignOrder($order);

        if ($assigned) {
            $this->createMovement($order, 'received', 'assigned_to_am', 'ai_auto',
                'AI auto-assigned to Account Manager based on risk level and specialization');
            return true;
        }

        return false;
    }

    private function autoAssignToDA(Order $order)
    {
        $deliveryAgent = $this->selectDeliveryAgent($order);
        
        if (!$deliveryAgent) {
            $this->conditionalHold($order, 'No delivery agents available in customer zone');
            return false;
        }

        $order->update([
            'delivery_agent_id' => $deliveryAgent->id,
            'status' => 'assigned_to_da'
        ]);

        $this->createMovement($order, 'assigned_to_am', 'assigned_to_da', 'ai_auto',
            "AI assigned to {$deliveryAgent->name} based on zone coverage and availability");

        return true;
    }

    private function autoProgressToPaymentReceived(Order $order)
    {
        $order->update([
            'status' => 'payment_received',
            'payment_received_at' => now()
        ]);

        $this->createMovement($order, 'assigned_to_da', 'payment_received', 'ai_auto',
            'Prepaid order automatically moved to payment received');

        return true;
    }

    private function markCompleted(Order $order)
    {
        $order->update(['status' => 'completed']);
        
        // Update customer stats
        $customer = $order->customer;
        $customer->increment('completed_orders');
        $customer->increment('lifetime_value', $order->amount);
        $customer->update(['last_order_date' => now()]);

        $this->createMovement($order, 'payment_received', 'completed', 'ai_auto',
            'Order automatically completed');

        return true;
    }

    // AI Decision Helpers
    private function selectDeliveryAgent(Order $order)
    {
        $customerLocation = $order->customer->location;
        
        // Find agents covering customer's zone
        $availableAgents = DeliveryAgent::available()
            ->where('zone', 'like', '%' . $customerLocation . '%')
            ->orderBy('current_deliveries')
            ->orderBy('rating', 'desc')
            ->get();

        return $availableAgents->first();
    }

    private function requiresDeliveryConfirmation(Order $order)
    {
        // High-value orders or RISK customers require confirmation
        return $order->amount > 75000 || 
               in_array($order->customer->risk_level, ['RISK2', 'RISK3']);
    }

    private function createMovement(Order $order, $from, $to, $type, $reason)
    {
        KanbanMovement::create([
            'order_id' => $order->id,
            'from_status' => $from,
            'to_status' => $to,
            'movement_type' => $type,
            'movement_reason' => $reason,
            'moved_at' => now()
        ]);
    }

    // Check if order can progress (called by frontend)
    public function canProgressOrder(Order $order)
    {
        if (!$order->can_auto_progress) {
            return [
                'can_progress' => false,
                'restrictions' => $order->getAIRestrictions(),
                'next_action' => $this->getRequiredAction($order)
            ];
        }

        // Check time-based conditions
        if ($this->hasTimeBasedRestrictions($order)) {
            return [
                'can_progress' => false,
                'restrictions' => $order->getAIRestrictions(),
                'release_time' => $order->ai_restrictions['release_time'] ?? null
            ];
        }

        return ['can_progress' => true];
    }

    private function hasTimeBasedRestrictions(Order $order)
    {
        $restrictions = $order->getAIRestrictions();
        
        if ($restrictions['type'] === 'conditional_hold' && isset($restrictions['release_time'])) {
            return now() < $restrictions['release_time'];
        }

        return false;
    }

    private function getRequiredAction(Order $order)
    {
        $restrictions = $order->getAIRestrictions();
        return $restrictions['action_required'] ?? 'Contact system administrator';
    }

    // Override AI restrictions (manual approval)
    public function overrideRestrictions(Order $order, $approvedBy, $reason)
    {
        $order->update([
            'can_auto_progress' => true,
            'ai_restrictions' => null
        ]);

        $this->createMovement($order, $order->status, $order->status, 'manual_override',
            "Manual override by user {$approvedBy}: {$reason}");

        // Process the order now that restrictions are lifted
        return $this->processOrder($order);
    }

    private function sendPrepaymentRequest(Order $order)
    {
        $message = "Hello {$order->customer->name}, due to previous order cancellations, we now require payment before delivery. Please transfer ₦" . number_format($order->amount) . " to:\n\nAccount: Vital Vida Limited\nAccount Number: 1234567890\nBank: GTBank\n\nSend payment proof to proceed with your order.";
        
        WhatsappMessage::create([
            'order_id' => $order->id,
            'type' => 'prepayment_request',
            'message' => $message,
            'status' => 'sent',
            'sent_at' => now()
        ]);
    }

    private function awaitDeliveryConfirmation(Order $order)
    {
        // This would typically wait for delivery confirmation
        // For now, we'll just return false to indicate it's waiting
        return false;
    }

    private function awaitPaymentOnDelivery(Order $order)
    {
        // This would typically wait for actual delivery and payment
        // For now, we'll just return false to indicate it's waiting
        return false;
    }
} 
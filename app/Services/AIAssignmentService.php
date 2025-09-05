<?php

namespace App\Services;

use App\Models\Order;
use App\Models\Customer;
use App\Models\AccountManager;
use App\Models\DeliveryAgent;
use App\Models\WhatsappMessage;
use App\Models\Notification;

class AIAssignmentService
{
    public function assignOrder(Order $order)
    {
        $customer = $order->customer;
        
        // Calculate risk and update customer
        $this->updateCustomerRisk($customer);
        
        // Assign Account Manager
        $accountManager = $this->selectAccountManager($order);
        
        if (!$accountManager) {
            return false;
        }

        $order->update([
            'account_manager_id' => $accountManager->id,
            'assigned_at' => now(),
            'status' => 'assigned_to_am',
            'assignment_reasoning' => $this->getAssignmentReasoning($accountManager, $order)
        ]);
        
        // Increment manager load
        $accountManager->incrementLoad();
        
        // Send WhatsApp message
        $this->sendWhatsAppMessage($order);
        
        // Create notification if assignment took too long
        if ($this->getAssignmentTime($order) > 30) {
            $this->createAssignmentTimeoutNotification($order);
        }
        
        return true;
    }
    
    private function updateCustomerRisk(Customer $customer)
    {
        $riskLevel = $customer->calculateRiskLevel();
        $customer->update([
            'risk_level' => $riskLevel,
            'requires_prepayment' => $customer->shouldRequirePrepayment()
        ]);
    }
    
    private function handleRisk3Customer(Order $order)
    {
        $order->update([
            'requires_prepayment' => true,
            'status' => 'received' // Keep in received until payment
        ]);
        
        // Send prepayment request
        $this->sendPrepaymentRequest($order);
        
        return $order;
    }
    
    private function selectAccountManager(Order $order)
    {
        $customer = $order->customer;
        
        // High-value orders (>50k) - senior managers only
        if ($order->amount > 50000) {
            return AccountManager::available()
                ->where('rating', '>=', 4.8)
                ->orderBy('current_load')
                ->first();
        }
        
        // RISK2 customers - experienced managers
        if ($customer->risk_level === 'RISK2') {
            return AccountManager::available()
                ->where('rating', '>=', 4.5)
                ->orderBy('current_load')
                ->first();
        }
        
        // Regional specialists
        if ($customer->location) {
            $regionalManager = AccountManager::available()
                ->where('region', 'like', '%' . $customer->location . '%')
                ->orderBy('rating', 'desc')
                ->first();
            
            if ($regionalManager) return $regionalManager;
        }
        
        // Default: best available manager
        return AccountManager::available()
            ->orderBy('rating', 'desc')
            ->orderBy('current_load')
            ->first();
    }
    
    private function getAssignmentReasoning(AccountManager $manager, Order $order)
    {
        $reasons = [];
        
        if ($order->amount > 50000) {
            $reasons[] = 'High-value order';
        }
        
        if ($order->customer->risk_level !== 'TRUSTED') {
            $reasons[] = $order->customer->risk_level . ' customer handling';
        }
        
        if (str_contains($manager->region, $order->customer->location)) {
            $reasons[] = $order->customer->location . ' specialist';
        }
        
        if ($manager->rating >= 4.8) {
            $reasons[] = 'Senior account manager';
        }
        
        return [
            'primary_reason' => implode(' + ', $reasons) ?: 'Available + best match',
            'manager_rating' => $manager->rating,
            'current_load' => $manager->current_load
        ];
    }
    
    private function sendWhatsAppMessage(Order $order)
    {
        $messageType = $this->getMessageType($order);
        $message = $this->generateMessage($order, $messageType);
        
        WhatsappMessage::create([
            'order_id' => $order->id,
            'type' => $messageType,
            'message' => $message,
            'status' => 'sent',
            'sent_at' => now()
        ]);
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
    
    private function getMessageType(Order $order)
    {
        switch ($order->customer->risk_level) {
            case 'TRUSTED': return 'welcome';
            case 'RISK1': return 'risk_reminder';
            case 'RISK2': return 'verification';
            default: return 'welcome';
        }
    }
    
    private function generateMessage(Order $order, $type)
    {
        $name = $order->customer->name;
        $product = $order->product_name;
        
        switch ($type) {
            case 'welcome':
                return "Hello {$name}! Thank you for your order of {$product}. We're processing it now and will contact you shortly for delivery arrangements.";
            
            case 'risk_reminder':
                return "Hello {$name}! Thank you for your order of {$product}. Please note this order is important to us. We'll contact you shortly for delivery confirmation.";
            
            case 'verification':
                return "Hello {$name}! Thank you for your order of {$product}. Due to previous order history, we'll need to verify your order details before processing. Our team will contact you shortly.";
            
            default:
                return "Hello {$name}! Thank you for your order of {$product}.";
        }
    }
    
    private function getAssignmentTime(Order $order): int
    {
        if (!$order->assigned_at) return 0;
        return $order->created_at->diffInSeconds($order->assigned_at);
    }
    
    private function createAssignmentTimeoutNotification(Order $order)
    {
        Notification::create([
            'type' => 'assignment_timeout',
            'priority' => 'medium',
            'title' => 'Order Assignment Timeout',
            'message' => "Order {$order->order_id} took longer than expected to assign",
            'data' => [
                'order_id' => $order->id,
                'assignment_time' => $this->getAssignmentTime($order)
            ]
        ]);
    }
} 
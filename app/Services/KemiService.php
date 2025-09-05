<?php

namespace App\Services;

use App\Models\Order;
use App\Models\TelesalesAgent;
use App\Models\DeliveryAgent;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class KemiService
{
    public function generateResponse($userMessage, $order = null)
    {
        $message = strtolower($userMessage);
        
        // Simple rule-based responses
        if (str_contains($message, 'assign') || str_contains($message, 'delivery agent')) {
            return "I'll help you find the best delivery agent. Let me check available agents with the required stock.";
        }
        
        if (str_contains($message, 'customer') && str_contains($message, 'call')) {
            return "For customer calls, I recommend mentioning our 100% natural ingredients and 30-day results guarantee. Would you like me to suggest a call script?";
        }
        
        if (str_contains($message, 'help') || str_contains($message, 'assist')) {
            return "I'm here to help! I can assist with:\n• Finding delivery agents\n• Call scripts and objection handling\n• Order status updates\n• Customer information\n\nWhat do you need help with?";
        }
        
        if (str_contains($message, 'performance') || str_contains($message, 'bonus')) {
            return "I can help you track your weekly performance. You need 20+ delivered orders with 70%+ delivery rate to qualify for bonuses.";
        }
        
        return "I'm Kemi, your virtual team lead! How can I assist you today?";
    }
    
    public function getUrgentOrders($agentId)
    {
        return Order::where('telesales_agent_id', $agentId)
            ->whereIn('call_status', ['pending', 'callback'])
            ->where('created_at', '<', now()->subMinutes(15))
            ->count();
    }
    
    public function getUrgentAlerts($agentId)
    {
        $urgentOrders = Order::where('telesales_agent_id', $agentId)
            ->whereIn('call_status', ['pending', 'callback'])
            ->where('created_at', '<', now()->subMinutes(15))
            ->get();
            
        $alerts = [];
        
        foreach ($urgentOrders as $order) {
            $alerts[] = [
                'type' => 'urgent_order',
                'message' => "Order #{$order->order_number} needs attention",
                'order_id' => $order->id,
                'customer_name' => $order->customer_name,
                'time_waiting' => $order->created_at->diffInMinutes(now())
            ];
        }
        
        return $alerts;
    }
    
    public function generateOrderRecommendation($order)
    {
        $recommendations = [];
        
        // Check customer history
        $customerOrders = Order::where('customer_phone', $order->customer_phone)
            ->where('id', '!=', $order->id)
            ->get();
            
        if ($customerOrders->where('delivery_status', 'delivered')->count() > 0) {
            $recommendations[] = "✅ Returning customer - high conversion probability";
        }
        
        // Check available delivery agents
        $availableAgents = DeliveryAgent::where('status', 'active')
            ->where('location', 'like', '%' . $order->customer_location . '%')
            ->count();
            
        if ($availableAgents === 0) {
            $recommendations[] = "🚨 No delivery agents available in this area";
        } else {
            $recommendations[] = "✅ {$availableAgents} delivery agents available in this area";
        }
        
        // Check stock availability
        $stockRecommendations = $this->checkStockAvailability($order);
        $recommendations = array_merge($recommendations, $stockRecommendations);
        
        return $recommendations;
    }
    
    private function checkStockAvailability($order)
    {
        $recommendations = [];
        $productDetails = $order->product_details ?? [];
        
        foreach ($productDetails as $item => $qty) {
            $totalStock = DeliveryAgent::where('status', 'active')
                ->get()
                ->sum(function($agent) use ($item) {
                    return $agent->current_stock[$item] ?? 0;
                });
                
            if ($totalStock < $qty) {
                $recommendations[] = "⚠️ Low stock alert: Only {$totalStock} {$item} available (needs {$qty})";
            }
        }
        
        return $recommendations;
    }
    
    public function getCallScript($order)
    {
        $scripts = [
            'greeting' => "Hello! This is [Your Name] from VitalVida. I'm calling about your recent order for our natural hair care products.",
            'benefits' => "Our products are 100% natural and have helped thousands of customers achieve healthier hair in just 30 days.",
            'guarantee' => "We offer a 30-day money-back guarantee, so you have nothing to lose.",
            'closing' => "Would you like me to confirm your delivery details and answer any questions you might have?"
        ];
        
        return $scripts;
    }
    
    public function getObjectionHandling($objection)
    {
        $objection = strtolower($objection);
        
        $responses = [
            'too expensive' => "I understand your concern about price. Our products are premium quality and last 2-3 months. Plus, we offer installment payments.",
            'not interested' => "I completely understand. Would you be interested in a free sample to try our products first?",
            'call back later' => "Of course! When would be the best time to call you back?",
            'need to think' => "Absolutely! Take your time. I'll follow up in a few days. Our 30-day guarantee means you can try risk-free."
        ];
        
        foreach ($responses as $key => $response) {
            if (str_contains($objection, $key)) {
                return $response;
            }
        }
        
        return "I understand your concern. Let me know if you have any questions about our products or delivery process.";
    }
} 
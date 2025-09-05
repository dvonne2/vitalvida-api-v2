<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\TelesalesAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Carbon\Carbon;

class KemiController extends Controller
{
    // POST /api/kemi/chat/{orderId}
    public function handleChat($orderId, Request $request)
    {
        $request->validate([
            'message' => 'required|string',
            'type' => 'required|in:user,kemi,system'
        ]);
        
        $order = Order::findOrFail($orderId);
        $chatLog = $order->kemi_chat_log ?? [];
        
        $chatEntry = [
            'timestamp' => now()->toISOString(),
            'type' => $request->type,
            'message' => $request->message,
            'user_id' => auth()->id()
        ];
        
        $chatLog[] = $chatEntry;
        $order->kemi_chat_log = $chatLog;
        $order->save();
        
        // If this is a user message, generate Kemi response
        if ($request->type === 'user') {
            $kemiResponse = $this->generateKemiResponse($request->message, $order);
            $this->handleChat($orderId, new Request([
                'message' => $kemiResponse,
                'type' => 'kemi'
            ]));
        }
        
        return response()->json([
            'status' => 'success',
            'chat_log' => $order->fresh()->kemi_chat_log
        ]);
    }
    
    // GET /api/kemi/urgent-orders/{agentId}
    public function getUrgentOrders($agentId)
    {
        $urgentOrders = Order::where('telesales_agent_id', $agentId)
            ->whereIn('call_status', ['pending', 'callback'])
            ->where('created_at', '<', now()->subMinutes(15))
            ->with('deliveryAgent')
            ->get()
            ->map(function($order) {
                return [
                    'id' => $order->id,
                    'order_number' => $order->order_number,
                    'customer_name' => $order->customer_name,
                    'customer_phone' => $order->customer_phone,
                    'call_status' => $order->call_status,
                    'created_at' => $order->created_at->format('Y-m-d H:i:s'),
                    'minutes_old' => $order->created_at->diffInMinutes(now()),
                    'delivery_agent' => $order->deliveryAgent ? [
                        'id' => $order->deliveryAgent->id,
                        'name' => $order->deliveryAgent->name ?? $order->deliveryAgent->user->name ?? 'Unknown'
                    ] : null
                ];
            });
            
        return response()->json([
            'status' => 'success',
            'data' => $urgentOrders,
            'total_urgent' => $urgentOrders->count()
        ]);
    }

    // GET /api/kemi/suggestions/{orderId}
    public function getSuggestions($orderId)
    {
        $order = Order::findOrFail($orderId);
        
        $suggestions = $this->generateSuggestions($order);
        
        return response()->json([
            'status' => 'success',
            'suggestions' => $suggestions
        ]);
    }

    // POST /api/kemi/analyze-performance/{agentId}
    public function analyzePerformance($agentId, Request $request)
    {
        $agent = TelesalesAgent::findOrFail($agentId);
        $period = $request->get('period', 'week');
        
        $analysis = $this->generatePerformanceAnalysis($agent, $period);
        
        return response()->json([
            'status' => 'success',
            'analysis' => $analysis
        ]);
    }

    // PRIVATE HELPER METHODS

    private function generateKemiResponse($userMessage, $order)
    {
        $message = strtolower($userMessage);
        
        // Simple rule-based responses (in production, this would use AI/ML)
        if (str_contains($message, 'hello') || str_contains($message, 'hi')) {
            return "Hello! I'm Kemi, your AI assistant. How can I help you with order {$order->order_number}?";
        }
        
        if (str_contains($message, 'customer') || str_contains($message, 'call')) {
            return "I can help you call the customer. The customer's phone number is {$order->customer_phone}. Would you like me to remind you of the best practices for customer calls?";
        }
        
        if (str_contains($message, 'delivery') || str_contains($message, 'da')) {
            return "I can help you assign a delivery agent. Based on the customer location, I can find the best available delivery agent. Would you like me to show you the available agents?";
        }
        
        if (str_contains($message, 'bonus') || str_contains($message, 'performance')) {
            return "I can help you track your performance and bonus eligibility. You need 70% delivery rate and at least 20 orders to qualify for the ₦150 per delivery bonus.";
        }
        
        if (str_contains($message, 'otp') || str_contains($message, 'payment')) {
            return "I can help you generate OTP for delivery verification. The customer needs to pay first, then we can generate the OTP for the delivery agent.";
        }
        
        return "I understand you're asking about '{$userMessage}'. How can I assist you with this order? I can help with customer calls, delivery assignment, performance tracking, or OTP generation.";
    }

    private function generateSuggestions($order)
    {
        $suggestions = [];
        
        // Based on order status and age
        $orderAge = $order->created_at->diffInMinutes(now());
        
        if ($order->call_status === 'pending' && $orderAge > 30) {
            $suggestions[] = [
                'type' => 'urgent',
                'message' => 'This order is over 30 minutes old and hasn\'t been called yet. Consider calling the customer immediately.',
                'action' => 'call_customer'
            ];
        }
        
        if ($order->call_status === 'callback' && $order->created_at->diffInHours(now()) > 2) {
            $suggestions[] = [
                'type' => 'reminder',
                'message' => 'This customer requested a callback over 2 hours ago. Follow up with them.',
                'action' => 'call_customer'
            ];
        }
        
        if ($order->call_status === 'confirmed' && !$order->delivery_agent_id) {
            $suggestions[] = [
                'type' => 'action',
                'message' => 'Customer confirmed the order. Assign a delivery agent now.',
                'action' => 'assign_da'
            ];
        }
        
        if ($order->payment_status === 'verified' && !$order->otp_code) {
            $suggestions[] = [
                'type' => 'action',
                'message' => 'Payment verified. Generate OTP for delivery.',
                'action' => 'generate_otp'
            ];
        }
        
        // Performance-based suggestions
        $agent = TelesalesAgent::find($order->telesales_agent_id);
        if ($agent) {
            $deliveryRate = $agent->getDeliveryRate();
            if ($deliveryRate < 70) {
                $suggestions[] = [
                    'type' => 'performance',
                    'message' => "Your delivery rate is {$deliveryRate}%. Focus on confirmed orders to reach 70% for bonus eligibility.",
                    'action' => 'focus_confirmed'
                ];
            }
        }
        
        return $suggestions;
    }

    private function generatePerformanceAnalysis($agent, $period)
    {
        $currentWeek = $agent->getCurrentWeekPerformance();
        
        $analysis = [
            'agent_name' => $agent->name,
            'period' => $period,
            'current_performance' => $currentWeek,
            'recommendations' => []
        ];
        
        if ($currentWeek) {
            $deliveryRate = $currentWeek['delivery_rate'] ?? 0;
            $ordersAssigned = $currentWeek['orders_assigned'] ?? 0;
            
            if ($deliveryRate < 70) {
                $analysis['recommendations'][] = [
                    'type' => 'improvement',
                    'message' => "Focus on confirmed orders to improve your delivery rate from {$deliveryRate}% to 70%",
                    'priority' => 'high'
                ];
            }
            
            if ($ordersAssigned < 20) {
                $analysis['recommendations'][] = [
                    'type' => 'volume',
                    'message' => "You need " . (20 - $ordersAssigned) . " more orders to meet the minimum requirement for bonus eligibility",
                    'priority' => 'medium'
                ];
            }
            
            if ($deliveryRate >= 70 && $ordersAssigned >= 20) {
                $analysis['recommendations'][] = [
                    'type' => 'success',
                    'message' => "Great job! You're qualified for the weekly bonus. Keep up the excellent work!",
                    'priority' => 'low'
                ];
            }
        }
        
        return $analysis;
    }
}

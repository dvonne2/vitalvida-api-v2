<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class EbulkSmsService
{
    private $username;
    private $apiKey;
    private $sender;
    private $baseUrl = 'https://api.ebulksms.com:443';
    
    public function __construct()
    {
        $this->username = config('services.ebulksms.username');
        $this->apiKey = config('services.ebulksms.apikey');
        $this->sender = config('services.ebulksms.sender');
    }
    
    public function sendWhatsApp($phone, $message)
    {
        $payload = [
            'username' => $this->username,
            'apikey' => $this->apiKey,
            'sender' => $this->sender,
            'message' => $message,
            'mobiles' => $this->formatPhone($phone),
            'type' => 'whatsapp'
        ];
        
        $response = Http::post("{$this->baseUrl}/sendsms.json", $payload);
        
        if ($response->successful()) {
            Log::info("WhatsApp sent to {$phone}: {$message}");
            return true;
        }
        
        Log::error("Failed to send WhatsApp to {$phone}: " . $response->body());
        return false;
    }
    
    public function sendSMS($phone, $message)
    {
        $payload = [
            'username' => $this->username,
            'apikey' => $this->apiKey,
            'sender' => $this->sender,
            'message' => $message,
            'mobiles' => $this->formatPhone($phone),
            'type' => 'sms'
        ];
        
        $response = Http::post("{$this->baseUrl}/sendsms.json", $payload);
        
        if ($response->successful()) {
            Log::info("SMS sent to {$phone}: {$message}");
            return true;
        }
        
        Log::error("Failed to send SMS to {$phone}: " . $response->body());
        return false;
    }
    
    public function sendOTP($phone, $otp, $customerName)
    {
        $message = "Hello {$customerName}, your VitalVida delivery OTP is: {$otp}. "
                 . "Please share this code with the delivery agent to confirm your order. "
                 . "This code expires in 24 hours.";
                 
        return $this->sendSMS($phone, $message);
    }
    
    public function sendDeliveryAgentNotification($phone, $order)
    {
        $items = [];
        foreach ($order->product_details as $item => $quantity) {
            $items[] = "{$quantity}x " . ucfirst($item);
        }
        $itemsList = implode(', ', $items);
        
        $message = "🚚 NEW ORDER ASSIGNED!\n\n"
                 . "Items: {$itemsList}\n"
                 . "Customer: {$order->customer_name}\n"
                 . "Location: {$order->customer_location}\n"
                 . "Phone: {$order->customer_phone}\n\n"
                 . "Please prepare for delivery.";
                 
        return $this->sendWhatsApp($phone, $message);
    }
    
    public function sendPaymentConfirmation($phone, $order)
    {
        $message = "Payment confirmed for order {$order->order_number}. "
                 . "Your OTP will be sent shortly for delivery verification.";
                 
        return $this->sendSMS($phone, $message);
    }
    
    public function sendDeliveryReminder($phone, $order)
    {
        $message = "Reminder: Your VitalVida order {$order->order_number} is ready for delivery. "
                 . "Please ensure you have your OTP ready for verification.";
                 
        return $this->sendSMS($phone, $message);
    }
    
    public function sendBonusNotification($phone, $agentName, $bonusAmount)
    {
        $message = "Congratulations {$agentName}! You've earned ₦{$bonusAmount} bonus this week. "
                 . "Keep up the excellent work!";
                 
        return $this->sendWhatsApp($phone, $message);
    }
    
    public function sendLowStockAlert($phone, $agentName, $location)
    {
        $message = "⚠️ LOW STOCK ALERT\n\n"
                 . "Agent: {$agentName}\n"
                 . "Location: {$location}\n"
                 . "Please restock your inventory soon.";
                 
        return $this->sendWhatsApp($phone, $message);
    }
    
    public function sendUrgentOrderAlert($phone, $order)
    {
        $message = "🚨 URGENT ORDER ALERT\n\n"
                 . "Order: {$order->order_number}\n"
                 . "Customer: {$order->customer_name}\n"
                 . "Location: {$order->customer_location}\n"
                 . "Status: {$order->call_status}\n\n"
                 . "This order needs immediate attention!";
                 
        return $this->sendWhatsApp($phone, $message);
    }
    
    public function sendWeeklyPerformanceSummary($phone, $agentName, $performance)
    {
        $deliveryRate = $performance['delivery_rate'] ?? 0;
        $ordersDelivered = $performance['orders_delivered'] ?? 0;
        $bonusEarned = $performance['bonus_earned'] ?? 0;
        
        $message = "📊 WEEKLY PERFORMANCE SUMMARY\n\n"
                 . "Agent: {$agentName}\n"
                 . "Delivery Rate: {$deliveryRate}%\n"
                 . "Orders Delivered: {$ordersDelivered}\n"
                 . "Bonus Earned: ₦{$bonusEarned}\n\n"
                 . "Keep up the great work!";
                 
        return $this->sendWhatsApp($phone, $message);
    }
    
    public function sendBulkNotification($phones, $message, $type = 'sms')
    {
        $formattedPhones = [];
        foreach ($phones as $phone) {
            $formattedPhones[] = $this->formatPhone($phone);
        }
        
        $payload = [
            'username' => $this->username,
            'apikey' => $this->apiKey,
            'sender' => $this->sender,
            'message' => $message,
            'mobiles' => implode(',', $formattedPhones),
            'type' => $type
        ];
        
        $response = Http::post("{$this->baseUrl}/sendsms.json", $payload);
        
        if ($response->successful()) {
            Log::info("Bulk {$type} sent to " . count($phones) . " recipients");
            return true;
        }
        
        Log::error("Failed to send bulk {$type}: " . $response->body());
        return false;
    }
    
    public function getBalance()
    {
        $payload = [
            'username' => $this->username,
            'apikey' => $this->apiKey
        ];
        
        $response = Http::post("{$this->baseUrl}/balance.json", $payload);
        
        if ($response->successful()) {
            return $response->json();
        }
        
        Log::error("Failed to get EbulkSMS balance: " . $response->body());
        return null;
    }
    
    public function testConnection()
    {
        try {
            $balance = $this->getBalance();
            return $balance !== null;
        } catch (Exception $e) {
            Log::error('EbulkSMS connection test failed: ' . $e->getMessage());
            return false;
        }
    }
    
    private function formatPhone($phone)
    {
        // Remove any non-digit characters except +
        $phone = preg_replace('/[^0-9+]/', '', $phone);
        
        // If it's a Nigerian number starting with 0, replace with +234
        if (preg_match('/^0/', $phone)) {
            $phone = preg_replace('/^0/', '+234', $phone);
        }
        
        // If it's a Nigerian number without country code, add +234
        if (preg_match('/^[0-9]{11}$/', $phone)) {
            $phone = '+234' . $phone;
        }
        
        return $phone;
    }
    
    public function validatePhone($phone)
    {
        $formatted = $this->formatPhone($phone);
        
        // Basic Nigerian phone number validation
        return preg_match('/^\+234[0-9]{10}$/', $formatted);
    }
} 
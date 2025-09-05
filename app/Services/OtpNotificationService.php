<?php

namespace App\Services;

use App\Models\Order;
use App\Mail\DeliveryConfirmationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;

class OtpNotificationService
{
    public function sendDeliveryConfirmationEmail(Order $order): bool
    {
        try {
            Mail::to($order->customer_email)->send(new DeliveryConfirmationMail($order));
            
            Log::info('Delivery confirmation email sent', [
                'order_number' => $order->order_number,
                'email' => $order->customer_email
            ]);
            
            return true;
        } catch (\Exception $e) {
            Log::error('Delivery confirmation email failed', [
                'order_number' => $order->order_number,
                'email' => $order->customer_email,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function notifyDeliveryAgentSuccess(Order $order, int $deliveryAgentId): bool
    {
        try {
            $deliveryAgent = \DB::table('delivery_agents')
                ->where('id', $deliveryAgentId)
                ->first();

            if (!$deliveryAgent || !$deliveryAgent->whatsapp_number) {
                Log::warning('Delivery agent WhatsApp not found', [
                    'delivery_agent_id' => $deliveryAgentId,
                    'order_number' => $order->order_number
                ]);
                return false;
            }

            $message = "Delivery Confirmed! Order: {$order->order_number} Customer: {$order->customer_name} Address: {$order->delivery_address} Stock has been deducted from your BIN. Great job!";

            return $this->sendWhatsAppMessage($deliveryAgent->whatsapp_number, $message);

        } catch (\Exception $e) {
            Log::error('Failed to notify delivery agent', [
                'delivery_agent_id' => $deliveryAgentId,
                'order_number' => $order->order_number,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendCustomerDeliveryConfirmationSms(Order $order): bool
    {
        try {
            $message = "Hi {$order->customer_name}! Your VitalVida order #{$order->order_number} has been delivered successfully. Thank you for choosing us!";
            
            return $this->sendSms($order->customer_phone, $message);

        } catch (\Exception $e) {
            Log::error('Customer delivery SMS failed', [
                'order_number' => $order->order_number,
                'phone' => $order->customer_phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    public function sendCustomerDeliveryConfirmationWhatsApp(Order $order): bool
    {
        try {
            $itemsList = collect($order->items)->map(function($item) {
                return "* {$item['name']} (Qty: {$item['quantity']})";
            })->join("\n");

            $message = "Delivery Completed! Dear {$order->customer_name}, Your VitalVida order has been delivered successfully! Order Details: Order #: {$order->order_number} Items Delivered: {$itemsList} Thank you for choosing VitalVida! Your health is our priority.";

            return $this->sendWhatsAppMessage($this->formatPhoneNumberForWhatsApp($order->customer_phone), $message);

        } catch (\Exception $e) {
            Log::error('Customer delivery WhatsApp failed', [
                'order_number' => $order->order_number,
                'phone' => $order->customer_phone,
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    private function formatPhoneNumberForWhatsApp(string $phone): string
    {
        $formatted = $this->formatPhoneNumber($phone);
        return '+' . $formatted;
    }

    private function formatPhoneNumber(string $phone): string
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        if (strlen($phone) === 11 && substr($phone, 0, 1) === '0') {
            return '234' . substr($phone, 1);
        } elseif (strlen($phone) === 10) {
            return '234' . $phone;
        } elseif (strlen($phone) === 13 && substr($phone, 0, 3) === '234') {
            return $phone;
        }
        
        return $phone;
    }

    private function sendSms(string $phone, string $message): bool
    {
        Log::info('SMS sent', ['phone' => $phone, 'message' => $message]);
        return true;
    }

    private function sendWhatsAppMessage(string $phone, string $message): bool
    {
        Log::info('WhatsApp sent', ['phone' => $phone, 'message' => $message]);
        return true;
    }
}

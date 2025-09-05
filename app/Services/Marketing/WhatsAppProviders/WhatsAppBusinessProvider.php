<?php

namespace App\Services\Marketing\WhatsAppProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WhatsAppBusinessProvider implements WhatsAppProviderInterface
{
    private $apiUrl;
    private $accessToken;
    private $phoneNumberId;
    private $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.whatsapp_business.api_url', 'https://graph.facebook.com/v18.0');
        $this->accessToken = config('services.whatsapp_business.access_token');
        $this->phoneNumberId = config('services.whatsapp_business.phone_number_id');
        $this->timeout = 30;
    }

    public function sendMessage($phoneNumber, $message, $templateName = null, $templateParams = [])
    {
        if (!$this->accessToken || !$this->phoneNumberId) {
            throw new \Exception('WhatsApp Business API credentials not configured');
        }

        $payload = [
            'messaging_product' => 'whatsapp',
            'to' => $this->formatPhoneNumber($phoneNumber),
        ];

        if ($templateName) {
            // Template message
            $payload['type'] = 'template';
            $payload['template'] = [
                'name' => $templateName,
                'language' => ['code' => 'en']
            ];

            if (!empty($templateParams)) {
                $payload['template']['components'] = [
                    [
                        'type' => 'body',
                        'parameters' => array_map(function($param) {
                            return ['type' => 'text', 'text' => $param];
                        }, $templateParams)
                    ]
                ];
            }
        } else {
            // Text message
            $payload['type'] = 'text';
            $payload['text'] = ['body' => $message];
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Content-Type' => 'application/json'
            ])
            ->post($this->apiUrl . '/' . $this->phoneNumberId . '/messages', $payload);

        if (!$response->successful()) {
            $error = $response->json();
            throw new \Exception('WhatsApp Business API error: ' . ($error['error']['message'] ?? $response->body()));
        }

        return $response->json();
    }

    public function checkHealth()
    {
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->accessToken
                ])
                ->get($this->apiUrl . '/' . $this->phoneNumberId);

            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'response_time' => $responseTime,
                    'phone_number_info' => $response->json()
                ];
            }

            throw new \Exception('Health check failed: ' . $response->status());
            
        } catch (\Exception $e) {
            throw new \Exception('WhatsApp Business API health check failed: ' . $e->getMessage());
        }
    }

    public function getTemplates()
    {
        if (!$this->accessToken) {
            throw new \Exception('WhatsApp Business API access token not configured');
        }

        $businessAccountId = config('services.whatsapp_business.business_account_id');
        
        if (!$businessAccountId) {
            throw new \Exception('WhatsApp Business Account ID not configured');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken
            ])
            ->get($this->apiUrl . '/' . $businessAccountId . '/message_templates');

        if (!$response->successful()) {
            $error = $response->json();
            throw new \Exception('Failed to fetch WhatsApp templates: ' . ($error['error']['message'] ?? $response->body()));
        }

        return $response->json();
    }

    public function getName()
    {
        return 'whatsapp_business';
    }

    private function formatPhoneNumber($phoneNumber)
    {
        // Remove any non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phoneNumber);
        
        // Add country code if not present (assuming Nigeria +234)
        if (!str_starts_with($phone, '234') && strlen($phone) === 11) {
            $phone = '234' . substr($phone, 1);
        }
        
        return $phone;
    }
}

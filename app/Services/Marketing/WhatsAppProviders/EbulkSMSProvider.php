<?php

namespace App\Services\Marketing\WhatsAppProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class EbulkSMSProvider implements WhatsAppProviderInterface
{
    private $apiUrl;
    private $username;
    private $apiKey;
    private $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.ebulksms.api_url', 'https://api.ebulksms.com');
        $this->username = config('services.ebulksms.username');
        $this->apiKey = config('services.ebulksms.api_key');
        $this->timeout = 30;
    }

    public function sendMessage($phoneNumber, $message, $templateName = null, $templateParams = [])
    {
        if (!$this->username || !$this->apiKey) {
            throw new \Exception('EbulkSMS credentials not configured');
        }

        $payload = [
            'SMS' => [
                'auth' => [
                    'username' => $this->username,
                    'apikey' => $this->apiKey
                ],
                'message' => [
                    'sender' => config('services.ebulksms.sender_id', 'VitalVida'),
                    'messagetext' => $message,
                    'flash' => '0'
                ],
                'recipients' => [
                    'gsm' => [
                        [
                            'msidn' => $this->formatPhoneNumber($phoneNumber),
                            'msgid' => uniqid()
                        ]
                    ]
                ]
            ]
        ];

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Content-Type' => 'application/json'
            ])
            ->post($this->apiUrl . '/sendsms.json', $payload);

        if (!$response->successful()) {
            throw new \Exception('EbulkSMS API error: ' . $response->body());
        }

        $result = $response->json();
        
        // Check if the response indicates success
        if (isset($result['response']['status']) && $result['response']['status'] !== 'SUCCESS') {
            throw new \Exception('EbulkSMS error: ' . ($result['response']['status'] ?? 'Unknown error'));
        }

        return $result;
    }

    public function checkHealth()
    {
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Content-Type' => 'application/json'
                ])
                ->post($this->apiUrl . '/balance/' . $this->username . '/' . $this->apiKey);

            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'response_time' => $responseTime,
                    'balance' => $response->json()
                ];
            }

            throw new \Exception('Health check failed: ' . $response->status());
            
        } catch (\Exception $e) {
            throw new \Exception('EbulkSMS health check failed: ' . $e->getMessage());
        }
    }

    public function getTemplates()
    {
        // EbulkSMS doesn't have template management like WhatsApp Business API
        // Return empty array or predefined templates
        return [
            'templates' => [],
            'message' => 'EbulkSMS does not support template management'
        ];
    }

    public function getName()
    {
        return 'ebulksms';
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

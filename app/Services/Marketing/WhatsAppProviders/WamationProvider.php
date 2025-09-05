<?php

namespace App\Services\Marketing\WhatsAppProviders;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WamationProvider implements WhatsAppProviderInterface
{
    private $apiUrl;
    private $apiKey;
    private $timeout;

    public function __construct()
    {
        $this->apiUrl = config('services.wamation.api_url', 'https://api.wamation.com');
        $this->apiKey = config('services.wamation.api_key');
        $this->timeout = 30;
    }

    public function sendMessage($phoneNumber, $message, $templateName = null, $templateParams = [])
    {
        if (!$this->apiKey) {
            throw new \Exception('Wamation API key not configured');
        }

        $payload = [
            'phone' => $this->formatPhoneNumber($phoneNumber),
            'message' => $message,
            'type' => $templateName ? 'template' : 'text'
        ];

        if ($templateName) {
            $payload['template'] = [
                'name' => $templateName,
                'parameters' => $templateParams
            ];
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey,
                'Content-Type' => 'application/json'
            ])
            ->post($this->apiUrl . '/v1/messages', $payload);

        if (!$response->successful()) {
            throw new \Exception('Wamation API error: ' . $response->body());
        }

        return $response->json();
    }

    public function checkHealth()
    {
        $startTime = microtime(true);
        
        try {
            $response = Http::timeout(10)
                ->withHeaders([
                    'Authorization' => 'Bearer ' . $this->apiKey
                ])
                ->get($this->apiUrl . '/v1/health');

            $responseTime = (microtime(true) - $startTime) * 1000;

            if ($response->successful()) {
                return [
                    'status' => 'healthy',
                    'response_time' => $responseTime
                ];
            }

            throw new \Exception('Health check failed: ' . $response->status());
            
        } catch (\Exception $e) {
            throw new \Exception('Wamation health check failed: ' . $e->getMessage());
        }
    }

    public function getTemplates()
    {
        if (!$this->apiKey) {
            throw new \Exception('Wamation API key not configured');
        }

        $response = Http::timeout($this->timeout)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->apiKey
            ])
            ->get($this->apiUrl . '/v1/templates');

        if (!$response->successful()) {
            throw new \Exception('Failed to fetch Wamation templates: ' . $response->body());
        }

        return $response->json();
    }

    public function getName()
    {
        return 'wamation';
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

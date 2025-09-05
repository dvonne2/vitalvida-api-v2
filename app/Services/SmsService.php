<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SmsService
{
    protected $apiKey;
    protected $senderId;
    protected $apiUrl;

    public function __construct()
    {
        $this->apiKey = config('services.sms.api_key');
        $this->senderId = config('services.sms.sender_id', 'VitalVida');
        $this->apiUrl = config('services.sms.api_url', 'https://api.termii.com/api/sms/send');
    }

    /**
     * Send SMS message
     */
    public function sendSms(string $phone, string $message): array
    {
        try {
            // Normalize phone number for Nigerian format
            $normalizedPhone = $this->normalizePhoneNumber($phone);

            // Prepare SMS payload
            $payload = [
                'to' => $normalizedPhone,
                'from' => $this->senderId,
                'sms' => $message,
                'type' => 'plain',
                'api_key' => $this->apiKey,
                'channel' => 'generic'
            ];

            // Send SMS via HTTP API
            $response = Http::timeout(30)
                ->post($this->apiUrl, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                
                Log::info('SMS sent successfully', [
                    'phone' => $normalizedPhone,
                    'message_id' => $responseData['message_id'] ?? null,
                    'status' => $responseData['status'] ?? 'sent'
                ]);

                return [
                    'success' => true,
                    'message_id' => $responseData['message_id'] ?? null,
                    'status' => $responseData['status'] ?? 'sent',
                    'provider_response' => $responseData
                ];
            } else {
                Log::error('SMS sending failed', [
                    'phone' => $normalizedPhone,
                    'status_code' => $response->status(),
                    'response' => $response->body()
                ]);

                return [
                    'success' => false,
                    'error' => 'SMS provider returned error: ' . $response->status(),
                    'provider_response' => $response->json()
                ];
            }

        } catch (\Exception $e) {
            Log::error('SMS service exception', [
                'phone' => $phone,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'SMS service failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Send bulk SMS messages
     */
    public function sendBulkSms(array $recipients, string $message): array
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $phone) {
            $result = $this->sendSms($phone, $message);
            $results[] = [
                'phone' => $phone,
                'success' => $result['success'],
                'message_id' => $result['message_id'] ?? null,
                'error' => $result['error'] ?? null
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }
        }

        return [
            'success' => $successCount > 0,
            'total_sent' => count($recipients),
            'successful' => $successCount,
            'failed' => $failureCount,
            'results' => $results
        ];
    }

    /**
     * Check SMS delivery status
     */
    public function checkDeliveryStatus(string $messageId): array
    {
        try {
            $response = Http::timeout(30)
                ->get("https://api.termii.com/api/sms/inbox", [
                    'api_key' => $this->apiKey,
                    'message_id' => $messageId
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'success' => true,
                    'status' => $responseData['status'] ?? 'unknown',
                    'delivered_at' => $responseData['delivered_at'] ?? null,
                    'provider_response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to check delivery status',
                    'provider_response' => $response->json()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Delivery status check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Normalize phone number for Nigerian format
     */
    private function normalizePhoneNumber(string $phone): string
    {
        // Remove all non-digits
        $cleaned = preg_replace('/\D/', '', $phone);

        // Handle different Nigerian phone formats
        if (strlen($cleaned) === 11 && substr($cleaned, 0, 1) === '0') {
            // 0803XXXXXXX -> 2348XXXXXXX
            return '234' . substr($cleaned, 1);
        }

        if (strlen($cleaned) === 10) {
            // 803XXXXXXX -> 2348XXXXXXX
            return '234' . $cleaned;
        }

        if (strlen($cleaned) === 13 && substr($cleaned, 0, 3) === '234') {
            // Already in correct format
            return $cleaned;
        }

        // If format is unclear, assume it needs 234 prefix
        return '234' . $cleaned;
    }

    /**
     * Get SMS account balance
     */
    public function getBalance(): array
    {
        try {
            $response = Http::timeout(30)
                ->get("https://api.termii.com/api/get-balance", [
                    'api_key' => $this->apiKey
                ]);

            if ($response->successful()) {
                $responseData = $response->json();
                
                return [
                    'success' => true,
                    'balance' => $responseData['balance'] ?? 0,
                    'currency' => $responseData['currency'] ?? 'NGN',
                    'provider_response' => $responseData
                ];
            } else {
                return [
                    'success' => false,
                    'error' => 'Failed to get balance',
                    'provider_response' => $response->json()
                ];
            }

        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Balance check failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Validate phone number format
     */
    public function isValidPhoneNumber(string $phone): bool
    {
        $normalized = $this->normalizePhoneNumber($phone);
        
        // Nigerian phone numbers should be 13 digits starting with 234
        return strlen($normalized) === 13 && 
               substr($normalized, 0, 3) === '234' &&
               ctype_digit($normalized);
    }
} 
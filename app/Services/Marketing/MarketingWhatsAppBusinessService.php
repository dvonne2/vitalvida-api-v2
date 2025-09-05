<?php

namespace App\Services\Marketing;

use App\Models\Customer;
use App\Models\Marketing\MarketingWhatsAppLog;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use Exception;

class MarketingWhatsAppBusinessService
{
    protected $providers = ['wamation', 'ebulksms', 'whatsapp_business'];
    protected $currentProvider = 'wamation'; // Primary provider
    
    public function sendMessage($phone, $message, $companyId)
    {
        // Validate customer consent
        $customer = Customer::where('phone', $phone)
            ->where('company_id', $companyId)
            ->where('whatsapp_consent', true)
            ->first();
            
        if (!$customer) {
            throw new Exception('Customer not found or consent not given');
        }
        
        // Try providers in order with automatic failover
        return $this->sendWithFailover($phone, $message, $companyId);
    }
    
    private function sendWithFailover($phone, $message, $companyId)
    {
        foreach ($this->providers as $provider) {
            try {
                $response = match($provider) {
                    'wamation' => $this->sendViaWamation($phone, $message),
                    'ebulksms' => $this->sendViaEbulkSMS($phone, $message),
                    'whatsapp_business' => $this->sendViaWhatsAppBusiness($phone, $message),
                };
                
                // Log successful send
                $this->logWhatsAppSend($phone, $message, $provider, 'success', $companyId, null, $response);
                
                return array_merge($response, ['provider' => $provider]);
                
            } catch (Exception $e) {
                // Log failed attempt
                $this->logWhatsAppSend($phone, $message, $provider, 'failed', $companyId, $e->getMessage());
                
                // Continue to next provider
                continue;
            }
        }
        
        // All providers failed
        throw new Exception('All WhatsApp providers failed');
    }
    
    private function sendViaWamation($phone, $message)
    {
        $client = new Client();
        
        $response = $client->post(config('services.wamation.api_url'), [
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.wamation.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'phone' => $this->formatNigerianPhone($phone),
                'message' => $message,
                'type' => 'text'
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    }
    
    private function sendViaEbulkSMS($phone, $message)
    {
        $client = new Client();
        
        $response = $client->post(config('services.ebulksms.whatsapp_url'), [
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.ebulksms.api_key'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'recipient' => $this->formatNigerianPhone($phone),
                'message' => $message,
                'sender' => config('services.ebulksms.sender_id')
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    }
    
    private function sendViaWhatsAppBusiness($phone, $message)
    {
        $client = new Client();
        
        $response = $client->post(config('services.whatsapp.api_url'), [
            'headers' => [
                'Authorization' => 'Bearer ' . config('services.whatsapp.access_token'),
                'Content-Type' => 'application/json',
            ],
            'json' => [
                'messaging_product' => 'whatsapp',
                'to' => $this->formatNigerianPhone($phone),
                'type' => 'text',
                'text' => ['body' => $message]
            ]
        ]);
        
        return json_decode($response->getBody(), true);
    }
    
    private function formatNigerianPhone($phone)
    {
        // Format phone number for Nigerian WhatsApp
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Convert Nigerian numbers to international format
        if (substr($phone, 0, 1) === '0') {
            $phone = '234' . substr($phone, 1);
        } elseif (substr($phone, 0, 3) !== '234') {
            $phone = '234' . $phone;
        }
        
        return '+' . $phone;
    }
    
    private function logWhatsAppSend($phone, $message, $provider, $status, $companyId, $error = null, $response = null)
    {
        MarketingWhatsAppLog::create([
            'phone' => $phone,
            'message' => $message,
            'provider' => $provider,
            'status' => $status,
            'error_message' => $error,
            'response_data' => $response,
            'company_id' => $companyId,
            'user_id' => auth()->id(),
            'created_at' => now()
        ]);
    }
    
    public function bulkSend($recipients, $message, $companyId)
    {
        $results = [];
        $providerIndex = 0;
        
        foreach ($recipients as $recipient) {
            // Rotate providers for load balancing
            $provider = $this->providers[$providerIndex % count($this->providers)];
            
            try {
                $response = match($provider) {
                    'wamation' => $this->sendViaWamation($recipient['phone'], $message),
                    'ebulksms' => $this->sendViaEbulkSMS($recipient['phone'], $message),
                    'whatsapp_business' => $this->sendViaWhatsAppBusiness($recipient['phone'], $message),
                };
                
                $results[] = [
                    'phone' => $recipient['phone'],
                    'status' => 'sent',
                    'provider' => $provider
                ];
                
            } catch (Exception $e) {
                // Try failover for individual message
                try {
                    $response = $this->sendWithFailover($recipient['phone'], $message, $companyId);
                    $results[] = [
                        'phone' => $recipient['phone'],
                        'status' => 'sent_with_failover',
                        'provider' => 'failover'
                    ];
                } catch (Exception $e2) {
                    $results[] = [
                        'phone' => $recipient['phone'],
                        'status' => 'failed',
                        'error' => $e2->getMessage()
                    ];
                }
            }
            
            $providerIndex++;
            
            // Add small delay to prevent rate limiting
            usleep(100000); // 0.1 second delay
        }
        
        return $results;
    }
    
    public function getProviderStatus()
    {
        $status = [];
        
        foreach ($this->providers as $provider) {
            try {
                $status[$provider] = $this->checkProviderHealth($provider);
            } catch (Exception $e) {
                $status[$provider] = [
                    'status' => 'down',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        return $status;
    }
    
    private function checkProviderHealth($provider)
    {
        return match($provider) {
            'wamation' => $this->checkWamationHealth(),
            'ebulksms' => $this->checkEbulkSMSHealth(),
            'whatsapp_business' => $this->checkWhatsAppBusinessHealth(),
        };
    }
    
    private function checkWamationHealth()
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get(config('services.wamation.health_url'));
            return ['status' => 'up', 'response_time' => $response->getHeader('X-Response-Time')[0] ?? 'unknown'];
        } catch (Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
    
    private function checkEbulkSMSHealth()
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get(config('services.ebulksms.health_url'));
            return ['status' => 'up', 'response_time' => $response->getHeader('X-Response-Time')[0] ?? 'unknown'];
        } catch (Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
    
    private function checkWhatsAppBusinessHealth()
    {
        try {
            $client = new Client(['timeout' => 5]);
            $response = $client->get(config('services.whatsapp.health_url'));
            return ['status' => 'up', 'response_time' => $response->getHeader('X-Response-Time')[0] ?? 'unknown'];
        } catch (Exception $e) {
            return ['status' => 'down', 'error' => $e->getMessage()];
        }
    }
}

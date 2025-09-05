<?php

namespace App\Services\Marketing;

use App\Models\Marketing\MarketingWhatsAppLog;
use App\Services\Marketing\WhatsAppProviders\WamationProvider;
use App\Services\Marketing\WhatsAppProviders\EbulkSMSProvider;
use App\Services\Marketing\WhatsAppProviders\WhatsAppBusinessProvider;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class WhatsAppService
{
    private $providers;
    private $currentProvider;
    private $companyId;

    public function __construct($companyId = null)
    {
        $this->companyId = $companyId ?? auth()->user()->company_id;
        $this->initializeProviders();
    }

    private function initializeProviders()
    {
        $this->providers = [
            'wamation' => new WamationProvider(),
            'ebulksms' => new EbulkSMSProvider(),
            'whatsapp_business' => new WhatsAppBusinessProvider()
        ];
        
        // Set default provider order based on reliability
        $this->currentProvider = 'wamation';
    }

    public function sendMessage($phoneNumber, $message, $templateName = null, $templateParams = [], $campaignId = null)
    {
        $startTime = microtime(true);
        $providers = array_keys($this->providers);
        $lastException = null;

        foreach ($providers as $providerName) {
            try {
                $provider = $this->providers[$providerName];
                
                Log::info("Attempting WhatsApp send via {$providerName}", [
                    'phone' => $phoneNumber,
                    'company_id' => $this->companyId
                ]);

                $response = $provider->sendMessage($phoneNumber, $message, $templateName, $templateParams);
                
                $responseTime = (microtime(true) - $startTime) * 1000; // Convert to milliseconds

                // Log successful attempt
                $this->logAttempt([
                    'provider' => $providerName,
                    'phone_number' => $phoneNumber,
                    'message' => $message,
                    'template_name' => $templateName,
                    'template_params' => $templateParams,
                    'campaign_id' => $campaignId,
                    'status' => 'delivered',
                    'response_time_ms' => $responseTime,
                    'provider_response' => $response,
                    'company_id' => $this->companyId
                ]);

                return [
                    'success' => true,
                    'provider' => $providerName,
                    'response' => $response,
                    'response_time_ms' => $responseTime
                ];

            } catch (\Exception $e) {
                $responseTime = (microtime(true) - $startTime) * 1000;
                $lastException = $e;

                Log::warning("WhatsApp send failed via {$providerName}", [
                    'phone' => $phoneNumber,
                    'error' => $e->getMessage(),
                    'company_id' => $this->companyId
                ]);

                // Log failed attempt
                $this->logAttempt([
                    'provider' => $providerName,
                    'phone_number' => $phoneNumber,
                    'message' => $message,
                    'template_name' => $templateName,
                    'template_params' => $templateParams,
                    'campaign_id' => $campaignId,
                    'status' => 'failed',
                    'response_time_ms' => $responseTime,
                    'error_message' => $e->getMessage(),
                    'company_id' => $this->companyId
                ]);

                // Continue to next provider
                continue;
            }
        }

        // All providers failed
        Log::error("All WhatsApp providers failed", [
            'phone' => $phoneNumber,
            'last_error' => $lastException?->getMessage(),
            'company_id' => $this->companyId
        ]);

        return [
            'success' => false,
            'error' => 'All WhatsApp providers failed',
            'last_error' => $lastException?->getMessage()
        ];
    }

    public function bulkSend($recipients, $message, $templateName = null, $templateParams = [], $campaignId = null)
    {
        $results = [];
        $successCount = 0;
        $failureCount = 0;

        foreach ($recipients as $recipient) {
            $phoneNumber = $recipient['phone'] ?? $recipient;
            $customParams = $recipient['template_params'] ?? $templateParams;
            
            $result = $this->sendMessage($phoneNumber, $message, $templateName, $customParams, $campaignId);
            
            $results[] = [
                'phone' => $phoneNumber,
                'result' => $result
            ];

            if ($result['success']) {
                $successCount++;
            } else {
                $failureCount++;
            }

            // Add small delay between messages to avoid rate limiting
            usleep(100000); // 100ms delay
        }

        return [
            'total_sent' => count($recipients),
            'success_count' => $successCount,
            'failure_count' => $failureCount,
            'results' => $results
        ];
    }

    public function getProviderStatus()
    {
        $status = [];

        foreach ($this->providers as $name => $provider) {
            try {
                $health = $provider->checkHealth();
                $status[$name] = [
                    'name' => $name,
                    'status' => 'healthy',
                    'response_time' => $health['response_time'] ?? null,
                    'last_check' => Carbon::now()
                ];
            } catch (\Exception $e) {
                $status[$name] = [
                    'name' => $name,
                    'status' => 'unhealthy',
                    'error' => $e->getMessage(),
                    'last_check' => Carbon::now()
                ];
            }
        }

        return $status;
    }

    public function switchProvider($providerName)
    {
        if (!isset($this->providers[$providerName])) {
            throw new \InvalidArgumentException("Provider {$providerName} not found");
        }

        $this->currentProvider = $providerName;
        
        Log::info("Switched WhatsApp provider to {$providerName}", [
            'company_id' => $this->companyId
        ]);

        return true;
    }

    public function getTemplates($providerName = null)
    {
        $provider = $providerName ? $this->providers[$providerName] : $this->providers[$this->currentProvider];
        
        try {
            return $provider->getTemplates();
        } catch (\Exception $e) {
            Log::error("Failed to get WhatsApp templates", [
                'provider' => $providerName ?? $this->currentProvider,
                'error' => $e->getMessage(),
                'company_id' => $this->companyId
            ]);
            
            throw $e;
        }
    }

    public function getLogs($filters = [])
    {
        $query = MarketingWhatsAppLog::where('company_id', $this->companyId);

        if (isset($filters['provider'])) {
            $query->where('provider', $filters['provider']);
        }

        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (isset($filters['campaign_id'])) {
            $query->where('campaign_id', $filters['campaign_id']);
        }

        if (isset($filters['start_date'])) {
            $query->where('created_at', '>=', $filters['start_date']);
        }

        if (isset($filters['end_date'])) {
            $query->where('created_at', '<=', $filters['end_date']);
        }

        return $query->orderBy('created_at', 'desc')
                    ->paginate($filters['per_page'] ?? 50);
    }

    public function getProviderPerformance($days = 30)
    {
        $startDate = Carbon::now()->subDays($days);

        return MarketingWhatsAppLog::where('company_id', $this->companyId)
            ->where('created_at', '>=', $startDate)
            ->selectRaw('
                provider,
                COUNT(*) as total_attempts,
                COUNT(CASE WHEN status = "delivered" THEN 1 END) as delivered,
                COUNT(CASE WHEN status = "failed" THEN 1 END) as failed,
                AVG(response_time_ms) as avg_response_time,
                (COUNT(CASE WHEN status = "delivered" THEN 1 END) / COUNT(*)) * 100 as delivery_rate
            ')
            ->groupBy('provider')
            ->orderByDesc('delivery_rate')
            ->get();
    }

    private function logAttempt($data)
    {
        try {
            MarketingWhatsAppLog::create($data);
        } catch (\Exception $e) {
            Log::error("Failed to log WhatsApp attempt", [
                'error' => $e->getMessage(),
                'data' => $data
            ]);
        }
    }
}

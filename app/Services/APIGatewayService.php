<?php

namespace App\Services;

use App\Models\ApiKey;
use App\Models\ApiRequest;
use App\Models\RateLimitRule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class APIGatewayService
{
    private const DEFAULT_RATE_LIMITS = [
        'mobile' => ['requests' => 1000, 'window' => 3600], // 1000 requests per hour
        'dashboard' => ['requests' => 2000, 'window' => 3600], // 2000 requests per hour
        'reporting' => ['requests' => 100, 'window' => 3600], // 100 reports per hour
        'sync' => ['requests' => 5000, 'window' => 3600] // 5000 sync requests per hour
    ];

    /**
     * Process incoming API request through gateway
     */
    public function processRequest(Request $request, string $service): array
    {
        $startTime = microtime(true);
        
        try {
            // 1. Authenticate request
            $authResult = $this->authenticateRequest($request);
            if (!$authResult['success']) {
                return $this->formatErrorResponse('authentication_failed', $authResult['message'], 401);
            }

            // 2. Check rate limits
            $rateLimitResult = $this->checkRateLimit($request, $authResult['user'], $service);
            if (!$rateLimitResult['allowed']) {
                return $this->formatErrorResponse('rate_limit_exceeded', 'Too many requests', 429, $rateLimitResult);
            }

            // 3. Validate request format
            $validationResult = $this->validateRequest($request, $service);
            if (!$validationResult['valid']) {
                return $this->formatErrorResponse('validation_failed', $validationResult['message'], 400);
            }

            // 4. Check cache for response
            $cacheKey = $this->generateCacheKey($request, $service);
            $cachedResponse = Cache::get($cacheKey);
            if ($cachedResponse && $this->isCacheable($request, $service)) {
                $this->logRequest($request, $service, 'cache_hit', microtime(true) - $startTime);
                return $this->formatSuccessResponse($cachedResponse, ['from_cache' => true]);
            }

            // 5. Transform request for backend
            $transformedRequest = $this->transformRequest($request, $service);

            // 6. Route to appropriate service
            $serviceResponse = $this->routeToService($transformedRequest, $service);

            // 7. Transform response for mobile
            $transformedResponse = $this->transformResponse($serviceResponse, $service);

            // 8. Cache response if applicable
            if ($this->isCacheable($request, $service)) {
                Cache::put($cacheKey, $transformedResponse, $this->getCacheTTL($service));
            }

            // 9. Log request
            $this->logRequest($request, $service, 'success', microtime(true) - $startTime);

            return $this->formatSuccessResponse($transformedResponse);

        } catch (\Exception $e) {
            Log::error('API Gateway error', [
                'service' => $service,
                'error' => $e->getMessage(),
                'request_id' => $request->header('X-Request-ID')
            ]);

            $this->logRequest($request, $service, 'error', microtime(true) - $startTime, $e->getMessage());
            
            return $this->formatErrorResponse('internal_error', 'Service temporarily unavailable', 500);
        }
    }

    /**
     * Authenticate mobile app requests
     */
    private function authenticateRequest(Request $request): array
    {
        // Check for API key in headers
        $apiKey = $request->header('X-API-Key');
        $authToken = $request->bearerToken();

        if (!$apiKey && !$authToken) {
            return ['success' => false, 'message' => 'Missing authentication credentials'];
        }

        // Validate API key for mobile app
        if ($apiKey) {
            $keyRecord = ApiKey::where('key', $apiKey)
                ->where('is_active', true)
                ->where('expires_at', '>', now())
                ->first();

            if (!$keyRecord) {
                return ['success' => false, 'message' => 'Invalid API key'];
            }

            // Update last used timestamp
            $keyRecord->update(['last_used_at' => now()]);

            return [
                'success' => true,
                'user' => $keyRecord->user,
                'auth_type' => 'api_key',
                'client_type' => $keyRecord->client_type
            ];
        }

        // Validate bearer token for web app
        if ($authToken) {
            try {
                $user = auth('api')->user();
                if (!$user) {
                    return ['success' => false, 'message' => 'Invalid access token'];
                }

                return [
                    'success' => true,
                    'user' => $user,
                    'auth_type' => 'bearer_token',
                    'client_type' => 'web'
                ];

            } catch (\Exception $e) {
                return ['success' => false, 'message' => 'Token validation failed'];
            }
        }

        return ['success' => false, 'message' => 'Authentication failed'];
    }

    /**
     * Check rate limits for user and service
     */
    private function checkRateLimit(Request $request, $user, string $service): array
    {
        $clientType = $this->getClientType($request);
        $rateLimitKey = "rate_limit:{$user->id}:{$service}:{$clientType}";
        
        // Get rate limit configuration
        $limits = $this->getRateLimits($user, $service, $clientType);
        $window = $limits['window'];
        $maxRequests = $limits['requests'];

        // Use Redis for distributed rate limiting
        $currentCount = Redis::get($rateLimitKey) ?: 0;
        
        if ($currentCount >= $maxRequests) {
            $ttl = Redis::ttl($rateLimitKey);
            return [
                'allowed' => false,
                'limit' => $maxRequests,
                'remaining' => 0,
                'reset_time' => now()->addSeconds($ttl),
                'retry_after' => $ttl
            ];
        }

        // Increment counter
        $newCount = Redis::incr($rateLimitKey);
        if ($newCount === 1) {
            Redis::expire($rateLimitKey, $window);
        }

        return [
            'allowed' => true,
            'limit' => $maxRequests,
            'remaining' => max(0, $maxRequests - $newCount),
            'reset_time' => now()->addSeconds(Redis::ttl($rateLimitKey))
        ];
    }

    /**
     * Route request to appropriate backend service
     */
    private function routeToService(Request $request, string $service): array
    {
        $method = $request->method();
        $data = $request->all();

        // Use internal service calls instead of HTTP for better performance
        return match($service) {
            'auth' => app(\App\Services\AuthenticationService::class)->handleMobileRequest($method, $data),
            'payments' => app(\App\Services\PaymentEngineService::class)->handleMobileRequest($method, $data),
            'inventory' => app(\App\Services\InventoryVerificationService::class)->handleMobileRequest($method, $data),
            'thresholds' => app(\App\Services\ThresholdValidationService::class)->handleMobileRequest($method, $data),
            'bonuses' => app(\App\Services\BonusCalculationService::class)->handleMobileRequest($method, $data),
            'reports' => app(\App\Services\ReportGeneratorService::class)->handleMobileRequest($method, $data),
            'analytics' => app(\App\Services\AnalyticsEngineService::class)->handleMobileRequest($method, $data),
            'sync' => app(\App\Services\MobileSyncService::class)->handleSyncRequest($method, $data),
            default => throw new \InvalidArgumentException("Unknown service: {$service}")
        };
    }

    /**
     * Transform request for backend compatibility
     */
    private function transformRequest(Request $request, string $service): Request
    {
        $data = $request->all();

        // Mobile-specific transformations
        switch ($service) {
            case 'payments':
                $data = $this->transformPaymentRequest($data);
                break;
            case 'inventory':
                $data = $this->transformInventoryRequest($data);
                break;
            case 'reports':
                $data = $this->transformReportRequest($data);
                break;
        }

        // Create new request with transformed data
        $transformedRequest = clone $request;
        $transformedRequest->merge($data);

        return $transformedRequest;
    }

    /**
     * Transform response for mobile consumption
     */
    private function transformResponse(array $response, string $service): array
    {
        // Mobile-specific response transformations
        return match($service) {
            'auth' => $this->transformAuthResponse($response),
            'payments' => $this->transformPaymentResponse($response),
            'inventory' => $this->transformInventoryResponse($response),
            'analytics' => $this->transformAnalyticsResponse($response),
            'reports' => $this->transformReportResponse($response),
            default => $response
        };
    }

    /**
     * Generate cache key for request
     */
    private function generateCacheKey(Request $request, string $service): string
    {
        $user = auth()->user();
        $params = $request->query();
        ksort($params);
        
        return sprintf(
            'api_cache:%s:%s:%s:%s',
            $service,
            $user ? $user->id : 'anonymous',
            $request->getPathInfo(),
            md5(serialize($params))
        );
    }

    /**
     * Check if request is cacheable
     */
    private function isCacheable(Request $request, string $service): bool
    {
        // Only cache GET requests
        if ($request->method() !== 'GET') {
            return false;
        }

        // Service-specific cache rules
        return match($service) {
            'analytics' => true,
            'reports' => true,
            'inventory' => true,
            'auth' => false,
            'payments' => false,
            'sync' => false,
            default => false
        };
    }

    /**
     * Get cache TTL for service
     */
    private function getCacheTTL(string $service): int
    {
        return match($service) {
            'analytics' => 300, // 5 minutes
            'reports' => 900,   // 15 minutes
            'inventory' => 600, // 10 minutes
            default => 300      // 5 minutes default
        };
    }

    /**
     * Log API request for monitoring
     */
    private function logRequest(Request $request, string $service, string $status, float $duration, string $error = null): void
    {
        ApiRequest::create([
            'service' => $service,
            'method' => $request->method(),
            'path' => $request->getPathInfo(),
            'user_id' => auth()->id(),
            'client_type' => $this->getClientType($request),
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => $status,
            'response_time' => $duration,
            'error_message' => $error,
            'request_id' => $request->header('X-Request-ID'),
            'timestamp' => now()
        ]);
    }

    /**
     * Format success response
     */
    private function formatSuccessResponse(array $data, array $meta = []): array
    {
        return [
            'success' => true,
            'data' => $data,
            'meta' => array_merge([
                'timestamp' => now()->toISOString(),
                'version' => '1.0'
            ], $meta)
        ];
    }

    /**
     * Format error response
     */
    private function formatErrorResponse(string $code, string $message, int $httpCode, array $details = []): array
    {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'http_code' => $httpCode,
                'details' => $details
            ],
            'meta' => [
                'timestamp' => now()->toISOString(),
                'version' => '1.0'
            ]
        ];
    }

    // Helper methods
    private function getClientType(Request $request): string
    {
        $userAgent = $request->userAgent();
        
        if (str_contains($userAgent, 'VitalvidaMobile')) {
            return 'mobile';
        } elseif (str_contains($userAgent, 'VitalvidaWeb')) {
            return 'web';
        } else {
            return 'unknown';
        }
    }

    private function getRateLimits($user, string $service, string $clientType): array
    {
        // Check for custom rate limits for user/service
        $customLimit = RateLimitRule::where('user_id', $user->id)
            ->where('service', $service)
            ->where('client_type', $clientType)
            ->first();

        if ($customLimit) {
            return [
                'requests' => $customLimit->max_requests,
                'window' => $customLimit->window_seconds
            ];
        }

        // Return default limits
        return self::DEFAULT_RATE_LIMITS[$service] ?? self::DEFAULT_RATE_LIMITS['mobile'];
    }

    // Transformation methods (placeholder implementations)
    private function transformPaymentRequest(array $data): array { return $data; }
    private function transformInventoryRequest(array $data): array { return $data; }
    private function transformReportRequest(array $data): array { return $data; }
    private function transformAuthResponse(array $response): array { return $response; }
    private function transformPaymentResponse(array $response): array { return $response; }
    private function transformInventoryResponse(array $response): array { return $response; }
    private function transformAnalyticsResponse(array $response): array { return $response; }
    private function transformReportResponse(array $response): array { return $response; }
    private function validateRequest(Request $request, string $service): array { return ['valid' => true]; }
} 
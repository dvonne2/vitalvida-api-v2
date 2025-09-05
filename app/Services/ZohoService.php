<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Exception;

class ZohoService
{
    private $clientId;
    private $clientSecret;
    private $redirectUri;
    private $baseUrl;
    private $accountsUrl;

    public function __construct()
    {
        // Use your existing services.php config structure
        $this->clientId = config('services.zoho.client_id');
        $this->clientSecret = config('services.zoho.client_secret');
        $this->redirectUri = config('zoho.redirect_uri', 'http://localhost:8000/auth/zoho/callback');
        $this->baseUrl = env('ZOHO_API_DOMAIN', 'https://www.zohoapis.com') . '/inventory/v1';
        $this->accountsUrl = 'https://accounts.zoho.com';
    }

    /**
     * Refresh the access token using the refresh token
     */
    public function refreshToken($refreshToken = null)
    {
        try {
            // Get refresh token from parameter or cache
            $refreshToken = $refreshToken ?: Cache::get('zoho_refresh_token');
            
            if (!$refreshToken) {
                throw new Exception('No refresh token available for token refresh');
            }

            Log::info('Attempting to refresh Zoho access token', [
                'refresh_token_preview' => substr($refreshToken, 0, 10) . '...'
            ]);

            // Prepare refresh token request
            $response = Http::asForm()->post("{$this->accountsUrl}/oauth/v2/token", [
                'refresh_token' => $refreshToken,
                'client_id' => $this->clientId,
                'client_secret' => $this->clientSecret,
                'grant_type' => 'refresh_token'
            ]);

            // Check if request was successful
            if (!$response->successful()) {
                Log::error('Zoho token refresh failed', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'headers' => $response->headers()
                ]);
                
                throw new Exception("Token refresh failed: HTTP {$response->status()}");
            }

            $tokenData = $response->json();

            // Validate response structure
            if (!isset($tokenData['access_token'])) {
                Log::error('Invalid token refresh response', ['response' => $tokenData]);
                throw new Exception('Invalid response: missing access_token');
            }

            // Cache the new tokens
            $expiresIn = $tokenData['expires_in'] ?? 3600; // Default 1 hour
            $cacheExpiry = now()->addSeconds($expiresIn - 300); // 5 minutes buffer

            Cache::put('zoho_access_token', $tokenData['access_token'], $cacheExpiry);
            
            // Update refresh token if provided in response
            if (isset($tokenData['refresh_token'])) {
                Cache::put('zoho_refresh_token', $tokenData['refresh_token'], now()->addDays(365));
                Log::info('Refresh token updated');
            }

            Log::info('Zoho access token refreshed successfully', [
                'expires_in' => $expiresIn,
                'token_preview' => substr($tokenData['access_token'], 0, 10) . '...',
                'cached_until' => $cacheExpiry->toDateTimeString()
            ]);

            return [
                'success' => true,
                'access_token' => $tokenData['access_token'],
                'refresh_token' => $tokenData['refresh_token'] ?? $refreshToken,
                'expires_in' => $expiresIn,
                'token_type' => $tokenData['token_type'] ?? 'Bearer',
                'cached_until' => $cacheExpiry
            ];

        } catch (Exception $e) {
            Log::error('Token refresh error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get a valid access token, refreshing if necessary
     */
    public function getAccessToken()
    {
        // Try to get cached token first
        $accessToken = Cache::get('zoho_access_token');
        
        if ($accessToken) {
            return $accessToken;
        }

        // Token expired or missing, try to refresh
        Log::info('Access token missing/expired, attempting refresh');
        
        $refreshResult = $this->refreshToken();
        
        if ($refreshResult['success']) {
            return $refreshResult['access_token'];
        }

        Log::warning('Unable to refresh token', ['error' => $refreshResult['error']]);
        return null;
    }

    /**
     * Check if tokens are configured and available
     */
    public function getTokenStatus()
    {
        $accessToken = Cache::get('zoho_access_token');
        $refreshToken = Cache::get('zoho_refresh_token');
        
        return [
            'has_access_token' => !empty($accessToken),
            'has_refresh_token' => !empty($refreshToken),
            'access_token_length' => $accessToken ? strlen($accessToken) : 0,
            'refresh_token_length' => $refreshToken ? strlen($refreshToken) : 0,
            'client_configured' => !empty($this->clientId) && !empty($this->clientSecret),
            'cache_ttl_access' => Cache::get('zoho_access_token') ? 'Available' : 'Expired/Missing',
            'cache_ttl_refresh' => Cache::get('zoho_refresh_token') ? 'Available' : 'Missing'
        ];
    }

    /**
     * Test the refresh token functionality with dummy data
     */
    public function testRefreshWithDummyTokens()
    {
        $testResults = [];
        
        // Test 1: Missing refresh token
        $testResults['test_missing_token'] = $this->refreshToken('');
        
        // Test 2: Invalid refresh token format
        $testResults['test_invalid_token'] = $this->refreshToken('invalid_dummy_token_123');
        
        // Test 3: Well-formed but fake token
        $testResults['test_fake_token'] = $this->refreshToken('1000.dummy12345678901234567890123456789012345678901234567890.fake_token_for_testing');
        
        // Test 4: Current configuration status
        $testResults['config_status'] = [
            'client_id_set' => !empty($this->clientId),
            'client_secret_set' => !empty($this->clientSecret),
            'base_url' => $this->baseUrl,
            'accounts_url' => $this->accountsUrl
        ];
        
        return $testResults;
    }

    /**
     * Make authenticated API request to Zoho Inventory
     */
    public function makeApiRequest($endpoint, $method = 'GET', $data = [])
    {
        $accessToken = $this->getAccessToken();
        
        if (!$accessToken) {
            return ['error' => 'No valid access token available'];
        }

        $url = $this->baseUrl . '/' . ltrim($endpoint, '/');
        
        $request = Http::withHeaders([
            'Authorization' => 'Zoho-oauthtoken ' . $accessToken,
            'Content-Type' => 'application/json'
        ]);

        try {
            if ($method === 'GET') {
                $response = $request->get($url, $data);
            } elseif ($method === 'POST') {
                $response = $request->post($url, $data);
            } elseif ($method === 'PUT') {
                $response = $request->put($url, $data);
            } elseif ($method === 'DELETE') {
                $response = $request->delete($url, $data);
            } else {
                return ['error' => 'Unsupported HTTP method'];
            }

            // If token expired (401), try refresh once
            if ($response->status() === 401) {
                Log::info('Access token expired, attempting refresh');
                
                $refreshResult = $this->refreshToken();
                if ($refreshResult['success']) {
                    // Retry with new token
                    $request = $request->withHeaders([
                        'Authorization' => 'Zoho-oauthtoken ' . $refreshResult['access_token']
                    ]);
                    
                    if ($method === 'GET') {
                        $response = $request->get($url, $data);
                    } elseif ($method === 'POST') {
                        $response = $request->post($url, $data);
                    } elseif ($method === 'PUT') {
                        $response = $request->put($url, $data);
                    } elseif ($method === 'DELETE') {
                        $response = $request->delete($url, $data);
                    }
                }
            }

            return [
                'success' => $response->successful(),
                'status' => $response->status(),
                'data' => $response->json(),
                'headers' => $response->headers()
            ];

        } catch (Exception $e) {
            Log::error('Zoho API request failed', [
                'endpoint' => $endpoint,
                'method' => $method,
                'error' => $e->getMessage()
            ]);
            
            return ['error' => $e->getMessage()];
        }
    }
}

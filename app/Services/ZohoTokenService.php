<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZohoTokenService
{
    public static function refreshAccessToken()
    {
        try {
            $response = Http::post('https://accounts.zoho.com/oauth/v2/token', [
                'refresh_token' => config('zoho.refresh_token'),
                'client_id' => config('zoho.client_id'),
                'client_secret' => config('zoho.client_secret'),
                'grant_type' => 'refresh_token'
            ]);

            if ($response->successful()) {
                $data = $response->json();
                
                Log::info('Zoho token refreshed successfully');
                
                return $data['access_token'];
            }

            throw new Exception('Failed to refresh Zoho token');

        } catch (Exception $e) {
            Log::error('Token refresh failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }
}

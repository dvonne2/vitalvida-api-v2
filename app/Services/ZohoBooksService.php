<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZohoBooksService
{
    private $baseUrl;
    private $orgId;
    private $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('zoho.books_base_url');
        $this->orgId = config('zoho.org_id');
        $this->accessToken = config('zoho.access_token');
    }

    private function makeRequest($method, $endpoint, $data = [])
    {
        try {
            $url = $this->baseUrl . $endpoint;
            
            $response = Http::withHeaders([
                'Authorization' => 'Zoho-oauthtoken ' . $this->accessToken,
                'Content-Type' => 'application/json',
            ])->timeout(30);

            if ($method === 'GET') {
                $response = $response->get($url, array_merge(['organization_id' => $this->orgId], $data));
            } else {
                $data['organization_id'] = $this->orgId;
                $response = $response->$method($url, $data);
            }

            return $response->successful() ? $response->json() : null;

        } catch (Exception $e) {
            Log::error('Zoho Books Service Exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);
            return null;
        }
    }

    public function getJournals($filters = [])
    {
        return $this->makeRequest('GET', '/journals', $filters);
    }

    public function getInvoices($filters = [])
    {
        return $this->makeRequest('GET', '/invoices', $filters);
    }

    public function getAccounts($filters = [])
    {
        return $this->makeRequest('GET', '/chartofaccounts', $filters);
    }
}

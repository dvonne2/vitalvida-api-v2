<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class ZohoInventoryService
{
    private $baseUrl;
    private $orgId;
    private $accessToken;

    public function __construct()
    {
        $this->baseUrl = config('zoho.inventory_base_url');
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

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('Zoho API Error', [
                'status' => $response->status(),
                'response' => $response->body(),
                'endpoint' => $endpoint
            ]);

            throw new Exception('Zoho API request failed: ' . $response->status());

        } catch (Exception $e) {
            Log::error('Zoho Service Exception', [
                'message' => $e->getMessage(),
                'endpoint' => $endpoint
            ]);
            throw $e;
        }
    }

    public function getItems($filters = [])
    {
        return $this->makeRequest('GET', '/items', $filters);
    }

    public function getWarehouses()
    {
        return $this->makeRequest('GET', '/warehouses');
    }

    public function getBins($warehouseId = null)
    {
        $endpoint = $warehouseId ? "/warehouses/{$warehouseId}/bins" : '/bins';
        return $this->makeRequest('GET', $endpoint);
    }

    public function getInventoryAdjustments($filters = [])
    {
        return $this->makeRequest('GET', '/inventoryadjustments', $filters);
    }

    public function createInventoryAdjustment($data)
    {
        return $this->makeRequest('POST', '/inventoryadjustments', $data);
    }

    public function createPackage($data)
    {
        return $this->makeRequest('POST', '/packages', $data);
    }

    public function createTransferOrder($data)
    {
        return $this->makeRequest('POST', '/transferorders', $data);
    }

    public function getStorageLocations($warehouseId = null)
    {
        $endpoint = $warehouseId ? "/settings/warehouses/{$warehouseId}/storagelocations" : '/storagelocations';
        return $this->makeRequest('GET', $endpoint);
    }

    public function getStockByStorage($itemId, $warehouseId = null)
    {
        $filters = ['item_id' => $itemId];
        if ($warehouseId) {
            $filters['warehouse_id'] = $warehouseId;
        }
        return $this->makeRequest('GET', '/autocomplete/storages', $filters);
    }

    public function createStorageLocation($data)
    {
        return $this->makeRequest('POST', '/storagelocations', $data);
    }

    public function updateStorageLocation($storageId, $data)
    {
        return $this->makeRequest('PUT', "/storagelocations/{$storageId}", $data);
    }

    public function deleteStorageLocation($storageId)
    {
        return $this->makeRequest('DELETE', "/storagelocations/{$storageId}");
    }
}


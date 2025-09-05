<?php

namespace App\Services;

use App\Models\Bin;
use App\Services\ZohoService;
use Illuminate\Support\Facades\Log;

class BinSyncService
{
    private $zohoService;

    public function __construct(ZohoService $zohoService)
    {
        $this->zohoService = $zohoService;
    }

    public function syncAllBins()
    {
        Log::info('Starting structural bin sync between Laravel and Zoho');

        $results = [
            'laravel_bins_synced' => 0,
            'zoho_bins_created' => 0,
            'zoho_bins_updated' => 0,
            'errors' => []
        ];

        // Get all warehouses from Zoho
        $warehouses = $this->getZohoWarehouses();
        if (empty($warehouses)) {
            throw new \Exception('No warehouses found in Zoho');
        }

        // Sync each Laravel bin with Zoho
        $laravelBins = Bin::with('user')->get();
        
        foreach ($laravelBins as $bin) {
            try {
                $syncResult = $this->syncSingleBin($bin, $warehouses);
                
                if ($syncResult['created']) {
                    $results['zoho_bins_created']++;
                } elseif ($syncResult['updated']) {
                    $results['zoho_bins_updated']++;
                }
                
                $results['laravel_bins_synced']++;
                
            } catch (\Exception $e) {
                $results['errors'][] = "Bin {$bin->name}: " . $e->getMessage();
                Log::error("Failed to sync bin {$bin->name}", ['error' => $e->getMessage()]);
            }
        }

        Log::info('Structural bin sync completed', $results);
        return $results;
    }

    private function syncSingleBin($bin, $warehouses)
    {
        // Implementation for individual bin sync
        return ['created' => false, 'updated' => false];
    }

    private function getZohoWarehouses()
    {
        $response = $this->zohoService->makeApiRequest('settings/warehouses');
        
        if (!$response['success']) {
            throw new \Exception("Failed to fetch warehouses from Zoho");
        }

        return $response['data']['warehouses'] ?? [];
    }
}

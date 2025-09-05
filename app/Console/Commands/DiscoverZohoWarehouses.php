<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZohoService;
use Exception;

class DiscoverZohoWarehouses extends Command
{
    protected $signature = 'app:discover-zoho-warehouses {--locations : Discover locations instead of warehouses}';
    protected $description = 'Fetches all warehouses/locations from Zoho Inventory';

    protected $zohoService;

    public function __construct(ZohoService $zohoService)
    {
        parent::__construct();
        $this->zohoService = $zohoService;
    }

    public function handle()
    {
        $this->info('🔍 Running Zoho discovery...');

        try {
            if ($this->option('locations')) {
                return $this->discoverLocations();
            } else {
                return $this->discoverWarehouses();
            }
        } catch (Exception $e) {
            $this->error('❌ Discovery failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function discoverLocations(): int
    {
        $this->info('📍 Discovering Zoho Locations...');
        
        $locationsResponse = $this->zohoService->getLocations();
        $locations = $locationsResponse['locations'] ?? [];
        
        if (empty($locations)) {
            $this->warn('No locations found');
            return 0;
        }
        
        $this->info("Found " . count($locations) . " locations:");
        
        $this->table(
            ['Location Name', 'Location ID', 'Type'],
            collect($locations)->map(function ($location) {
                return [
                    $location['location_name'],
                    $location['location_id'],
                    $location['location_type'] ?? 'N/A'
                ];
            })->toArray()
        );

        // Specifically highlight FHG location
        $fhgLocation = collect($locations)->first(function ($location) {
            return stripos($location['location_name'], 'FHG') !== false;
        });

        if ($fhgLocation) {
            $this->info("\n🎯 FHG Delivery agents found!");
            $this->line("Name: {$fhgLocation['location_name']}");
            $this->line("ID: {$fhgLocation['location_id']}");
            
            // Show zones for FHG
            try {
                $zonesResponse = $this->zohoService->getZones($fhgLocation['location_id']);
                $zones = $zonesResponse['zones'] ?? [];
                $this->line("Zones: " . count($zones));
                
                if (count($zones) > 0) {
                    $this->line("Zone examples: " . 
                        collect($zones)->take(3)->pluck('zone_name')->implode(', ') . 
                        (count($zones) > 3 ? '...' : '')
                    );
                }
            } catch (Exception $e) {
                $this->warn("Could not fetch zones: " . $e->getMessage());
            }
        }

        return 0;
    }

    private function discoverWarehouses(): int
    {
        $this->info('🏭 Discovering Zoho Warehouses (Old API)...');
        
        $warehousesResponse = $this->zohoService->getWarehouses();
        $warehouses = $warehousesResponse['warehouses'] ?? [];
        
        if (empty($warehouses)) {
            $this->warn('No warehouses found. Try --locations for new API.');
            return 0;
        }
        
        $this->table(
            ['Warehouse Name', 'Warehouse ID'],
            collect($warehouses)->map(function ($warehouse) {
                return [
                    $warehouse['warehouse_name'],
                    $warehouse['warehouse_id']
                ];
            })->toArray()
        );

        return 0;
    }
}

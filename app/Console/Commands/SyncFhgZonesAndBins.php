<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ZohoService;
use App\Models\Bin;
use Exception;

class SyncFhgZonesAndBins extends Command
{
    protected $signature = 'sync:fhg-zones-bins {--dry-run : Show what would be synced without making changes}';
    protected $description = 'Sync FHG Delivery agents zones and bins from Zoho to local database';

    protected $zohoService;

    public function __construct(ZohoService $zohoService)
    {
        parent::__construct();
        $this->zohoService = $zohoService;
    }

    public function handle()
    {
        $this->info('🚀 Starting FHG Zones & Bins sync...');
        
        $isDryRun = $this->option('dry-run');
        if ($isDryRun) {
            $this->warn('🔍 DRY RUN MODE - No changes will be made');
        }

        try {
            // Step 1: Verify FHG location exists
            $this->verifyFHGLocation();
            
            // Step 2: Fetch and sync zones
            $zones = $this->fetchFHGZones();
            
            // Step 3: For each zone, fetch and sync bins
            $totalCreated = 0;
            $totalUpdated = 0;
            
            foreach ($zones as $zone) {
                $result = $this->syncZoneAndBins($zone, $isDryRun);
                $totalCreated += $result['created'];
                $totalUpdated += $result['updated'];
            }
            
            // Step 4: Show summary
            $this->showSummary($zones, $totalCreated, $totalUpdated, $isDryRun);
            
            return 0;
            
        } catch (Exception $e) {
            $this->error('❌ Sync failed: ' . $e->getMessage());
            return 1;
        }
    }

    private function verifyFHGLocation(): void
    {
        $this->info('📍 Verifying FHG Delivery agents location...');
        
        $fhgLocationId = config('services.zoho.fhg_location_id');
        $fhgLocationName = config('services.zoho.fhg_location_name');
        
        if (!$fhgLocationId) {
            throw new Exception('FHG location ID not configured in .env');
        }
        
        // Fetch all locations to verify FHG exists
        $locationsResponse = $this->zohoService->getLocations();
        $locations = $locationsResponse['locations'] ?? [];
        
        $fhgLocation = collect($locations)->first(function ($location) use ($fhgLocationId) {
            return $location['location_id'] === $fhgLocationId;
        });
        
        if (!$fhgLocation) {
            throw new Exception("FHG location with ID {$fhgLocationId} not found in Zoho");
        }
        
        $this->line("✅ Found: {$fhgLocation['location_name']} (ID: {$fhgLocationId})");
    }

    private function fetchFHGZones(): array
    {
        $this->info('🗂️ Fetching FHG zones...');
        
        $zonesResponse = $this->zohoService->getFHGZones();
        $zones = $zonesResponse['zones'] ?? [];
        
        if (empty($zones)) {
            throw new Exception('No zones found for FHG Delivery agents location');
        }
        
        $this->line("Found " . count($zones) . " zones");
        
        return $zones;
    }

    private function syncZoneAndBins(array $zone, bool $isDryRun): array
    {
        $zoneName = $zone['zone_name'];
        $zoneId = $zone['zone_id'];
        
        $this->line("📦 Processing zone: {$zoneName}");
        
        $created = 0;
        $updated = 0;
        
        try {
            // Fetch bins for this zone
            $binsResponse = $this->zohoService->getBinsForZone($zoneId);
            $bins = $binsResponse['storagelocations'] ?? [];
            
            if (empty($bins)) {
                $this->warn("  ⚠️ No bins found for zone: {$zoneName}");
                return ['created' => 0, 'updated' => 0];
            }
            
            if (count($bins) > 1) {
                $this->warn("  ⚠️ Zone {$zoneName} has " . count($bins) . " bins (expected 1)");
            }
            
            foreach ($bins as $bin) {
                $result = $this->syncBin($zone, $bin, $isDryRun);
                if ($result['created']) {
                    $created++;
                    $this->line("    ✅ Created bin: {$result['bin_name']}");
                } else {
                    $updated++;
                    $this->line("    🔄 Updated bin: {$result['bin_name']}");
                }
            }
            
        } catch (Exception $e) {
            $this->error("  ❌ Failed to process zone {$zoneName}: " . $e->getMessage());
        }
        
        return ['created' => $created, 'updated' => $updated];
    }

    private function syncBin(array $zone, array $bin, bool $isDryRun): array
    {
        $fhgLocationId = config('services.zoho.fhg_location_id');
        $zoneName = $zone['zone_name'];
        $zoneId = $zone['zone_id'];
        $binName = $bin['storage_name'];
        $binId = $bin['storage_id'];
        
        // Try to find existing bin by zoho_bin_id
        $existingBin = Bin::where('zoho_bin_id', $binId)->first();
        
        // Prepare bin data
        $binData = [
            'name' => $binName,
            'zoho_location_id' => $fhgLocationId,
            'zoho_zone_id' => $zoneId,
            'zoho_bin_id' => $binId,
            'location' => config('services.zoho.fhg_location_name'),
            'status' => 'active',
            'type' => 'delivery_agent',
            'metadata' => [
                'zoho_zone_name' => $zoneName,
                'zoho_bin_name' => $binName,
                'synced_at' => now()->toISOString()
            ]
        ];
        
        // Try to detect Nigerian state from zone name
        $detectedState = $this->detectStateFromZoneName($zoneName);
        if ($detectedState) {
            $binData['state'] = $detectedState;
        }
        
        $created = false;
        
        if (!$isDryRun) {
            if ($existingBin) {
                $existingBin->update($binData);
            } else {
                Bin::create($binData);
                $created = true;
            }
        } else {
            $created = !$existingBin;
        }
        
        return [
            'created' => $created,
            'bin_name' => $binName
        ];
    }

    private function detectStateFromZoneName(string $zoneName): ?string
    {
        $states = Bin::getNigerianStates();
        
        foreach ($states as $state) {
            // Check if zone name contains the state name
            if (stripos($zoneName, $state) !== false) {
                return $state;
            }
            
            // Check for state name repeated (like "Lagos Lagos")
            $stateWords = explode(' ', $state);
            foreach ($stateWords as $word) {
                if (stripos($zoneName, $word . ' ' . $word) !== false) {
                    return $state;
                }
            }
        }
        
        return null;
    }

    private function showSummary(array $zones, int $created, int $updated, bool $isDryRun): void
    {
        $mode = $isDryRun ? ' (DRY RUN)' : '';
        
        $this->info("\n🎉 FHG sync completed{$mode}!");
        
        $this->table(['Metric', 'Count'], [
            ['Zones processed', count($zones)],
            ['Bins created', $created],
            ['Bins updated', $updated],
            ['Total bins', $created + $updated]
        ]);
        
        if ($isDryRun) {
            $this->info('💡 Run without --dry-run to apply changes');
        }
    }
}

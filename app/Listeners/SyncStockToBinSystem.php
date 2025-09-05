<?php

namespace App\Listeners;

use App\Events\StockAllocatedEvent;
use App\Models\Bin;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Services\IntegrationService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SyncStockToBinSystem implements ShouldQueue
{
    use InteractsWithQueue;

    public $queue = 'high-priority-sync';
    public $tries = 3;
    public $backoff = [10, 30, 60];

    private $integrationService;

    public function __construct(IntegrationService $integrationService)
    {
        $this->integrationService = $integrationService;
    }

    public function handle(StockAllocatedEvent $event)
    {
        try {
            Log::info('Processing stock allocation sync', [
                'allocation_id' => $event->allocation->id,
                'agent_id' => $event->agent->id,
                'product_id' => $event->product->id,
                'quantity' => $event->allocation->quantity
            ]);

            // Sync stock allocation to bin system
            $syncResult = $this->integrationService->syncBinStock([
                'agent_id' => $event->agent->id,
                'product_code' => $event->product->code,
                'quantity' => $event->allocation->quantity,
                'allocated_at' => $event->allocation->allocated_at
            ]);

            // Update bin metadata
            $this->updateBinMetadata($event);

            // Check for capacity warnings
            $this->checkCapacityWarnings($event);

            // Update zone inventory statistics
            $this->updateZoneInventoryStats($event);

            Log::info('Stock allocation sync completed', [
                'allocation_id' => $event->allocation->id,
                'sync_result' => $syncResult
            ]);

        } catch (\Exception $e) {
            Log::error('Stock allocation sync failed', [
                'allocation_id' => $event->allocation->id,
                'error' => $e->getMessage(),
                'attempt' => $this->attempts()
            ]);

            throw $e;
        }
    }

    private function updateBinMetadata($event)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $event->agent->id)->first();
        
        if (!$roleAgent) {
            return;
        }

        $bin = Bin::where('da_id', $roleAgent->id)
            ->where('product_sku', $event->product->code)
            ->first();

        if ($bin) {
            $bin->update([
                'last_allocation_at' => $event->allocation->allocated_at,
                'allocation_count' => $bin->allocation_count + 1,
                'total_allocated_today' => $bin->total_allocated_today + $event->allocation->quantity,
                'supplier_name' => $event->product->supplier->company_name ?? 'Unknown',
                'product_category' => $event->product->category ?? 'General'
            ]);

            // Update utilization percentage
            $utilizationRate = $bin->max_capacity > 0 ? 
                ($bin->current_stock / $bin->max_capacity) * 100 : 0;
            
            $bin->update(['utilization_rate' => round($utilizationRate, 2)]);
        }
    }

    private function checkCapacityWarnings($event)
    {
        $roleAgent = RoleDeliveryAgent::where('external_id', $event->agent->id)->first();
        
        if (!$roleAgent) {
            return;
        }

        $bin = Bin::where('da_id', $roleAgent->id)
            ->where('product_sku', $event->product->code)
            ->first();

        if ($bin && $bin->max_capacity > 0) {
            $utilizationRate = ($bin->current_stock / $bin->max_capacity) * 100;

            // Create warnings based on utilization
            if ($utilizationRate >= 90) {
                $this->createCapacityAlert($bin, $event->agent, 'critical', $utilizationRate);
            } elseif ($utilizationRate >= 80) {
                $this->createCapacityAlert($bin, $event->agent, 'warning', $utilizationRate);
            }
        }
    }

    private function createCapacityAlert($bin, $agent, $severity, $utilizationRate)
    {
        \App\Models\SystemAlert::create([
            'alert_type' => 'capacity_warning',
            'severity' => $severity,
            'title' => 'Bin Capacity Alert',
            'message' => "Agent {$agent->name} bin for {$bin->product_name} is {$utilizationRate}% full",
            'data' => json_encode([
                'bin_id' => $bin->id,
                'agent_id' => $agent->id,
                'product_sku' => $bin->product_sku,
                'utilization_rate' => $utilizationRate,
                'current_stock' => $bin->current_stock,
                'max_capacity' => $bin->max_capacity
            ]),
            'requires_action' => $severity === 'critical',
            'zone' => $this->mapLocationToZone($agent->location),
            'created_at' => now()
        ]);
    }

    private function updateZoneInventoryStats($event)
    {
        $zone = $this->mapLocationToZone($event->agent->location);
        
        // Update zone inventory cache
        $cacheKey = "zone_inventory_stats:{$zone}";
        $stats = \Cache::get($cacheKey, [
            'total_allocations_today' => 0,
            'total_value_allocated' => 0,
            'active_agents' => 0,
            'last_updated' => now()
        ]);

        $stats['total_allocations_today'] += $event->allocation->quantity;
        $stats['total_value_allocated'] += ($event->allocation->quantity * $event->product->unit_price);
        $stats['last_updated'] = now();

        \Cache::put($cacheKey, $stats, 3600); // Cache for 1 hour
    }

    private function mapLocationToZone($location): string
    {
        $zoneMapping = [
            'Lagos' => 'Lagos',
            'Victoria Island' => 'Lagos', 
            'Ikeja' => 'Lagos',
            'Lekki' => 'Lagos',
            'Surulere' => 'Lagos',
            'Abuja' => 'Abuja',
            'FCT' => 'Abuja',
            'Kano' => 'Kano',
            'Port Harcourt' => 'Port Harcourt',
            'Rivers' => 'Port Harcourt'
        ];
        
        foreach ($zoneMapping as $keyword => $zone) {
            if (stripos($location, $keyword) !== false) {
                return $zone;
            }
        }
        
        return 'Lagos';
    }

    public function failed(\Throwable $exception)
    {
        Log::critical('Stock allocation sync listener failed permanently', [
            'allocation_id' => $this->event->allocation->id ?? 'unknown',
            'error' => $exception->getMessage(),
            'attempts' => $this->tries
        ]);
    }
}

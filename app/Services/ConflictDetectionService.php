<?php

namespace App\Services;

use App\Models\VitalVidaInventory\DeliveryAgent as VitalVidaDeliveryAgent;
use App\Models\DeliveryAgent as RoleDeliveryAgent;
use App\Models\VitalVidaInventory\Product as VitalVidaProduct;
use App\Models\Bin;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ConflictDetectionService
{
    private $redis;
    private $conflictThresholds;

    public function __construct()
    {
        $this->redis = null; // Temporarily disable Redis
        $this->conflictThresholds = [
            'agent_data_mismatch' => 5, // 5% tolerance
            'stock_variance' => 10, // 10 units tolerance
            'compliance_score_diff' => 15, // 15 points tolerance
            'status_sync_delay' => 300, // 5 minutes max delay
            'performance_variance' => 0.5 // 0.5 rating points tolerance
        ];
    }

    /**
     * Detect all types of conflicts between VitalVida and Role systems
     */
    public function detectAllConflicts(): array
    {
        $conflicts = [];

        try {
            // Agent data conflicts
            $agentConflicts = $this->detectAgentConflicts();
            $conflicts['agent_conflicts'] = $agentConflicts;

            // Stock/inventory conflicts
            $stockConflicts = $this->detectStockConflicts();
            $conflicts['stock_conflicts'] = $stockConflicts;

            // Compliance conflicts
            $complianceConflicts = $this->detectComplianceConflicts();
            $conflicts['compliance_conflicts'] = $complianceConflicts;

            // Sync timing conflicts
            $timingConflicts = $this->detectSyncTimingConflicts();
            $conflicts['timing_conflicts'] = $timingConflicts;

            // Data integrity conflicts
            $integrityConflicts = $this->detectDataIntegrityConflicts();
            $conflicts['integrity_conflicts'] = $integrityConflicts;

            // Cache conflicts summary
            $this->cacheConflictSummary($conflicts);

            Log::info('Conflict detection completed', [
                'total_conflicts' => array_sum(array_map('count', $conflicts)),
                'conflict_types' => array_keys($conflicts)
            ]);

            return $conflicts;

        } catch (\Exception $e) {
            Log::error('Conflict detection failed', ['error' => $e->getMessage()]);
            throw $e;
        }
    }

    /**
     * Detect agent data conflicts
     */
    public function detectAgentConflicts(): array
    {
        $conflicts = [];

        $vitalAgents = VitalVidaDeliveryAgent::all();
        
        foreach ($vitalAgents as $vitalAgent) {
            $roleAgent = RoleDeliveryAgent::where('external_id', $vitalAgent->id)->first();
            
            if (!$roleAgent) {
                $conflicts[] = [
                    'type' => 'missing_role_agent',
                    'severity' => 'high',
                    'vital_agent_id' => $vitalAgent->id,
                    'vital_agent_name' => $vitalAgent->name,
                    'description' => 'VitalVida agent exists but not found in Role system',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
                continue;
            }

            // Check name mismatch
            if ($vitalAgent->name !== $roleAgent->agent_name) {
                $conflicts[] = [
                    'type' => 'name_mismatch',
                    'severity' => 'medium',
                    'vital_agent_id' => $vitalAgent->id,
                    'role_agent_id' => $roleAgent->id,
                    'vital_value' => $vitalAgent->name,
                    'role_value' => $roleAgent->agent_name,
                    'description' => 'Agent name differs between systems',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }

            // Check performance/rating mismatch
            $performanceDiff = abs($vitalAgent->rating - ($roleAgent->performance_score ?? 0));
            if ($performanceDiff > $this->conflictThresholds['performance_variance']) {
                $conflicts[] = [
                    'type' => 'performance_mismatch',
                    'severity' => $performanceDiff > 1.0 ? 'high' : 'medium',
                    'vital_agent_id' => $vitalAgent->id,
                    'role_agent_id' => $roleAgent->id,
                    'vital_value' => $vitalAgent->rating,
                    'role_value' => $roleAgent->performance_score,
                    'variance' => $performanceDiff,
                    'description' => 'Performance rating differs significantly',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }

            // Check zone/location mismatch
            $vitalZone = $this->mapLocationToZone($vitalAgent->location);
            if ($vitalZone !== $roleAgent->zone) {
                $conflicts[] = [
                    'type' => 'zone_mismatch',
                    'severity' => 'medium',
                    'vital_agent_id' => $vitalAgent->id,
                    'role_agent_id' => $roleAgent->id,
                    'vital_value' => $vitalZone,
                    'role_value' => $roleAgent->zone,
                    'description' => 'Agent zone assignment differs',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }

            // Check status mismatch
            $vitalStatus = $this->mapVitalVidaStatusToRole($vitalAgent->status);
            if ($vitalStatus !== $roleAgent->status) {
                $conflicts[] = [
                    'type' => 'status_mismatch',
                    'severity' => 'high',
                    'vital_agent_id' => $vitalAgent->id,
                    'role_agent_id' => $roleAgent->id,
                    'vital_value' => $vitalStatus,
                    'role_value' => $roleAgent->status,
                    'description' => 'Agent status differs between systems',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Detect stock/inventory conflicts
     */
    public function detectStockConflicts(): array
    {
        $conflicts = [];

        // Get all bins with their corresponding VitalVida data
        $bins = Bin::with('deliveryAgent')->get();

        foreach ($bins as $bin) {
            if (!$bin->deliveryAgent || !$bin->deliveryAgent->external_id) {
                continue;
            }

            $vitalAgent = VitalVidaDeliveryAgent::find($bin->deliveryAgent->external_id);
            if (!$vitalAgent) {
                continue;
            }

            // Find corresponding VitalVida product
            $vitalProduct = VitalVidaProduct::where('code', $bin->product_sku)->first();
            if (!$vitalProduct) {
                $conflicts[] = [
                    'type' => 'missing_vital_product',
                    'severity' => 'medium',
                    'bin_id' => $bin->id,
                    'product_sku' => $bin->product_sku,
                    'description' => 'Bin product not found in VitalVida system',
                    'detected_at' => now(),
                    'auto_resolvable' => false
                ];
                continue;
            }

            // Check stock variance (if we have allocation data)
            $expectedStock = $this->calculateExpectedStock($vitalAgent->id, $vitalProduct->code);
            if ($expectedStock !== null) {
                $stockVariance = abs($bin->current_stock - $expectedStock);
                if ($stockVariance > $this->conflictThresholds['stock_variance']) {
                    $conflicts[] = [
                        'type' => 'stock_variance',
                        'severity' => $stockVariance > 50 ? 'high' : 'medium',
                        'bin_id' => $bin->id,
                        'agent_id' => $vitalAgent->id,
                        'product_sku' => $bin->product_sku,
                        'bin_stock' => $bin->current_stock,
                        'expected_stock' => $expectedStock,
                        'variance' => $stockVariance,
                        'description' => 'Stock levels differ from expected allocation',
                        'detected_at' => now(),
                        'auto_resolvable' => true
                    ];
                }
            }

            // Check price mismatch
            if ($bin->unit_price != $vitalProduct->unit_price) {
                $conflicts[] = [
                    'type' => 'price_mismatch',
                    'severity' => 'low',
                    'bin_id' => $bin->id,
                    'product_sku' => $bin->product_sku,
                    'bin_price' => $bin->unit_price,
                    'vital_price' => $vitalProduct->unit_price,
                    'description' => 'Product price differs between systems',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Detect compliance conflicts
     */
    public function detectComplianceConflicts(): array
    {
        $conflicts = [];

        $vitalAgents = VitalVidaDeliveryAgent::whereNotNull('compliance_score')->get();

        foreach ($vitalAgents as $vitalAgent) {
            $roleAgent = RoleDeliveryAgent::where('external_id', $vitalAgent->id)->first();
            
            if (!$roleAgent) {
                continue;
            }

            // Check compliance score mismatch
            $complianceDiff = abs(($vitalAgent->compliance_score ?? 100) - ($roleAgent->compliance_score ?? 100));
            if ($complianceDiff > $this->conflictThresholds['compliance_score_diff']) {
                $conflicts[] = [
                    'type' => 'compliance_score_mismatch',
                    'severity' => $complianceDiff > 30 ? 'high' : 'medium',
                    'vital_agent_id' => $vitalAgent->id,
                    'role_agent_id' => $roleAgent->id,
                    'vital_score' => $vitalAgent->compliance_score,
                    'role_score' => $roleAgent->compliance_score,
                    'difference' => $complianceDiff,
                    'description' => 'Compliance scores differ significantly',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }

            // Check for missing enforcement actions
            if ($vitalAgent->status === 'Suspended' && $roleAgent->status !== 'suspended') {
                $conflicts[] = [
                    'type' => 'enforcement_not_synced',
                    'severity' => 'critical',
                    'vital_agent_id' => $vitalAgent->id,
                    'role_agent_id' => $roleAgent->id,
                    'description' => 'Agent suspended in VitalVida but active in Role system',
                    'detected_at' => now(),
                    'auto_resolvable' => true
                ];
            }
        }

        return $conflicts;
    }

    /**
     * Detect sync timing conflicts
     */
    public function detectSyncTimingConflicts(): array
    {
        $conflicts = [];

        // Check for stale sync data
        $staleThreshold = now()->subMinutes(30);
        
        $staleAgents = RoleDeliveryAgent::where('sync_timestamp', '<', $staleThreshold)
            ->whereNotNull('external_id')
            ->get();

        foreach ($staleAgents as $roleAgent) {
            $conflicts[] = [
                'type' => 'stale_sync_data',
                'severity' => 'medium',
                'role_agent_id' => $roleAgent->id,
                'vital_agent_id' => $roleAgent->external_id,
                'last_sync' => $roleAgent->sync_timestamp,
                'age_minutes' => $roleAgent->sync_timestamp ? 
                    now()->diffInMinutes($roleAgent->sync_timestamp) : null,
                'description' => 'Agent data not synced recently',
                'detected_at' => now(),
                'auto_resolvable' => true
            ];
        }

        // Check for failed sync jobs
        $failedJobs = DB::table('failed_jobs')
            ->where('payload', 'like', '%SyncAgent%')
            ->where('failed_at', '>', now()->subHours(24))
            ->get();

        foreach ($failedJobs as $failedJob) {
            $conflicts[] = [
                'type' => 'failed_sync_job',
                'severity' => 'high',
                'job_id' => $failedJob->id,
                'failed_at' => $failedJob->failed_at,
                'exception' => substr($failedJob->exception, 0, 200),
                'description' => 'Sync job failed and requires attention',
                'detected_at' => now(),
                'auto_resolvable' => false
            ];
        }

        return $conflicts;
    }

    /**
     * Detect data integrity conflicts
     */
    public function detectDataIntegrityConflicts(): array
    {
        $conflicts = [];

        // Check for orphaned Role agents
        $orphanedAgents = RoleDeliveryAgent::whereNotNull('external_id')
            ->whereNotExists(function ($query) {
                $query->select(DB::raw(1))
                    ->from('vitalvida_delivery_agents')
                    ->whereRaw('vitalvida_delivery_agents.id = delivery_agents.external_id');
            })->get();

        foreach ($orphanedAgents as $orphan) {
            $conflicts[] = [
                'type' => 'orphaned_role_agent',
                'severity' => 'medium',
                'role_agent_id' => $orphan->id,
                'external_id' => $orphan->external_id,
                'description' => 'Role agent references non-existent VitalVida agent',
                'detected_at' => now(),
                'auto_resolvable' => false
            ];
        }

        // Check for bins without valid agents
        $invalidBins = Bin::whereNotExists(function ($query) {
            $query->select(DB::raw(1))
                ->from('delivery_agents')
                ->whereRaw('delivery_agents.id = bins.da_id');
        })->get();

        foreach ($invalidBins as $bin) {
            $conflicts[] = [
                'type' => 'invalid_bin_agent',
                'severity' => 'high',
                'bin_id' => $bin->id,
                'da_id' => $bin->da_id,
                'description' => 'Bin references non-existent delivery agent',
                'detected_at' => now(),
                'auto_resolvable' => false
            ];
        }

        return $conflicts;
    }

    /**
     * Get conflict summary statistics
     */
    public function getConflictSummary(): array
    {
        $summary = $this->getCachedConflictSummary();
        
        if ($summary) {
            return $summary;
        }

        // If no cached summary, run detection
        $conflicts = $this->detectAllConflicts();
        return $this->generateConflictSummary($conflicts);
    }

    // Helper methods
    private function calculateExpectedStock($agentId, $productCode): ?int
    {
        // This would integrate with allocation tracking
        // For now, return null to skip stock variance checks
        return null;
    }

    private function cacheConflictSummary($conflicts)
    {
        $summary = $this->generateConflictSummary($conflicts);
        if ($this->redis) {
            $this->redis->setex('conflict_summary', 1800, json_encode($summary)); // 30 minutes
        }
    }

    private function generateConflictSummary($conflicts): array
    {
        $summary = [
            'total_conflicts' => 0,
            'by_severity' => ['critical' => 0, 'high' => 0, 'medium' => 0, 'low' => 0],
            'by_type' => [],
            'auto_resolvable' => 0,
            'manual_resolution_required' => 0,
            'last_detection' => now()->toISOString()
        ];

        foreach ($conflicts as $category => $categoryConflicts) {
            foreach ($categoryConflicts as $conflict) {
                $summary['total_conflicts']++;
                $summary['by_severity'][$conflict['severity']]++;
                
                $type = $conflict['type'];
                $summary['by_type'][$type] = ($summary['by_type'][$type] ?? 0) + 1;
                
                if ($conflict['auto_resolvable']) {
                    $summary['auto_resolvable']++;
                } else {
                    $summary['manual_resolution_required']++;
                }
            }
        }

        return $summary;
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

    private function mapVitalVidaStatusToRole($vitalVidaStatus): string
    {
        return match($vitalVidaStatus) {
            'Active' => 'active',
            'Inactive' => 'inactive',
            'On Delivery' => 'on_delivery',
            'Break' => 'on_break',
            'Suspended' => 'suspended',
            'Training Required' => 'training',
            default => 'active'
        };
    }

    private function getCachedConflictSummary()
    {
        if ($this->redis) {
            return Cache::get('conflict_summary_' . date('Y-m-d'), []);
        }
        return [];
    }
}

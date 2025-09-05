<?php

namespace App\Services;

use App\Models\BlindTransfer;
use App\Models\DeliveryAgent;
use App\Models\VitalVidaProduct;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class BlindTransferService
{
    protected $realTimeSyncService;

    public function __construct(RealTimeSyncService $realTimeSyncService)
    {
        $this->realTimeSyncService = $realTimeSyncService;
    }

    /**
     * Orchestrate blind transfer between agents
     */
    public function orchestrateBlindTransfer($fromAgentId, $toAgentId, $productId, $quantity, $orchestratedBy)
    {
        DB::beginTransaction();
        
        try {
            // Generate unique codes
            $transferCode = 'BT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $pickupCode = 'PU-' . strtoupper(Str::random(8));
            $deliveryCode = 'DL-' . strtoupper(Str::random(8));

            // Get agent locations for neutral pickup/delivery points
            $neutralLocations = $this->getNeutralTransferLocations($fromAgentId, $toAgentId);

            // Create blind transfer
            $blindTransfer = BlindTransfer::create([
                'transfer_code' => $transferCode,
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'orchestrated_by' => $orchestratedBy,
                'pickup_location' => $neutralLocations['pickup'],
                'delivery_location' => $neutralLocations['delivery'],
                'transfer_status' => BlindTransfer::STATUS_SCHEDULED,
                'pickup_code' => $pickupCode,
                'delivery_code' => $deliveryCode,
                'pickup_window_start' => now()->addHours(2),
                'pickup_window_end' => now()->addHours(6),
                'delivery_window_start' => now()->addHours(8),
                'delivery_window_end' => now()->addHours(24),
                'transfer_metadata' => [
                    'orchestration_time' => now(),
                    'anonymity_level' => 'high',
                    'tracking_enabled' => true,
                    'violation_monitoring' => true
                ]
            ]);

            // Send anonymous instructions to agents
            $this->sendBlindTransferInstructions($blindTransfer);

            // Log orchestration
            Log::info('Blind transfer orchestrated', [
                'transfer_code' => $transferCode,
                'from_agent' => $fromAgentId,
                'to_agent' => $toAgentId,
                'product' => $productId,
                'quantity' => $quantity
            ]);

            // Real-time sync notification
            $this->realTimeSyncService->broadcastBlindTransferUpdate($blindTransfer);

            DB::commit();
            return $blindTransfer;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Blind transfer orchestration failed', [
                'error' => $e->getMessage(),
                'from_agent' => $fromAgentId,
                'to_agent' => $toAgentId
            ]);
            throw $e;
        }
    }

    /**
     * Process pickup confirmation
     */
    public function confirmPickup($transferCode, $pickupCode, $agentId, $gpsCoordinates = null)
    {
        $transfer = BlindTransfer::where('transfer_code', $transferCode)->firstOrFail();
        
        // Verify pickup code and agent
        if ($transfer->pickup_code !== $pickupCode || $transfer->from_agent_id !== $agentId) {
            $this->flagViolation($transfer, 'unauthorized_pickup_attempt', [
                'attempted_by' => $agentId,
                'provided_code' => $pickupCode,
                'gps_coordinates' => $gpsCoordinates
            ]);
            throw new \Exception('Unauthorized pickup attempt');
        }

        // Check pickup window
        if (now()->isBefore($transfer->pickup_window_start) || now()->isAfter($transfer->pickup_window_end)) {
            $this->flagViolation($transfer, 'pickup_window_violation', [
                'pickup_time' => now(),
                'window_start' => $transfer->pickup_window_start,
                'window_end' => $transfer->pickup_window_end
            ]);
        }

        $transfer->update([
            'transfer_status' => BlindTransfer::STATUS_IN_TRANSIT,
            'picked_up_at' => now(),
            'transfer_metadata' => array_merge($transfer->transfer_metadata ?? [], [
                'pickup_gps' => $gpsCoordinates,
                'pickup_confirmed' => now()
            ])
        ]);

        // Update stock levels
        $this->updateAgentStockForBlindTransfer($agentId, $transfer->product_id, -$transfer->quantity, 'blind_pickup');

        $this->realTimeSyncService->broadcastBlindTransferUpdate($transfer);
        
        return $transfer;
    }

    /**
     * Process delivery confirmation
     */
    public function confirmDelivery($transferCode, $deliveryCode, $agentId, $gpsCoordinates = null)
    {
        $transfer = BlindTransfer::where('transfer_code', $transferCode)->firstOrFail();
        
        // Verify delivery code and agent
        if ($transfer->delivery_code !== $deliveryCode || $transfer->to_agent_id !== $agentId) {
            $this->flagViolation($transfer, 'unauthorized_delivery_attempt', [
                'attempted_by' => $agentId,
                'provided_code' => $deliveryCode,
                'gps_coordinates' => $gpsCoordinates
            ]);
            throw new \Exception('Unauthorized delivery attempt');
        }

        // Check delivery window
        if (now()->isBefore($transfer->delivery_window_start) || now()->isAfter($transfer->delivery_window_end)) {
            $this->flagViolation($transfer, 'delivery_window_violation', [
                'delivery_time' => now(),
                'window_start' => $transfer->delivery_window_start,
                'window_end' => $transfer->delivery_window_end
            ]);
        }

        $transfer->update([
            'transfer_status' => BlindTransfer::STATUS_COMPLETED,
            'delivered_at' => now(),
            'transfer_metadata' => array_merge($transfer->transfer_metadata ?? [], [
                'delivery_gps' => $gpsCoordinates,
                'delivery_confirmed' => now(),
                'total_transit_time' => $transfer->transit_duration
            ])
        ]);

        // Update stock levels
        $this->updateAgentStockForBlindTransfer($agentId, $transfer->product_id, $transfer->quantity, 'blind_delivery');

        $this->realTimeSyncService->broadcastBlindTransferUpdate($transfer);
        
        return $transfer;
    }

    /**
     * Get blind transfer analytics
     */
    public function getBlindTransferAnalytics($period = 'monthly')
    {
        $startDate = match($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'quarterly' => now()->startOfQuarter(),
            default => now()->startOfMonth()
        };

        $transfers = BlindTransfer::where('created_at', '>=', $startDate)->get();

        return [
            'total_transfers' => $transfers->count(),
            'completed_transfers' => $transfers->where('transfer_status', BlindTransfer::STATUS_COMPLETED)->count(),
            'violated_transfers' => $transfers->where('transfer_status', BlindTransfer::STATUS_VIOLATED)->count(),
            'active_transfers' => $transfers->whereIn('transfer_status', [
                BlindTransfer::STATUS_SCHEDULED,
                BlindTransfer::STATUS_PICKUP_READY,
                BlindTransfer::STATUS_IN_TRANSIT,
                BlindTransfer::STATUS_DELIVERY_READY
            ])->count(),
            'overdue_transfers' => $transfers->filter(function($t) { return $t->is_overdue; })->count(),
            'completion_rate' => $this->calculateCompletionRate($transfers),
            'average_transit_time' => $this->calculateAverageTransitTime($transfers),
            'violation_rate' => $this->calculateViolationRate($transfers),
            'anonymity_compliance' => $this->calculateAnonymityCompliance($transfers),
            'window_compliance' => $this->calculateWindowCompliance($transfers),
            'top_performing_routes' => $this->getTopPerformingRoutes($transfers),
            'violation_breakdown' => $this->getViolationBreakdown($transfers),
            'efficiency_metrics' => $this->getEfficiencyMetrics($transfers)
        ];
    }

    /**
     * Send anonymous transfer instructions
     */
    private function sendBlindTransferInstructions($transfer)
    {
        // Send pickup instructions to from_agent
        $pickupInstructions = [
            'type' => 'blind_pickup',
            'pickup_code' => $transfer->pickup_code,
            'pickup_location' => $transfer->pickup_location,
            'pickup_window' => [
                'start' => $transfer->pickup_window_start,
                'end' => $transfer->pickup_window_end
            ],
            'product_details' => [
                'product_id' => $transfer->product_id,
                'quantity' => $transfer->quantity
            ],
            'instructions' => 'Proceed to pickup location during specified window. Use pickup code for verification.'
        ];

        // Send delivery instructions to to_agent
        $deliveryInstructions = [
            'type' => 'blind_delivery',
            'delivery_code' => $transfer->delivery_code,
            'delivery_location' => $transfer->delivery_location,
            'delivery_window' => [
                'start' => $transfer->delivery_window_start,
                'end' => $transfer->delivery_window_end
            ],
            'expected_product' => [
                'product_id' => $transfer->product_id,
                'quantity' => $transfer->quantity
            ],
            'instructions' => 'Proceed to delivery location during specified window. Use delivery code for verification.'
        ];

        // This would integrate with existing notification system
        Log::info('Blind transfer instructions sent', [
            'transfer_code' => $transfer->transfer_code,
            'pickup_agent' => $transfer->from_agent_id,
            'delivery_agent' => $transfer->to_agent_id
        ]);
    }

    /**
     * Get neutral transfer locations
     */
    private function getNeutralTransferLocations($fromAgentId, $toAgentId)
    {
        // This would calculate optimal neutral locations based on agent zones
        // For now, return placeholder locations
        return [
            'pickup' => [
                'name' => 'Neutral Pickup Point Alpha',
                'address' => 'Central Business District, Lagos',
                'coordinates' => ['lat' => 6.4541, 'lng' => 3.3947],
                'code' => 'NPP-A'
            ],
            'delivery' => [
                'name' => 'Neutral Delivery Point Beta',
                'address' => 'Victoria Island, Lagos',
                'coordinates' => ['lat' => 6.4281, 'lng' => 3.4219],
                'code' => 'NDP-B'
            ]
        ];
    }

    /**
     * Flag violation
     */
    private function flagViolation($transfer, $violationType, $details)
    {
        $violations = $transfer->violation_flags ?? [];
        $violations[] = [
            'type' => $violationType,
            'details' => $details,
            'flagged_at' => now(),
            'severity' => $this->getViolationSeverity($violationType)
        ];

        $transfer->update([
            'violation_flags' => $violations,
            'transfer_status' => BlindTransfer::STATUS_VIOLATED
        ]);

        Log::warning('Blind transfer violation flagged', [
            'transfer_code' => $transfer->transfer_code,
            'violation_type' => $violationType,
            'details' => $details
        ]);
    }

    /**
     * Get violation severity
     */
    private function getViolationSeverity($violationType)
    {
        $severityMap = [
            'unauthorized_pickup_attempt' => 'critical',
            'unauthorized_delivery_attempt' => 'critical',
            'pickup_window_violation' => 'high',
            'delivery_window_violation' => 'high',
            'location_deviation' => 'medium',
            'code_sharing_detected' => 'critical',
            'agent_contact_detected' => 'high'
        ];

        return $severityMap[$violationType] ?? 'medium';
    }

    /**
     * Calculate completion rate
     */
    private function calculateCompletionRate($transfers)
    {
        $total = $transfers->count();
        $completed = $transfers->where('transfer_status', BlindTransfer::STATUS_COMPLETED)->count();
        
        return $total > 0 ? round(($completed / $total) * 100, 2) : 0;
    }

    /**
     * Calculate average transit time
     */
    private function calculateAverageTransitTime($transfers)
    {
        $completedTransfers = $transfers->where('transfer_status', BlindTransfer::STATUS_COMPLETED);
        
        if ($completedTransfers->isEmpty()) {
            return 0;
        }

        $totalHours = $completedTransfers->sum('transit_duration');
        return round($totalHours / $completedTransfers->count(), 1);
    }

    /**
     * Calculate violation rate
     */
    private function calculateViolationRate($transfers)
    {
        $total = $transfers->count();
        $violated = $transfers->filter(function($t) { return $t->has_violations; })->count();
        
        return $total > 0 ? round(($violated / $total) * 100, 2) : 0;
    }

    /**
     * Calculate anonymity compliance
     */
    private function calculateAnonymityCompliance($transfers)
    {
        $total = $transfers->count();
        $compliant = $transfers->filter(function($t) {
            $violations = $t->violation_flags ?? [];
            return !collect($violations)->contains('type', 'agent_contact_detected');
        })->count();
        
        return $total > 0 ? round(($compliant / $total) * 100, 2) : 100;
    }

    /**
     * Calculate window compliance
     */
    private function calculateWindowCompliance($transfers)
    {
        $total = $transfers->count();
        $compliant = $transfers->filter(function($t) {
            $violations = $t->violation_flags ?? [];
            $windowViolations = collect($violations)->whereIn('type', [
                'pickup_window_violation',
                'delivery_window_violation'
            ]);
            return $windowViolations->isEmpty();
        })->count();
        
        return $total > 0 ? round(($compliant / $total) * 100, 2) : 100;
    }

    /**
     * Get top performing routes
     */
    private function getTopPerformingRoutes($transfers)
    {
        $routes = [];
        
        foreach ($transfers as $transfer) {
            $routeKey = $transfer->from_agent_id . '-' . $transfer->to_agent_id;
            
            if (!isset($routes[$routeKey])) {
                $routes[$routeKey] = [
                    'from_agent' => $transfer->fromAgent,
                    'to_agent' => $transfer->toAgent,
                    'total_transfers' => 0,
                    'completed_transfers' => 0,
                    'violation_count' => 0,
                    'average_transit_time' => 0
                ];
            }
            
            $routes[$routeKey]['total_transfers']++;
            if ($transfer->transfer_status === BlindTransfer::STATUS_COMPLETED) {
                $routes[$routeKey]['completed_transfers']++;
            }
            if ($transfer->has_violations) {
                $routes[$routeKey]['violation_count']++;
            }
        }

        // Calculate success rates
        foreach ($routes as &$route) {
            $route['success_rate'] = $route['total_transfers'] > 0 
                ? round(($route['completed_transfers'] / $route['total_transfers']) * 100, 2)
                : 0;
        }

        return collect($routes)
            ->sortByDesc('success_rate')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get violation breakdown
     */
    private function getViolationBreakdown($transfers)
    {
        $violations = [];
        
        foreach ($transfers as $transfer) {
            foreach ($transfer->violation_flags ?? [] as $violation) {
                $type = $violation['type'];
                if (!isset($violations[$type])) {
                    $violations[$type] = 0;
                }
                $violations[$type]++;
            }
        }

        return $violations;
    }

    /**
     * Get efficiency metrics
     */
    private function getEfficiencyMetrics($transfers)
    {
        return [
            'on_time_pickup_rate' => $this->calculateOnTimeRate($transfers, 'pickup'),
            'on_time_delivery_rate' => $this->calculateOnTimeRate($transfers, 'delivery'),
            'average_pickup_delay' => $this->calculateAverageDelay($transfers, 'pickup'),
            'average_delivery_delay' => $this->calculateAverageDelay($transfers, 'delivery'),
            'zero_violation_rate' => $this->calculateZeroViolationRate($transfers)
        ];
    }

    /**
     * Calculate on-time rate
     */
    private function calculateOnTimeRate($transfers, $type)
    {
        $relevantTransfers = $transfers->filter(function($t) use ($type) {
            return $type === 'pickup' ? $t->picked_up_at : $t->delivered_at;
        });

        if ($relevantTransfers->isEmpty()) {
            return 100;
        }

        $onTime = $relevantTransfers->filter(function($t) use ($type) {
            if ($type === 'pickup') {
                return $t->picked_up_at && 
                       $t->picked_up_at->between($t->pickup_window_start, $t->pickup_window_end);
            } else {
                return $t->delivered_at && 
                       $t->delivered_at->between($t->delivery_window_start, $t->delivery_window_end);
            }
        })->count();

        return round(($onTime / $relevantTransfers->count()) * 100, 2);
    }

    /**
     * Calculate average delay
     */
    private function calculateAverageDelay($transfers, $type)
    {
        $delays = $transfers->map(function($t) use ($type) {
            if ($type === 'pickup' && $t->picked_up_at) {
                return max(0, $t->picked_up_at->diffInMinutes($t->pickup_window_end));
            } else if ($type === 'delivery' && $t->delivered_at) {
                return max(0, $t->delivered_at->diffInMinutes($t->delivery_window_end));
            }
            return 0;
        })->filter();

        return $delays->isEmpty() ? 0 : round($delays->average(), 1);
    }

    /**
     * Calculate zero violation rate
     */
    private function calculateZeroViolationRate($transfers)
    {
        $total = $transfers->count();
        $zeroViolations = $transfers->filter(function($t) {
            return empty($t->violation_flags);
        })->count();
        
        return $total > 0 ? round(($zeroViolations / $total) * 100, 2) : 100;
    }

    /**
     * Update agent stock for blind transfer
     */
    private function updateAgentStockForBlindTransfer($agentId, $productId, $quantity, $operation)
    {
        // Integration with existing stock management system
        Log::info('Stock updated for blind transfer', [
            'agent_id' => $agentId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'operation' => $operation
        ]);
    }
}

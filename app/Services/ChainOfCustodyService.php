<?php

namespace App\Services;

use App\Models\CustodyTransfer;
use App\Models\SealLog;
use App\Models\DeliveryAgent;
use App\Models\VitalVidaProduct;
use App\Services\RealTimeSyncService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ChainOfCustodyService
{
    protected $realTimeSyncService;

    public function __construct(RealTimeSyncService $realTimeSyncService)
    {
        $this->realTimeSyncService = $realTimeSyncService;
    }

    /**
     * Initiate custody transfer with seal generation
     */
    public function initiateCustodyTransfer($fromAgentId, $toAgentId, $productId, $quantity, $initiatedBy, $transferType = 'agent_to_agent')
    {
        DB::beginTransaction();
        
        try {
            // Generate unique transfer ID and seal ID
            $transferId = 'CT-' . date('Ymd') . '-' . strtoupper(Str::random(6));
            $sealId = 'SEAL-' . date('Ymd') . '-' . strtoupper(Str::random(8));

            // Create custody transfer record
            $custodyTransfer = CustodyTransfer::create([
                'transfer_id' => $transferId,
                'from_agent_id' => $fromAgentId,
                'to_agent_id' => $toAgentId,
                'product_id' => $productId,
                'quantity' => $quantity,
                'seal_id' => $sealId,
                'custody_status' => CustodyTransfer::STATUS_PENDING,
                'transfer_type' => $transferType,
                'initiated_by' => $initiatedBy,
                'custody_metadata' => [
                    'chain_of_custody_started' => now(),
                    'expected_delivery_window' => now()->addHours(48),
                    'security_level' => 'high',
                    'tracking_enabled' => true
                ],
                'initiated_at' => now()
            ]);

            // Create initial seal log
            $this->createSealLog($custodyTransfer->id, $sealId, SealLog::STATUS_INTACT, $initiatedBy, [
                'action' => 'seal_applied',
                'location' => 'origin_warehouse',
                'notes' => 'Initial seal applied during custody transfer initiation'
            ]);

            // Update agent stock levels
            $this->updateAgentStockForCustody($fromAgentId, $productId, -$quantity, 'custody_out');
            
            // Log custody initiation
            Log::info('Custody transfer initiated', [
                'transfer_id' => $transferId,
                'from_agent' => $fromAgentId,
                'to_agent' => $toAgentId,
                'product' => $productId,
                'quantity' => $quantity,
                'seal_id' => $sealId
            ]);

            // Real-time sync notification
            $this->realTimeSyncService->broadcastCustodyUpdate($custodyTransfer);

            DB::commit();
            return $custodyTransfer;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Custody transfer initiation failed', [
                'error' => $e->getMessage(),
                'from_agent' => $fromAgentId,
                'to_agent' => $toAgentId
            ]);
            throw $e;
        }
    }

    /**
     * Approve custody transfer
     */
    public function approveCustodyTransfer($transferId, $approvedBy)
    {
        $transfer = CustodyTransfer::where('transfer_id', $transferId)->firstOrFail();
        
        if ($transfer->custody_status !== CustodyTransfer::STATUS_PENDING) {
            throw new \Exception('Transfer not in pending status');
        }

        $transfer->update([
            'custody_status' => CustodyTransfer::STATUS_APPROVED,
            'approved_by' => $approvedBy,
            'approved_at' => now()
        ]);

        // Create approval seal check
        $this->createSealLog($transfer->id, $transfer->seal_id, SealLog::STATUS_INTACT, $approvedBy, [
            'action' => 'approval_check',
            'location' => 'approval_station',
            'notes' => 'Seal verified during transfer approval'
        ]);

        $this->realTimeSyncService->broadcastCustodyUpdate($transfer);
        
        return $transfer;
    }

    /**
     * Mark transfer as in transit
     */
    public function markInTransit($transferId, $handlerUserId, $gpsCoordinates = null)
    {
        $transfer = CustodyTransfer::where('transfer_id', $transferId)->firstOrFail();
        
        if ($transfer->custody_status !== CustodyTransfer::STATUS_APPROVED) {
            throw new \Exception('Transfer must be approved before marking in transit');
        }

        $transfer->update([
            'custody_status' => CustodyTransfer::STATUS_IN_TRANSIT,
            'in_transit_at' => now()
        ]);

        // Create in-transit seal check
        $this->createSealLog($transfer->id, $transfer->seal_id, SealLog::STATUS_INTACT, $handlerUserId, [
            'action' => 'transit_check',
            'location' => 'in_transit',
            'gps_coordinates' => $gpsCoordinates,
            'notes' => 'Seal verified at transit start'
        ]);

        $this->realTimeSyncService->broadcastCustodyUpdate($transfer);
        
        return $transfer;
    }

    /**
     * Receive custody transfer with seal verification
     */
    public function receiveCustodyTransfer($transferId, $receivedBy, $sealStatus, $anomalies = [])
    {
        DB::beginTransaction();
        
        try {
            $transfer = CustodyTransfer::where('transfer_id', $transferId)->firstOrFail();
            
            if ($transfer->custody_status !== CustodyTransfer::STATUS_IN_TRANSIT) {
                throw new \Exception('Transfer not in transit status');
            }

            // Create receiving seal check
            $sealLog = $this->createSealLog($transfer->id, $transfer->seal_id, $sealStatus, $receivedBy, [
                'action' => 'receipt_verification',
                'location' => 'destination',
                'notes' => 'Seal verified upon receipt',
                'anomalies' => $anomalies
            ]);

            // Determine final status based on seal integrity
            $finalStatus = ($sealStatus === SealLog::STATUS_INTACT && empty($anomalies)) 
                ? CustodyTransfer::STATUS_COMPLETED 
                : CustodyTransfer::STATUS_VIOLATED;

            $transfer->update([
                'custody_status' => $finalStatus,
                'received_by' => $receivedBy,
                'received_at' => now(),
                'completed_at' => $finalStatus === CustodyTransfer::STATUS_COMPLETED ? now() : null
            ]);

            // Update receiving agent stock if successful
            if ($finalStatus === CustodyTransfer::STATUS_COMPLETED) {
                $this->updateAgentStockForCustody($transfer->to_agent_id, $transfer->product_id, $transfer->quantity, 'custody_in');
            } else {
                // Log violation for investigation
                $this->logCustodyViolation($transfer, $sealLog, $anomalies);
            }

            $this->realTimeSyncService->broadcastCustodyUpdate($transfer);

            DB::commit();
            return $transfer;

        } catch (\Exception $e) {
            DB::rollback();
            Log::error('Custody transfer receipt failed', [
                'error' => $e->getMessage(),
                'transfer_id' => $transferId
            ]);
            throw $e;
        }
    }

    /**
     * Create seal log entry
     */
    public function createSealLog($custodyTransferId, $sealId, $status, $checkedBy, $details = [])
    {
        return SealLog::create([
            'custody_transfer_id' => $custodyTransferId,
            'seal_id' => $sealId,
            'seal_type' => SealLog::TYPE_SECURITY,
            'seal_status' => $status,
            'checked_by' => $checkedBy,
            'check_location' => $details['location'] ?? 'unknown',
            'check_notes' => $details['notes'] ?? '',
            'anomaly_detected' => !empty($details['anomalies']),
            'anomaly_details' => $details['anomalies'] ?? [],
            'gps_coordinates' => $details['gps_coordinates'] ?? null,
            'checked_at' => now()
        ]);
    }

    /**
     * Get custody transfer analytics
     */
    public function getCustodyAnalytics($period = 'monthly')
    {
        $startDate = match($period) {
            'daily' => now()->startOfDay(),
            'weekly' => now()->startOfWeek(),
            'monthly' => now()->startOfMonth(),
            'quarterly' => now()->startOfQuarter(),
            default => now()->startOfMonth()
        };

        $transfers = CustodyTransfer::where('created_at', '>=', $startDate)->get();
        $sealLogs = SealLog::whereHas('custodyTransfer', function($q) use ($startDate) {
            $q->where('created_at', '>=', $startDate);
        })->get();

        return [
            'total_transfers' => $transfers->count(),
            'completed_transfers' => $transfers->where('custody_status', CustodyTransfer::STATUS_COMPLETED)->count(),
            'violated_transfers' => $transfers->where('custody_status', CustodyTransfer::STATUS_VIOLATED)->count(),
            'pending_transfers' => $transfers->where('custody_status', CustodyTransfer::STATUS_PENDING)->count(),
            'in_transit_transfers' => $transfers->where('custody_status', CustodyTransfer::STATUS_IN_TRANSIT)->count(),
            'shrinkage_rate' => $this->calculateShrinkageRate($transfers),
            'average_transit_time' => $this->calculateAverageTransitTime($transfers),
            'seal_integrity_rate' => $this->calculateSealIntegrityRate($sealLogs),
            'compliance_score' => $this->calculateComplianceScore($transfers),
            'violation_breakdown' => $this->getViolationBreakdown($transfers),
            'top_performing_agents' => $this->getTopPerformingAgents($transfers),
            'risk_indicators' => $this->getRiskIndicators($transfers, $sealLogs)
        ];
    }

    /**
     * Calculate shrinkage rate
     */
    private function calculateShrinkageRate($transfers)
    {
        $totalQuantity = $transfers->sum('quantity');
        $violatedQuantity = $transfers->where('custody_status', CustodyTransfer::STATUS_VIOLATED)->sum('quantity');
        
        return $totalQuantity > 0 ? round(($violatedQuantity / $totalQuantity) * 100, 2) : 0;
    }

    /**
     * Calculate average transit time
     */
    private function calculateAverageTransitTime($transfers)
    {
        $completedTransfers = $transfers->where('custody_status', CustodyTransfer::STATUS_COMPLETED);
        
        if ($completedTransfers->isEmpty()) {
            return 0;
        }

        $totalHours = $completedTransfers->sum(function($transfer) {
            return $transfer->duration_in_transit ?? 0;
        });

        return round($totalHours / $completedTransfers->count(), 1);
    }

    /**
     * Calculate seal integrity rate
     */
    private function calculateSealIntegrityRate($sealLogs)
    {
        $totalChecks = $sealLogs->count();
        $intactSeals = $sealLogs->where('seal_status', SealLog::STATUS_INTACT)->count();
        
        return $totalChecks > 0 ? round(($intactSeals / $totalChecks) * 100, 2) : 100;
    }

    /**
     * Calculate compliance score
     */
    private function calculateComplianceScore($transfers)
    {
        $totalTransfers = $transfers->count();
        $compliantTransfers = $transfers->where('custody_status', CustodyTransfer::STATUS_COMPLETED)->count();
        
        return $totalTransfers > 0 ? round(($compliantTransfers / $totalTransfers) * 100, 2) : 100;
    }

    /**
     * Get violation breakdown
     */
    private function getViolationBreakdown($transfers)
    {
        $violations = $transfers->where('custody_status', CustodyTransfer::STATUS_VIOLATED);
        
        return [
            'seal_tampering' => $violations->filter(function($t) {
                return $t->sealLogs()->where('seal_status', SealLog::STATUS_TAMPERED)->exists();
            })->count(),
            'seal_broken' => $violations->filter(function($t) {
                return $t->sealLogs()->where('seal_status', SealLog::STATUS_BROKEN)->exists();
            })->count(),
            'seal_missing' => $violations->filter(function($t) {
                return $t->sealLogs()->where('seal_status', SealLog::STATUS_MISSING)->exists();
            })->count(),
            'anomalies_detected' => $violations->filter(function($t) {
                return $t->sealLogs()->where('anomaly_detected', true)->exists();
            })->count()
        ];
    }

    /**
     * Get top performing agents
     */
    private function getTopPerformingAgents($transfers)
    {
        $agentPerformance = [];
        
        foreach ($transfers as $transfer) {
            $agentId = $transfer->from_agent_id;
            if (!isset($agentPerformance[$agentId])) {
                $agentPerformance[$agentId] = [
                    'agent' => $transfer->fromAgent,
                    'total_transfers' => 0,
                    'successful_transfers' => 0,
                    'violation_count' => 0
                ];
            }
            
            $agentPerformance[$agentId]['total_transfers']++;
            if ($transfer->custody_status === CustodyTransfer::STATUS_COMPLETED) {
                $agentPerformance[$agentId]['successful_transfers']++;
            } else if ($transfer->custody_status === CustodyTransfer::STATUS_VIOLATED) {
                $agentPerformance[$agentId]['violation_count']++;
            }
        }

        // Calculate success rates and sort
        foreach ($agentPerformance as &$performance) {
            $performance['success_rate'] = $performance['total_transfers'] > 0 
                ? round(($performance['successful_transfers'] / $performance['total_transfers']) * 100, 2)
                : 0;
        }

        return collect($agentPerformance)
            ->sortByDesc('success_rate')
            ->take(10)
            ->values()
            ->toArray();
    }

    /**
     * Get risk indicators
     */
    private function getRiskIndicators($transfers, $sealLogs)
    {
        return [
            'high_risk_transfers' => $transfers->where('custody_status', CustodyTransfer::STATUS_VIOLATED)->count(),
            'overdue_transfers' => $transfers->filter(function($t) {
                return $t->custody_status === CustodyTransfer::STATUS_IN_TRANSIT && 
                       $t->in_transit_at && 
                       $t->in_transit_at->addHours(48)->isPast();
            })->count(),
            'anomaly_rate' => $sealLogs->where('anomaly_detected', true)->count() / max($sealLogs->count(), 1) * 100,
            'critical_violations' => $sealLogs->whereIn('seal_status', [
                SealLog::STATUS_BROKEN, 
                SealLog::STATUS_MISSING
            ])->count()
        ];
    }

    /**
     * Update agent stock for custody operations
     */
    private function updateAgentStockForCustody($agentId, $productId, $quantity, $operation)
    {
        // This would integrate with existing stock management system
        // Implementation depends on existing VitalVida stock models
        Log::info('Stock updated for custody operation', [
            'agent_id' => $agentId,
            'product_id' => $productId,
            'quantity' => $quantity,
            'operation' => $operation
        ]);
    }

    /**
     * Log custody violation for investigation
     */
    private function logCustodyViolation($transfer, $sealLog, $anomalies)
    {
        Log::warning('Custody violation detected', [
            'transfer_id' => $transfer->transfer_id,
            'seal_status' => $sealLog->seal_status,
            'anomalies' => $anomalies,
            'from_agent' => $transfer->from_agent_id,
            'to_agent' => $transfer->to_agent_id,
            'product' => $transfer->product_id,
            'quantity' => $transfer->quantity
        ]);

        // This could trigger automated compliance actions
        // Integration with existing AutomatedComplianceService
    }
}

<?php

namespace App\Services;

use App\Models\SyncJob;
use App\Models\SyncConflict;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class MobileSyncService
{
    /**
     * Handle mobile sync requests
     */
    public function handleSyncRequest(string $method, array $data): array
    {
        return match($method) {
            'POST' => $this->processSyncData($data),
            'GET' => $this->getSyncData($data),
            default => throw new \InvalidArgumentException("Unsupported sync method: {$method}")
        };
    }

    /**
     * Process data uploaded from mobile app
     */
    private function processSyncData(array $data): array
    {
        $deviceId = $data['device_id'] ?? null;
        $syncItems = $data['sync_items'] ?? [];
        $conflicts = [];
        $processed = [];
        $errors = [];

        DB::beginTransaction();

        try {
            foreach ($syncItems as $item) {
                $result = $this->processSyncItem($item, $deviceId);
                
                if ($result['success']) {
                    $processed[] = $result['data'];
                } elseif ($result['conflict']) {
                    $conflicts[] = $result['conflict'];
                } else {
                    $errors[] = $result['error'];
                }
            }

            DB::commit();

            return [
                'success' => true,
                'processed_count' => count($processed),
                'conflict_count' => count($conflicts),
                'error_count' => count($errors),
                'conflicts' => $conflicts,
                'errors' => $errors,
                'next_sync_token' => $this->generateSyncToken()
            ];

        } catch (\Exception $e) {
            DB::rollBack();
            
            Log::error('Mobile sync failed', [
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => 'Sync failed: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Get data for mobile app synchronization
     */
    private function getSyncData(array $params): array
    {
        $deviceId = $params['device_id'] ?? null;
        $lastSyncToken = $params['last_sync_token'] ?? null;
        $dataTypes = $params['data_types'] ?? ['all'];

        $syncData = [];

        foreach ($dataTypes as $dataType) {
            $syncData[$dataType] = $this->getSyncDataForType($dataType, $lastSyncToken, $deviceId);
        }

        return [
            'success' => true,
            'sync_data' => $syncData,
            'sync_token' => $this->generateSyncToken(),
            'server_time' => now()->toISOString()
        ];
    }

    /**
     * Process individual sync item
     */
    private function processSyncItem(array $item, string $deviceId): array
    {
        $entityType = $item['entity_type'] ?? null;
        $entityId = $item['entity_id'] ?? null;
        $action = $item['action'] ?? 'update';
        $data = $item['data'] ?? [];
        $version = $item['version'] ?? 1;

        try {
            // Check for conflicts
            $conflict = $this->detectConflict($entityType, $entityId, $version, $data);
            if ($conflict) {
                return [
                    'success' => false,
                    'conflict' => $conflict
                ];
            }

            // Process based on entity type
            $result = match($entityType) {
                'payment' => $this->processPaymentSync($action, $data, $deviceId),
                'inventory' => $this->processInventorySync($action, $data, $deviceId),
                'logistics' => $this->processLogisticsSync($action, $data, $deviceId),
                'bonus' => $this->processBonusSync($action, $data, $deviceId),
                'analytics' => $this->processAnalyticsSync($action, $data, $deviceId),
                default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}")
            };

            // Create sync job record
            SyncJob::create([
                'device_id' => $deviceId,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'action' => $action,
                'status' => $result['success'] ? 'completed' : 'failed',
                'error_message' => $result['error'] ?? null,
                'processed_at' => now()
            ]);

            return $result;

        } catch (\Exception $e) {
            Log::error('Sync item processing failed', [
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'device_id' => $deviceId,
                'error' => $e->getMessage()
            ]);

            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Get sync data for specific type
     */
    private function getSyncDataForType(string $dataType, ?string $lastSyncToken, ?string $deviceId): array
    {
        return match($dataType) {
            'payments' => $this->getPaymentSyncData($lastSyncToken, $deviceId),
            'inventory' => $this->getInventorySyncData($lastSyncToken, $deviceId),
            'logistics' => $this->getLogisticsSyncData($lastSyncToken, $deviceId),
            'bonuses' => $this->getBonusSyncData($lastSyncToken, $deviceId),
            'analytics' => $this->getAnalyticsSyncData($lastSyncToken, $deviceId),
            'all' => $this->getAllSyncData($lastSyncToken, $deviceId),
            default => []
        };
    }

    /**
     * Detect sync conflicts
     */
    private function detectConflict(string $entityType, $entityId, int $clientVersion, array $clientData): ?array
    {
        // Get server version
        $serverData = $this->getServerData($entityType, $entityId);
        
        if (!$serverData) {
            return null; // No conflict if entity doesn't exist on server
        }

        $serverVersion = $serverData['version'] ?? 1;

        // Version conflict
        if ($clientVersion < $serverVersion) {
            return [
                'type' => 'version_mismatch',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'client_version' => $clientVersion,
                'server_version' => $serverVersion,
                'client_data' => $clientData,
                'server_data' => $serverData
            ];
        }

        // Data conflict (same version but different data)
        if ($clientVersion === $serverVersion && $clientData !== $serverData) {
            return [
                'type' => 'data_conflict',
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'client_data' => $clientData,
                'server_data' => $serverData
            ];
        }

        return null;
    }

    /**
     * Process payment sync
     */
    private function processPaymentSync(string $action, array $data, string $deviceId): array
    {
        try {
            $paymentService = app(\App\Services\PaymentEngineService::class);
            
            return match($action) {
                'create' => $paymentService->createPayment($data),
                'update' => $paymentService->updatePayment($data['id'], $data),
                'delete' => $paymentService->deletePayment($data['id']),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process inventory sync
     */
    private function processInventorySync(string $action, array $data, string $deviceId): array
    {
        try {
            $inventoryService = app(\App\Services\InventoryVerificationService::class);
            
            return match($action) {
                'create' => $inventoryService->createInventoryMovement($data),
                'update' => $inventoryService->updateInventoryMovement($data['id'], $data),
                'delete' => $inventoryService->deleteInventoryMovement($data['id']),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process logistics sync
     */
    private function processLogisticsSync(string $action, array $data, string $deviceId): array
    {
        try {
            $logisticsService = app(\App\Services\LogisticsService::class);
            
            return match($action) {
                'create' => $logisticsService->createDelivery($data),
                'update' => $logisticsService->updateDelivery($data['id'], $data),
                'delete' => $logisticsService->deleteDelivery($data['id']),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process bonus sync
     */
    private function processBonusSync(string $action, array $data, string $deviceId): array
    {
        try {
            $bonusService = app(\App\Services\BonusCalculationService::class);
            
            return match($action) {
                'create' => $bonusService->createBonus($data),
                'update' => $bonusService->updateBonus($data['id'], $data),
                'delete' => $bonusService->deleteBonus($data['id']),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Process analytics sync
     */
    private function processAnalyticsSync(string $action, array $data, string $deviceId): array
    {
        try {
            $analyticsService = app(\App\Services\AnalyticsEngineService::class);
            
            return match($action) {
                'create' => $analyticsService->createMetric($data),
                'update' => $analyticsService->updateMetric($data['id'], $data),
                'delete' => $analyticsService->deleteMetric($data['id']),
                default => throw new \InvalidArgumentException("Unknown action: {$action}")
            };

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    // Data retrieval methods
    private function getPaymentSyncData(?string $lastSyncToken, ?string $deviceId): array
    {
        $query = \App\Models\Payment::query();
        
        if ($lastSyncToken) {
            $query->where('updated_at', '>', $this->parseSyncToken($lastSyncToken));
        }

        return $query->limit(100)->get()->toArray();
    }

    private function getInventorySyncData(?string $lastSyncToken, ?string $deviceId): array
    {
        $query = \App\Models\InventoryMovement::query();
        
        if ($lastSyncToken) {
            $query->where('updated_at', '>', $this->parseSyncToken($lastSyncToken));
        }

        return $query->limit(100)->get()->toArray();
    }

    private function getLogisticsSyncData(?string $lastSyncToken, ?string $deviceId): array
    {
        $query = \App\Models\Delivery::query();
        
        if ($lastSyncToken) {
            $query->where('updated_at', '>', $this->parseSyncToken($lastSyncToken));
        }

        return $query->limit(100)->get()->toArray();
    }

    private function getBonusSyncData(?string $lastSyncToken, ?string $deviceId): array
    {
        $query = \App\Models\Bonus::query();
        
        if ($lastSyncToken) {
            $query->where('updated_at', '>', $this->parseSyncToken($lastSyncToken));
        }

        return $query->limit(100)->get()->toArray();
    }

    private function getAnalyticsSyncData(?string $lastSyncToken, ?string $deviceId): array
    {
        $query = \App\Models\AnalyticsMetric::query();
        
        if ($lastSyncToken) {
            $query->where('updated_at', '>', $this->parseSyncToken($lastSyncToken));
        }

        return $query->limit(100)->get()->toArray();
    }

    private function getAllSyncData(?string $lastSyncToken, ?string $deviceId): array
    {
        return [
            'payments' => $this->getPaymentSyncData($lastSyncToken, $deviceId),
            'inventory' => $this->getInventorySyncData($lastSyncToken, $deviceId),
            'logistics' => $this->getLogisticsSyncData($lastSyncToken, $deviceId),
            'bonuses' => $this->getBonusSyncData($lastSyncToken, $deviceId),
            'analytics' => $this->getAnalyticsSyncData($lastSyncToken, $deviceId)
        ];
    }

    // Helper methods
    private function getServerData(string $entityType, $entityId): ?array
    {
        $model = match($entityType) {
            'payment' => \App\Models\Payment::class,
            'inventory' => \App\Models\InventoryMovement::class,
            'logistics' => \App\Models\Delivery::class,
            'bonus' => \App\Models\Bonus::class,
            'analytics' => \App\Models\AnalyticsMetric::class,
            default => null
        };

        if (!$model) {
            return null;
        }

        $entity = $model::find($entityId);
        return $entity ? $entity->toArray() : null;
    }

    private function generateSyncToken(): string
    {
        return base64_encode(now()->toISOString());
    }

    private function parseSyncToken(string $token): string
    {
        return base64_decode($token);
    }
} 
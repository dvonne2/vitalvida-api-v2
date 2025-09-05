<?php

namespace App\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Services\MobileSyncService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MobileSyncController extends Controller
{
    private MobileSyncService $syncService;

    public function __construct(MobileSyncService $syncService)
    {
        $this->syncService = $syncService;
    }

    /**
     * Get sync data for mobile app
     */
    public function getSyncData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_id' => 'required|string',
                'last_sync_token' => 'nullable|string',
                'data_types' => 'nullable|array',
                'data_types.*' => 'string|in:payments,inventory,logistics,bonuses,analytics,all'
            ]);

            $params = [
                'device_id' => $request->device_id,
                'last_sync_token' => $request->last_sync_token,
                'data_types' => $request->data_types ?? ['all']
            ];

            $result = $this->syncService->handleSyncRequest('GET', $params);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Sync data retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Upload sync data from mobile app
     */
    public function uploadSyncData(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_id' => 'required|string',
                'sync_items' => 'required|array',
                'sync_items.*.entity_type' => 'required|string|in:payment,inventory,logistics,bonus,analytics',
                'sync_items.*.entity_id' => 'nullable|integer',
                'sync_items.*.action' => 'required|string|in:create,update,delete,sync',
                'sync_items.*.data' => 'required|array',
                'sync_items.*.version' => 'nullable|integer'
            ]);

            $data = [
                'device_id' => $request->device_id,
                'sync_items' => $request->sync_items
            ];

            $result = $this->syncService->handleSyncRequest('POST', $data);

            return response()->json($result);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Sync data upload failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync conflicts
     */
    public function getConflicts(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_id' => 'required|string',
                'entity_type' => 'nullable|string',
                'status' => 'nullable|string|in:pending,resolved'
            ]);

            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            $query = \App\Models\SyncConflict::where('device_id', $request->device_id);

            if ($request->entity_type) {
                $query->where('entity_type', $request->entity_type);
            }

            if ($request->status) {
                $query->where('status', $request->status);
            }

            $conflicts = $query->orderBy('detected_at', 'desc')->get();

            return response()->json([
                'success' => true,
                'data' => [
                    'conflicts' => $conflicts,
                    'total_count' => $conflicts->count(),
                    'pending_count' => $conflicts->where('status', 'pending')->count(),
                    'resolved_count' => $conflicts->where('status', 'resolved')->count()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Conflict retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Resolve sync conflict
     */
    public function resolveConflict(Request $request, int $conflictId): JsonResponse
    {
        try {
            $request->validate([
                'resolution' => 'required|string|in:use_server,use_client,merge',
                'resolved_data' => 'nullable|array'
            ]);

            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            $conflict = \App\Models\SyncConflict::find($conflictId);

            if (!$conflict) {
                return response()->json([
                    'success' => false,
                    'error' => 'Conflict not found'
                ], 404);
            }

            // Apply resolution
            $result = $this->applyConflictResolution($conflict, $request->resolution, $request->resolved_data);

            if ($result['success']) {
                $conflict->update([
                    'status' => 'resolved',
                    'resolution' => $request->resolution,
                    'resolved_at' => now()
                ]);

                return response()->json([
                    'success' => true,
                    'data' => [
                        'message' => 'Conflict resolved successfully',
                        'conflict_id' => $conflictId
                    ]
                ]);
            } else {
                return response()->json([
                    'success' => false,
                    'error' => 'Conflict resolution failed: ' . $result['error']
                ], 500);
            }

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Conflict resolution failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get sync status
     */
    public function getSyncStatus(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_id' => 'required|string'
            ]);

            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            // Get sync statistics
            $pendingJobs = \App\Models\SyncJob::where('device_id', $request->device_id)
                ->where('status', 'pending')
                ->count();

            $completedJobs = \App\Models\SyncJob::where('device_id', $request->device_id)
                ->where('status', 'completed')
                ->count();

            $failedJobs = \App\Models\SyncJob::where('device_id', $request->device_id)
                ->where('status', 'failed')
                ->count();

            $pendingConflicts = \App\Models\SyncConflict::where('device_id', $request->device_id)
                ->where('status', 'pending')
                ->count();

            $lastSync = \App\Models\SyncJob::where('device_id', $request->device_id)
                ->where('status', 'completed')
                ->latest('processed_at')
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'device_id' => $request->device_id,
                    'sync_status' => [
                        'pending_jobs' => $pendingJobs,
                        'completed_jobs' => $completedJobs,
                        'failed_jobs' => $failedJobs,
                        'pending_conflicts' => $pendingConflicts,
                        'last_sync' => $lastSync ? $lastSync->processed_at->toISOString() : null,
                        'sync_health' => $this->calculateSyncHealth($completedJobs, $failedJobs)
                    ],
                    'server_time' => now()->toISOString()
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Sync status retrieval failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Force sync for specific data type
     */
    public function forceSync(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'device_id' => 'required|string',
                'data_type' => 'required|string|in:payments,inventory,logistics,bonuses,analytics,all'
            ]);

            $user = auth()->user();
            
            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error' => 'Authentication required'
                ], 401);
            }

            // Create force sync job
            \App\Models\SyncJob::create([
                'device_id' => $request->device_id,
                'entity_type' => $request->data_type,
                'action' => 'sync',
                'status' => 'pending'
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'message' => 'Force sync initiated',
                    'data_type' => $request->data_type,
                    'device_id' => $request->device_id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Force sync failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Apply conflict resolution
     */
    private function applyConflictResolution(\App\Models\SyncConflict $conflict, string $resolution, ?array $resolvedData): array
    {
        try {
            $entityType = $conflict->entity_type;
            $entityId = $conflict->entity_id;

            switch ($resolution) {
                case 'use_server':
                    // Keep server data, discard client changes
                    return ['success' => true];

                case 'use_client':
                    // Apply client data to server
                    return $this->applyClientData($entityType, $entityId, $conflict->client_data);

                case 'merge':
                    // Merge client and server data
                    $mergedData = $this->mergeData($conflict->server_data, $conflict->client_data, $resolvedData);
                    return $this->applyClientData($entityType, $entityId, $mergedData);

                default:
                    return ['success' => false, 'error' => 'Unknown resolution type'];
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Apply client data to server
     */
    private function applyClientData(string $entityType, $entityId, array $data): array
    {
        try {
            $service = match($entityType) {
                'payment' => app(\App\Services\PaymentEngineService::class),
                'inventory' => app(\App\Services\InventoryVerificationService::class),
                'logistics' => app(\App\Services\LogisticsService::class),
                'bonus' => app(\App\Services\BonusCalculationService::class),
                'analytics' => app(\App\Services\AnalyticsEngineService::class),
                default => throw new \InvalidArgumentException("Unknown entity type: {$entityType}")
            };

            if ($entityId) {
                return $service->updateEntity($entityId, $data);
            } else {
                return $service->createEntity($data);
            }

        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Merge client and server data
     */
    private function mergeData(array $serverData, array $clientData, ?array $resolvedData): array
    {
        // Use resolved data if provided, otherwise merge intelligently
        if ($resolvedData) {
            return $resolvedData;
        }

        // Simple merge strategy - prefer client data for most fields
        $merged = array_merge($serverData, $clientData);

        // Handle special cases
        if (isset($clientData['updated_at']) && isset($serverData['updated_at'])) {
            // Keep the most recent timestamp
            $merged['updated_at'] = max($clientData['updated_at'], $serverData['updated_at']);
        }

        return $merged;
    }

    /**
     * Calculate sync health score
     */
    private function calculateSyncHealth(int $completed, int $failed): string
    {
        $total = $completed + $failed;
        
        if ($total === 0) {
            return 'unknown';
        }

        $successRate = ($completed / $total) * 100;

        return match(true) {
            $successRate >= 95 => 'excellent',
            $successRate >= 80 => 'good',
            $successRate >= 60 => 'fair',
            default => 'poor'
        };
    }
} 
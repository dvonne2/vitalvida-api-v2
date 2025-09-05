<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ZohoInventoryService;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ZohoInventoryController extends Controller
{
    protected $zohoInventoryService;

    public function __construct(ZohoInventoryService $zohoInventoryService)
    {
        $this->zohoInventoryService = $zohoInventoryService;
    }

    /**
     * Sync all DA inventory from Zoho
     */
    public function syncAllInventory(): JsonResponse
    {
        try {
            $result = $this->zohoInventoryService->syncAllDaInventory();

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'All DA inventory synced successfully' : 'Inventory sync failed',
                'data' => $result,
                'timestamp' => now()
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Zoho inventory sync API error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Inventory sync failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Sync inventory for a specific DA
     */
    public function syncSingleDaInventory(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'da_id' => 'required|integer|exists:delivery_agents,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $result = $this->zohoInventoryService->forceSyncDaInventory($request->da_id);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'DA inventory synced successfully' : 'DA inventory sync failed',
                'data' => $result,
                'timestamp' => now()
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('Single DA inventory sync API error', [
                'da_id' => $request->da_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'DA inventory sync failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get DA inventory summary
     */
    public function getDaInventorySummary(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'da_id' => 'required|integer|exists:delivery_agents,id'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $deliveryAgent = DeliveryAgent::find($request->da_id);
            $result = $this->zohoInventoryService->getDaInventorySummary($deliveryAgent);

            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? 'DA inventory summary retrieved' : 'Failed to get DA inventory summary',
                'data' => $result,
                'timestamp' => now()
            ], $result['success'] ? 200 : 500);

        } catch (\Exception $e) {
            Log::error('DA inventory summary API error', [
                'da_id' => $request->da_id,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get DA inventory summary',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get all DAs with low stock
     */
    public function getLowStockDas(): JsonResponse
    {
        try {
            $result = $this->zohoInventoryService->getDasWithLowStock();

            return response()->json([
                'success' => $result['success'],
                'message' => "Found {$result['count']} DAs with low stock",
                'data' => $result,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Low stock DAs API error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get low stock DAs',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get sync statistics
     */
    public function getSyncStatistics(): JsonResponse
    {
        try {
            $result = $this->zohoInventoryService->getSyncStatistics();

            return response()->json([
                'success' => $result['success'],
                'message' => 'Sync statistics retrieved successfully',
                'data' => $result,
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('Sync statistics API error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get sync statistics',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Get all DA inventory summaries
     */
    public function getAllDaInventorySummaries(): JsonResponse
    {
        try {
            $deliveryAgents = DeliveryAgent::where('status', 'active')->get();
            $summaries = [];

            foreach ($deliveryAgents as $da) {
                $summary = $this->zohoInventoryService->getDaInventorySummary($da);
                if ($summary['success']) {
                    $summaries[] = $summary['data'];
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'All DA inventory summaries retrieved',
                'data' => [
                    'summaries' => $summaries,
                    'total_das' => count($summaries),
                    'low_stock_count' => collect($summaries)->where('has_minimum_stock', false)->count(),
                    'adequate_stock_count' => collect($summaries)->where('has_minimum_stock', true)->count()
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            Log::error('All DA inventory summaries API error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to get DA inventory summaries',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }

    /**
     * Health check endpoint
     */
    public function healthCheck(): JsonResponse
    {
        try {
            $stats = $this->zohoInventoryService->getSyncStatistics();
            
            return response()->json([
                'success' => true,
                'message' => 'Zoho Inventory Service is healthy',
                'data' => [
                    'service_status' => 'operational',
                    'last_sync' => $stats['data']['last_sync'] ?? null,
                    'active_das' => $stats['data']['active_das'] ?? 0,
                    'sync_success_rate' => $stats['data']['last_24_hours']['success_rate'] ?? 0
                ],
                'timestamp' => now()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Zoho Inventory Service health check failed',
                'error' => $e->getMessage(),
                'timestamp' => now()
            ], 500);
        }
    }
} 
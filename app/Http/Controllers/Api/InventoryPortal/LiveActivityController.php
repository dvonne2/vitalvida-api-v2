<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\ActivityFeed;
use App\Models\Order;
use App\Models\DeliveryAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class LiveActivityController extends Controller
{
    /**
     * Get live activity feed
     */
    public function index(): JsonResponse
    {
        try {
            $liveActivity = ActivityFeed::recent(24)
                ->orderBy('created_at', 'desc')
                ->limit(50)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'timestamp' => $activity->formatted_time,
                        'type' => $activity->type,
                        'message' => $activity->message,
                        'status' => $activity->status,
                        'time_ago' => $activity->time_ago
                    ];
                });

            return response()->json(['live_activity' => $liveActivity]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch live activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get real-time activity feed (last 30 minutes)
     */
    public function realtime(): JsonResponse
    {
        try {
            $realtimeActivity = ActivityFeed::live()
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'timestamp' => $activity->formatted_time,
                        'type' => $activity->type,
                        'message' => $activity->message,
                        'status' => $activity->status,
                        'time_ago' => $activity->time_ago
                    ];
                });

            return response()->json(['realtime_activity' => $realtimeActivity]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch real-time activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity by type
     */
    public function byType(string $type): JsonResponse
    {
        try {
            $activities = ActivityFeed::byType($type)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'timestamp' => $activity->formatted_time,
                        'type' => $activity->type,
                        'message' => $activity->message,
                        'status' => $activity->status,
                        'time_ago' => $activity->time_ago
                    ];
                });

            return response()->json(['activities' => $activities]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch activities by type',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity by DA
     */
    public function byDA(string $daId): JsonResponse
    {
        try {
            $activities = ActivityFeed::byDA($daId)
                ->orderBy('created_at', 'desc')
                ->limit(20)
                ->get()
                ->map(function ($activity) {
                    return [
                        'id' => $activity->id,
                        'timestamp' => $activity->formatted_time,
                        'type' => $activity->type,
                        'message' => $activity->message,
                        'status' => $activity->status,
                        'time_ago' => $activity->time_ago
                    ];
                });

            return response()->json(['activities' => $activities]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch activities by DA',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get activity statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total_today' => ActivityFeed::today()->count(),
                'total_this_week' => ActivityFeed::whereBetween('created_at', [
                    now()->startOfWeek(), 
                    now()->endOfWeek()
                ])->count(),
                'by_type' => [
                    'pickup' => ActivityFeed::byType('pickup')->count(),
                    'delivery' => ActivityFeed::byType('delivery')->count(),
                    'mismatch' => ActivityFeed::byType('mismatch')->count(),
                    'call' => ActivityFeed::byType('call')->count()
                ],
                'by_status' => [
                    'info' => ActivityFeed::byStatus('info')->count(),
                    'delivered' => ActivityFeed::byStatus('delivered')->count(),
                    'flagged' => ActivityFeed::byStatus('flagged')->count()
                ],
                'recent_activity' => ActivityFeed::recent(1)->count() // Last hour
            ];

            return response()->json($stats);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch activity statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create activity entry
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'type' => 'required|string|max:50',
                'message' => 'required|string',
                'da_id' => 'nullable|string|max:50',
                'order_id' => 'nullable|string|max:50',
                'consignment_id' => 'nullable|string|max:20',
                'location' => 'nullable|string|max:100',
                'status' => 'nullable|string|max:50',
                'activity_data' => 'nullable|array'
            ]);

            $activity = ActivityFeed::create([
                'type' => $request->type,
                'message' => $request->message,
                'da_id' => $request->da_id,
                'order_id' => $request->order_id,
                'consignment_id' => $request->consignment_id,
                'location' => $request->location,
                'status' => $request->status ?? 'info',
                'activity_data' => $request->activity_data
            ]);

            return response()->json([
                'id' => $activity->id,
                'message' => 'Activity logged successfully'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to log activity',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order pipeline data
     */
    public function orderPipeline(): JsonResponse
    {
        try {
            // Simulate order pipeline data
            $pipelineStatus = [
                'orders_waiting' => 7,
                'delivered' => 2
            ];

            $orders = [
                [
                    'order_id' => '10057',
                    'customer_name' => 'Adam J.',
                    'product' => 'Multivitamin Pack',
                    'da_called_customer' => '09:50',
                    'out_for_delivery' => '10:11',
                    'delivery_cost' => 2500,
                    'additional_cost' => 200,
                    'payment_received' => '10:21',
                    'delivered' => '12:18',
                    'warnings' => null,
                    'actions' => ['view']
                ],
                [
                    'order_id' => '10056',
                    'customer_name' => 'Omololu A.',
                    'product' => 'Omega 3 Softgels',
                    'da_called_customer' => '08:28',
                    'out_for_delivery' => '08:48',
                    'delivery_cost' => 2200,
                    'additional_cost' => null,
                    'payment_received' => '09:07',
                    'delivered' => '11:14',
                    'warnings' => null,
                    'actions' => ['view']
                ]
            ];

            return response()->json([
                'pipeline_status' => $pipelineStatus,
                'orders' => $orders
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch order pipeline',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

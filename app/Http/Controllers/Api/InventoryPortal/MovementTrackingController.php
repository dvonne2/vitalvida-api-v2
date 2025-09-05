<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\MovementTracking;
use App\Models\SystemAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class MovementTrackingController extends Controller
{
    /**
     * Get movement tracking data
     */
    public function index(): JsonResponse
    {
        try {
            $movements = MovementTracking::orderBy('created_at', 'desc')
                ->get()
                ->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'movement_type' => $movement->movement_type,
                        'type_display' => $movement->type_display,
                        'from_location' => $movement->from_location,
                        'to_location' => $movement->to_location,
                        'quantity' => $movement->quantity,
                        'status' => $movement->status_display,
                        'tracking_number' => $movement->tracking_number,
                        'notes' => $movement->notes,
                        'started_at' => $movement->started_at?->format('Y-m-d H:i:s'),
                        'completed_at' => $movement->completed_at?->format('Y-m-d H:i:s'),
                        'duration' => $movement->duration,
                        'is_delayed' => $movement->isDelayed(),
                        'created_at' => $movement->created_at->format('Y-m-d H:i:s')
                    ];
                });

            $data = [
                'title' => 'Bird Eye Panel - Stock Movement Tracking',
                'subtitle' => 'Track logistics movements: Warehouse ↔ DA ↔ HQ',
                'movement_records' => $movements,
                'message' => $movements->isEmpty() ? 'No movement records found' : null,
                'call_to_action' => $movements->isEmpty() ? 'Add your first movement entry to get started' : null,
                'filters' => [
                    'movement_type' => [],
                    'search' => ''
                ],
                'flag_summary' => [
                    'total' => $movements->count(),
                    'pending' => $movements->where('status', 'Pending')->count(),
                    'in_progress' => $movements->where('status', 'In Progress')->count(),
                    'completed' => $movements->where('status', 'Completed')->count(),
                    'delayed' => $movements->filter(fn($m) => $m['is_delayed'])->count()
                ]
            ];

            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch movement tracking data',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new movement entry
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'movement_type' => 'required|string|in:warehouse_to_da,da_to_da,da_to_hq,da_to_warehouse',
                'from_location' => 'required|string|max:100',
                'to_location' => 'required|string|max:100',
                'quantity' => 'required|string|max:50',
                'notes' => 'nullable|string'
            ]);

            $movement = MovementTracking::create([
                'movement_type' => $request->movement_type,
                'from_location' => $request->from_location,
                'to_location' => $request->to_location,
                'quantity' => $request->quantity,
                'status' => 'pending',
                'tracking_number' => 'MT-' . strtoupper(uniqid()),
                'notes' => $request->notes
            ]);

            // Log the movement creation
            SystemAuditLog::logMovementCreated($movement->tracking_number);

            return response()->json([
                'movement_id' => $movement->id,
                'tracking_number' => $movement->tracking_number,
                'status' => 'recorded'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create movement entry',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific movement
     */
    public function show(string $id): JsonResponse
    {
        try {
            $movement = MovementTracking::find($id);
            
            if (!$movement) {
                return response()->json([
                    'error' => 'Movement not found'
                ], 404);
            }

            $data = [
                'id' => $movement->id,
                'movement_type' => $movement->movement_type,
                'type_display' => $movement->type_display,
                'from_location' => $movement->from_location,
                'to_location' => $movement->to_location,
                'quantity' => $movement->quantity,
                'status' => $movement->status_display,
                'tracking_number' => $movement->tracking_number,
                'notes' => $movement->notes,
                'started_at' => $movement->started_at?->format('Y-m-d H:i:s'),
                'completed_at' => $movement->completed_at?->format('Y-m-d H:i:s'),
                'duration' => $movement->duration,
                'is_delayed' => $movement->isDelayed(),
                'created_at' => $movement->created_at->format('Y-m-d H:i:s')
            ];

            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch movement',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update movement status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,in_progress,completed,cancelled',
                'notes' => 'nullable|string'
            ]);

            $movement = MovementTracking::find($id);
            
            if (!$movement) {
                return response()->json([
                    'error' => 'Movement not found'
                ], 404);
            }

            $updateData = [
                'status' => $request->status,
                'notes' => $request->notes
            ];

            // Set timestamps based on status
            if ($request->status === 'in_progress' && !$movement->started_at) {
                $updateData['started_at'] = now();
            }

            if ($request->status === 'completed' && !$movement->completed_at) {
                $updateData['completed_at'] = now();
            }

            $movement->update($updateData);

            return response()->json([
                'message' => 'Movement status updated successfully',
                'status' => $movement->status_display
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update movement status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get movement statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => MovementTracking::count(),
                'pending' => MovementTracking::pending()->count(),
                'in_progress' => MovementTracking::inProgress()->count(),
                'completed' => MovementTracking::completed()->count(),
                'cancelled' => MovementTracking::where('status', 'cancelled')->count(),
                'today' => MovementTracking::today()->count(),
                'this_week' => MovementTracking::thisWeek()->count(),
                'by_type' => [
                    'warehouse_to_da' => MovementTracking::byType('warehouse_to_da')->count(),
                    'da_to_da' => MovementTracking::byType('da_to_da')->count(),
                    'da_to_hq' => MovementTracking::byType('da_to_hq')->count(),
                    'da_to_warehouse' => MovementTracking::byType('da_to_warehouse')->count()
                ],
                'delayed' => MovementTracking::get()->filter(fn($m) => $m->isDelayed())->count()
            ];

            return response()->json($stats);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch movement statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get movements by type
     */
    public function byType(string $type): JsonResponse
    {
        try {
            $movements = MovementTracking::byType($type)
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($movement) {
                    return [
                        'id' => $movement->id,
                        'movement_type' => $movement->movement_type,
                        'type_display' => $movement->type_display,
                        'from_location' => $movement->from_location,
                        'to_location' => $movement->to_location,
                        'quantity' => $movement->quantity,
                        'status' => $movement->status_display,
                        'tracking_number' => $movement->tracking_number,
                        'duration' => $movement->duration,
                        'is_delayed' => $movement->isDelayed(),
                        'created_at' => $movement->created_at->format('Y-m-d H:i:s')
                    ];
                });

            return response()->json(['movements' => $movements]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch movements by type',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

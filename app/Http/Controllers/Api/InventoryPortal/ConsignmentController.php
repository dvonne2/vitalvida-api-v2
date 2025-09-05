<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Consignment;
use App\Models\SystemAuditLog;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class ConsignmentController extends Controller
{
    /**
     * Get all consignments
     */
    public function index(): JsonResponse
    {
        try {
            $consignments = Consignment::orderBy('created_at', 'desc')
                ->get()
                ->map(function ($consignment) {
                    return [
                        'id' => $consignment->consignment_id,
                        'from' => $consignment->from_location,
                        'to' => $consignment->to_location,
                        'quantity' => $consignment->quantity,
                        'port' => $consignment->port,
                        'driver' => $consignment->driver_phone,
                        'time' => $consignment->created_at->format('Y-m-d H:i'),
                        'status' => $consignment->status_display
                    ];
                });

            return response()->json(['consignments' => $consignments]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch consignments',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create new consignment
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $request->validate([
                'from' => 'required|string|max:100',
                'to' => 'required|string|max:100',
                'quantity' => 'required|string|max:50',
                'port' => 'nullable|string|max:100',
                'driver' => 'nullable|string|max:20',
                'notes' => 'nullable|string'
            ]);

            // Generate consignment ID
            $consignmentId = 'VV-' . date('Y') . '-' . str_pad(Consignment::count() + 1, 3, '0', STR_PAD_LEFT);

            $consignment = Consignment::create([
                'consignment_id' => $consignmentId,
                'from_location' => $request->from,
                'to_location' => $request->to,
                'quantity' => $request->quantity,
                'port' => $request->port,
                'driver_phone' => $request->driver,
                'notes' => $request->notes,
                'status' => 'pending'
            ]);

            // Log the action
            SystemAuditLog::logConsignmentCreated($consignmentId);

            return response()->json([
                'consignment_id' => $consignmentId,
                'status' => 'created'
            ], 201);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to create consignment',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific consignment
     */
    public function show(string $id): JsonResponse
    {
        try {
            $consignment = Consignment::where('consignment_id', $id)->first();
            
            if (!$consignment) {
                return response()->json([
                    'error' => 'Consignment not found'
                ], 404);
            }

            $data = [
                'id' => $consignment->consignment_id,
                'from' => $consignment->from_location,
                'to' => $consignment->to_location,
                'quantity' => $consignment->quantity,
                'port' => $consignment->port,
                'driver_name' => $consignment->driver_name,
                'driver_phone' => $consignment->driver_phone,
                'status' => $consignment->status_display,
                'pickup_time' => $consignment->pickup_time?->format('Y-m-d H:i:s'),
                'delivery_time' => $consignment->delivery_time?->format('Y-m-d H:i:s'),
                'notes' => $consignment->notes,
                'duration' => $consignment->duration,
                'is_delayed' => $consignment->isDelayed(),
                'created_at' => $consignment->created_at->format('Y-m-d H:i:s')
            ];

            return response()->json($data);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch consignment',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Update consignment status
     */
    public function updateStatus(Request $request, string $id): JsonResponse
    {
        try {
            $request->validate([
                'status' => 'required|in:pending,in_transit,delivered,cancelled',
                'pickup_time' => 'nullable|date',
                'delivery_time' => 'nullable|date',
                'notes' => 'nullable|string'
            ]);

            $consignment = Consignment::where('consignment_id', $id)->first();
            
            if (!$consignment) {
                return response()->json([
                    'error' => 'Consignment not found'
                ], 404);
            }

            $updateData = [
                'status' => $request->status,
                'notes' => $request->notes
            ];

            if ($request->pickup_time) {
                $updateData['pickup_time'] = $request->pickup_time;
            }

            if ($request->delivery_time) {
                $updateData['delivery_time'] = $request->delivery_time;
            }

            $consignment->update($updateData);

            return response()->json([
                'message' => 'Consignment status updated successfully',
                'status' => $consignment->status_display
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to update consignment status',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get consignment statistics
     */
    public function statistics(): JsonResponse
    {
        try {
            $stats = [
                'total' => Consignment::count(),
                'pending' => Consignment::pending()->count(),
                'in_transit' => Consignment::active()->count(),
                'delivered' => Consignment::delivered()->count(),
                'cancelled' => Consignment::where('status', 'cancelled')->count(),
                'today' => Consignment::today()->count(),
                'this_week' => Consignment::thisWeek()->count(),
                'delayed' => Consignment::get()->filter(fn($c) => $c->isDelayed())->count()
            ];

            return response()->json($stats);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to fetch consignment statistics',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}

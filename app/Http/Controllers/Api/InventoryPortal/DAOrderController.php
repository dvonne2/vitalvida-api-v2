<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\DeliveryAgent;
use App\Models\Order;
use App\Models\Delivery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DAOrderController extends Controller
{
    /**
     * Get DA orders
     */
    public function index(Request $request)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            $status = $request->get('status', 'pending');
            $today = Carbon::today();

            $query = Order::where('assigned_da_id', $agent->id)
                ->whereDate('created_at', $today);

            if ($status !== 'all') {
                $query->where('status', $status);
            }

            $orders = $query->with(['customer'])
                ->orderBy('created_at', 'desc')
                ->get()
                ->map(function ($order) {
                    return [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'customer_phone' => $order->customer_phone,
                        'delivery_address' => $order->delivery_address,
                        'amount' => (float) $order->total_amount,
                        'fasttrack_bonus' => $this->calculateFastTrackBonus($order),
                        'status' => $order->status,
                        'assigned_at' => $order->assigned_at,
                        'items' => $order->items ?? [],
                        'delivery_notes' => $order->delivery_notes
                    ];
                });

            $totalPotential = $orders->sum('amount') + $orders->sum('fasttrack_bonus');

            return response()->json([
                'success' => true,
                'data' => [
                    'active_orders' => $orders->where('status', 'pending')->count(),
                    'total_potential' => $totalPotential,
                    'orders' => $orders
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch orders',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get specific order details
     */
    public function show(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            $order = Order::where('id', $id)
                ->where('assigned_da_id', $agent->id)
                ->with(['customer'])
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $delivery = Delivery::where('order_id', $order->id)
                ->where('delivery_agent_id', $agent->id)
                ->first();

            return response()->json([
                'success' => true,
                'data' => [
                    'order' => [
                        'id' => $order->id,
                        'order_number' => $order->order_number,
                        'customer_name' => $order->customer_name,
                        'customer_phone' => $order->customer_phone,
                        'customer_email' => $order->customer_email,
                        'delivery_address' => $order->delivery_address,
                        'amount' => (float) $order->total_amount,
                        'fasttrack_bonus' => $this->calculateFastTrackBonus($order),
                        'status' => $order->status,
                        'assigned_at' => $order->assigned_at,
                        'items' => $order->items ?? [],
                        'delivery_notes' => $order->delivery_notes,
                        'delivery_otp' => $order->delivery_otp
                    ],
                    'delivery' => $delivery ? [
                        'id' => $delivery->id,
                        'status' => $delivery->status,
                        'started_at' => $delivery->started_at,
                        'delivered_at' => $delivery->delivered_at,
                        'rating' => $delivery->rating,
                        'proof_photo' => $delivery->proof_photo
                    ] : null
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to fetch order details',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Start delivery for an order
     */
    public function startDelivery(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            $order = Order::where('id', $id)
                ->where('assigned_da_id', $agent->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            // Create or update delivery record
            $delivery = Delivery::updateOrCreate(
                [
                    'order_id' => $order->id,
                    'delivery_agent_id' => $agent->id
                ],
                [
                    'status' => 'in_transit',
                    'started_at' => Carbon::now(),
                    'updated_at' => Carbon::now()
                ]
            );

            // Update order status
            $order->update([
                'status' => 'in_transit',
                'updated_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'in_transit',
                    'started_at' => $delivery->started_at,
                    'delivery_id' => $delivery->id
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to start delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Complete delivery for an order
     */
    public function completeDelivery(Request $request, $id)
    {
        try {
            $user = $request->user();
            $agent = DeliveryAgent::where('user_id', $user->id)->first();
            
            if (!$agent) {
                return response()->json([
                    'success' => false,
                    'message' => 'Delivery agent profile not found'
                ], 404);
            }

            $order = Order::where('id', $id)
                ->where('assigned_da_id', $agent->id)
                ->first();

            if (!$order) {
                return response()->json([
                    'success' => false,
                    'message' => 'Order not found'
                ], 404);
            }

            $request->validate([
                'otp' => 'required|string|size:6',
                'delivery_proof' => 'nullable|string'
            ]);

            // Verify OTP
            if ($order->delivery_otp !== $request->otp) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid OTP'
                ], 400);
            }

            // Update delivery record
            $delivery = Delivery::where('order_id', $order->id)
                ->where('delivery_agent_id', $agent->id)
                ->first();

            if ($delivery) {
                $delivery->update([
                    'status' => 'completed',
                    'delivered_at' => Carbon::now(),
                    'proof_photo' => $request->delivery_proof,
                    'updated_at' => Carbon::now()
                ]);
            }

            // Update order status
            $order->update([
                'status' => 'delivered',
                'otp_verified' => true,
                'otp_verified_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Calculate earnings
            $earnings = $this->calculateDeliveryEarnings($order, $delivery);

            return response()->json([
                'success' => true,
                'data' => [
                    'status' => 'delivered',
                    'delivered_at' => Carbon::now(),
                    'earnings' => $earnings
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to complete delivery',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Calculate FastTrack bonus for an order
     */
    private function calculateFastTrackBonus($order)
    {
        // FastTrack bonus is ₦500 for deliveries under 10 hours
        return 500;
    }

    /**
     * Calculate delivery earnings
     */
    private function calculateDeliveryEarnings($order, $delivery)
    {
        $baseEarnings = 1000; // Base delivery fee
        $fastTrackBonus = 0;

        if ($delivery && $delivery->started_at && $delivery->delivered_at) {
            $deliveryTime = Carbon::parse($delivery->delivered_at)->diffInHours($delivery->started_at);
            if ($deliveryTime <= 10) {
                $fastTrackBonus = 500;
            }
        }

        return [
            'base_earnings' => $baseEarnings,
            'fasttrack_bonus' => $fastTrackBonus,
            'total_earnings' => $baseEarnings + $fastTrackBonus
        ];
    }
}

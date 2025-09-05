<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderHistory;
use App\Models\OrderRerouting;
use App\Models\User;
use App\Models\DeliveryAgent;
use App\Models\TelesalesAgent;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

class OrderController extends Controller
{
    /**
     * Get orders list with filters
     */
    public function index(Request $request): JsonResponse
    {
        $query = Order::with(['telesalesRep', 'deliveryAgent', 'payments']);
        
        // Apply filters based on request
        if ($request->has('filter')) {
            switch ($request->filter) {
                case 'ghosted':
                    $query->where('is_ghosted', true);
                    break;
                case 'unassigned':
                    $query->whereNull('assigned_telesales_id');
                    break;
                case 'delayed':
                    $query->where('created_at', '<', now()->subMinutes(15))
                          ->whereNull('assigned_telesales_id');
                    break;
                case 'pending_payment':
                    $query->where('payment_status', 'pending');
                    break;
                case 'confirmed_payment':
                    $query->where('payment_status', 'confirmed');
                    break;
                case 'delivered':
                    $query->where('status', 'delivered');
                    break;
                case 'in_transit':
                    $query->where('status', 'in_transit');
                    break;
            }
        }

        // Apply date filters
        if ($request->has('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->has('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        // Apply state filter
        if ($request->has('state')) {
            $query->where('state', $request->state);
        }

        // Apply source filter
        if ($request->has('source')) {
            $query->where('source', $request->source);
        }

        // Apply amount range filter
        if ($request->has('min_amount')) {
            $query->where('total_amount', '>=', $request->min_amount);
        }

        if ($request->has('max_amount')) {
            $query->where('total_amount', '<=', $request->max_amount);
        }

        $orders = $query->orderBy('created_at', 'desc')->paginate($request->get('per_page', 50));
        
        return response()->json([
            'status' => 'success', 
            'data' => $orders,
            'filters_applied' => $request->only(['filter', 'date_from', 'date_to', 'state', 'source', 'min_amount', 'max_amount']),
        ]);
    }

    /**
     * Get single order details
     */
    public function show($id): JsonResponse
    {
        $order = Order::with(['telesalesRep', 'deliveryAgent', 'payments', 'history'])->findOrFail($id);
        
        return response()->json([
            'status' => 'success',
            'data' => $order,
        ]);
    }

    /**
     * Reassign order to different telesales rep
     */
    public function reassign(Request $request, $id): JsonResponse
    {
        $request->validate([
            'new_telesales_id' => 'required|exists:users,id',
            'reason' => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($id);
        $oldTelesalesId = $order->assigned_telesales_id;
        
        // Create rerouting record
        OrderRerouting::create([
            'order_id' => $id,
            'from_staff_id' => $oldTelesalesId,
            'to_staff_id' => $request->new_telesales_id,
            'reason' => $request->reason,
            'timestamp' => now(),
            'success_status' => 'pending',
        ]);

        // Update order
        $order->update([
            'assigned_telesales_id' => $request->new_telesales_id,
            'status' => 'reassigned',
        ]);

        // Log in order history
        OrderHistory::create([
            'order_id' => $id,
            'staff_id' => auth()->id(),
            'action' => 'reassigned',
            'previous_status' => $order->getOriginal('status'),
            'new_status' => 'reassigned',
            'timestamp' => now(),
            'notes' => "Reassigned from staff {$oldTelesalesId} to {$request->new_telesales_id}. Reason: {$request->reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order reassigned successfully',
            'data' => $order->fresh(['telesalesRep', 'deliveryAgent']),
        ]);
    }

    /**
     * Call customer for order
     */
    public function callCustomer(Request $request, $id): JsonResponse
    {
        $request->validate([
            'call_notes' => 'nullable|string|max:500',
            'call_outcome' => 'required|in:answered,no_answer,busy,wrong_number,confirmed,ghosted',
        ]);

        $order = Order::findOrFail($id);
        
        // Log the call action
        OrderHistory::create([
            'order_id' => $id,
            'staff_id' => auth()->id(),
            'action' => 'customer_called',
            'previous_status' => $order->status,
            'new_status' => $order->status,
            'timestamp' => now(),
            'notes' => "Customer called. Outcome: {$request->call_outcome}. Notes: {$request->call_notes}",
            'auto_action' => false,
        ]);

        // Update order based on call outcome
        if ($request->call_outcome === 'ghosted') {
            $order->markAsGhosted('Customer confirmed ghosting during call');
        } elseif ($request->call_outcome === 'confirmed') {
            $order->update(['status' => 'confirmed']);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Call logged successfully',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Flag order for investigation
     */
    public function flagOrder(Request $request, $id): JsonResponse
    {
        $request->validate([
            'flag_type' => 'required|in:fraud,suspicious,payment_mismatch,ghosted,other',
            'flag_reason' => 'required|string|max:500',
        ]);

        $order = Order::findOrFail($id);
        
        // Add fraud flag
        $flags = $order->fraud_flags ?? [];
        $flags[$request->flag_type] = [
            'reason' => $request->flag_reason,
            'flagged_by' => auth()->id(),
            'flagged_at' => now()->toISOString(),
        ];
        
        $order->update(['fraud_flags' => $flags]);

        // Log the flag action
        OrderHistory::create([
            'order_id' => $id,
            'staff_id' => auth()->id(),
            'action' => 'order_flagged',
            'previous_status' => $order->status,
            'new_status' => $order->status,
            'timestamp' => now(),
            'notes' => "Order flagged as {$request->flag_type}. Reason: {$request->flag_reason}",
            'auto_action' => false,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Order flagged successfully',
            'data' => $order->fresh(),
        ]);
    }

    /**
     * Get order statistics
     */
    public function statistics(Request $request): JsonResponse
    {
        $period = $request->get('period', 'today');
        $startDate = $period === 'today' ? today() : now()->startOfWeek();
        $endDate = $period === 'today' ? today() : now();

        $stats = [
            'total_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->count(),
            'delivered_orders' => Order::whereBetween('delivered_at', [$startDate, $endDate])->count(),
            'ghosted_orders' => Order::whereBetween('created_at', [$startDate, $endDate])->where('is_ghosted', true)->count(),
            'pending_payment' => Order::whereBetween('created_at', [$startDate, $endDate])->where('payment_status', 'pending')->count(),
            'confirmed_payment' => Order::whereBetween('created_at', [$startDate, $endDate])->where('payment_status', 'confirmed')->count(),
            'total_revenue' => Order::whereBetween('created_at', [$startDate, $endDate])->where('payment_status', 'confirmed')->sum('total_amount'),
            'delivery_rate' => $this->calculateDeliveryRate($startDate, $endDate),
            'ghost_rate' => $this->calculateGhostRate($startDate, $endDate),
            'average_order_value' => $this->calculateAverageOrderValue($startDate, $endDate),
        ];

        return response()->json([
            'status' => 'success',
            'data' => $stats,
            'period' => $period,
        ]);
    }

    /**
     * Calculate delivery rate for given period
     */
    private function calculateDeliveryRate($startDate, $endDate): float
    {
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $deliveredOrders = Order::whereBetween('delivered_at', [$startDate, $endDate])->count();
        
        return $totalOrders > 0 ? round(($deliveredOrders / $totalOrders) * 100, 2) : 0;
    }

    /**
     * Calculate ghost rate for given period
     */
    private function calculateGhostRate($startDate, $endDate): float
    {
        $totalOrders = Order::whereBetween('created_at', [$startDate, $endDate])->count();
        $ghostedOrders = Order::whereBetween('created_at', [$startDate, $endDate])->where('is_ghosted', true)->count();
        
        return $totalOrders > 0 ? round(($ghostedOrders / $totalOrders) * 100, 2) : 0;
    }

    /**
     * Calculate average order value for given period
     */
    private function calculateAverageOrderValue($startDate, $endDate): float
    {
        $orders = Order::whereBetween('created_at', [$startDate, $endDate])->get();
        
        if ($orders->count() === 0) {
            return 0;
        }
        
        return $orders->avg('total_amount');
    }

    // TELESALES PORTAL METHODS

    // POST /api/orders/{orderId}/call
    public function recordCall($orderId, Request $request)
    {
        $request->validate([
            'outcome' => 'required|in:confirmed,not_interested,callback',
            'notes' => 'nullable|string',
            'callback_time' => 'nullable|date'
        ]);
        
        $order = Order::findOrFail($orderId);
        $order->call_status = $request->outcome;
        $order->notes = $request->notes;
        $order->save();
        
        // Log call in performance tracking
        $this->logCallPerformance($order, $request->all());
        
        return response()->json(['message' => 'Call recorded successfully', 'order' => $order]);
    }
    
    // POST /api/orders/{orderId}/assign-da
    public function assignDeliveryAgent($orderId, Request $request)
    {
        $request->validate([
            'delivery_agent_id' => 'required|exists:delivery_agents,id'
        ]);
        
        $order = Order::findOrFail($orderId);
        $deliveryAgent = DeliveryAgent::findOrFail($request->delivery_agent_id);
        
        // Check if DA has required stock
        if (!$this->hasRequiredStock($deliveryAgent, $order->product_details)) {
            return response()->json(['error' => 'Delivery agent does not have required stock'], 400);
        }
        
        $order->delivery_agent_id = $request->delivery_agent_id;
        $order->delivery_status = 'assigned';
        $order->assigned_at = now();
        $order->save();
        
        // Send WhatsApp notification to DA
        $this->sendDANotification($deliveryAgent, $order);
        
        // Update DA stock and active orders count
        $this->updateDeliveryAgentStock($deliveryAgent, $order->product_details);
        
        return response()->json(['message' => 'Order assigned successfully', 'order' => $order]);
    }
    
    // POST /api/orders/{orderId}/generate-otp
    public function generateOTP($orderId)
    {
        $order = Order::findOrFail($orderId);
        
        // Only generate OTP if payment is verified
        if ($order->payment_status !== 'verified') {
            return response()->json(['error' => 'Payment not verified'], 400);
        }
        
        $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
        $order->otp_code = $otp;
        $order->otp_generated_at = now();
        $order->save();
        
        // Send OTP to customer via SMS
        $this->sendOTPToCustomer($order, $otp);
        
        return response()->json(['message' => 'OTP generated and sent to customer']);
    }
    
    // POST /api/orders/{orderId}/verify-delivery
    public function verifyDelivery($orderId, Request $request)
    {
        $request->validate([
            'otp_entered' => 'required|string|size:6'
        ]);
        
        $order = Order::findOrFail($orderId);
        
        if ($order->otp_code !== $request->otp_entered) {
            return response()->json(['error' => 'Invalid OTP'], 400);
        }
        
        if (now()->diffInHours($order->otp_generated_at) > 24) {
            return response()->json(['error' => 'OTP expired'], 400);
        }
        
        $order->delivery_status = 'delivered';
        $order->delivered_at = now();
        $order->otp_used_at = now();
        $order->save();
        
        // Calculate and record bonus
        $this->recordDeliveryBonus($order);
        
        return response()->json(['message' => 'Delivery verified successfully', 'order' => $order]);
    }

    // PRIVATE HELPER METHODS FOR TELESALES PORTAL

    private function logCallPerformance($order, $callData)
    {
        // Log call performance for bonus calculation
        $telesalesAgent = TelesalesAgent::find($order->telesales_agent_id);
        if ($telesalesAgent) {
            $weekStart = now()->startOfWeek()->format('Y-m-d');
            $currentPerformance = $telesalesAgent->getWeeklyPerformance($weekStart) ?? [];
            
            $currentPerformance['calls_made'] = ($currentPerformance['calls_made'] ?? 0) + 1;
            $currentPerformance['last_call_at'] = now()->toISOString();
            
            $telesalesAgent->setWeeklyPerformance($weekStart, $currentPerformance);
        }
    }

    private function hasRequiredStock($deliveryAgent, $productRequirements)
    {
        if (empty($productRequirements)) return true;
        
        $currentStock = $deliveryAgent->current_stock ?? [];
        
        foreach ($productRequirements as $product => $quantity) {
            if (($currentStock[$product] ?? 0) < $quantity) {
                return false;
            }
        }
        
        return true;
    }

    private function sendDANotification($deliveryAgent, $order)
    {
        // Send WhatsApp notification to delivery agent
        $message = "New order assigned: {$order->order_number}\n";
        $message .= "Customer: {$order->customer_name}\n";
        $message .= "Location: {$order->delivery_address}\n";
        $message .= "Products: " . json_encode($order->product_details);
        
        // TODO: Integrate with EbulkSMS WhatsApp API
        // $this->sendWhatsAppMessage($deliveryAgent->phone, $message);
    }

    private function updateDeliveryAgentStock($deliveryAgent, $productDetails)
    {
        $currentStock = $deliveryAgent->current_stock ?? [];
        
        foreach ($productDetails as $product => $quantity) {
            if (isset($currentStock[$product])) {
                $currentStock[$product] = max(0, $currentStock[$product] - $quantity);
            }
        }
        
        $deliveryAgent->current_stock = $currentStock;
        $deliveryAgent->active_orders_count = $deliveryAgent->active_orders_count + 1;
        $deliveryAgent->save();
    }

    private function sendOTPToCustomer($order, $otp)
    {
        $message = "Your OTP for order {$order->order_number} is: {$otp}\n";
        $message .= "Show this to the delivery agent to confirm delivery.";
        
        // TODO: Integrate with EbulkSMS SMS API
        // $this->sendSMS($order->customer_phone, $message);
    }

    private function recordDeliveryBonus($order)
    {
        $telesalesAgent = TelesalesAgent::find($order->telesales_agent_id);
        if ($telesalesAgent) {
            $weekStart = now()->startOfWeek()->format('Y-m-d');
            $currentPerformance = $telesalesAgent->getWeeklyPerformance($weekStart) ?? [];
            
            $currentPerformance['orders_delivered'] = ($currentPerformance['orders_delivered'] ?? 0) + 1;
            $currentPerformance['bonus_earned'] = ($currentPerformance['bonus_earned'] ?? 0) + 150;
            
            $telesalesAgent->setWeeklyPerformance($weekStart, $currentPerformance);
            
            // Add to accumulated bonus if not yet unlocked
            if (!$telesalesAgent->bonus_unlocked) {
                $telesalesAgent->addToAccumulatedBonus(150);
            }
        }
    }
} 
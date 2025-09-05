<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Customer;
use App\Models\AccountManager;
use App\Models\DeliveryAgent;
use App\Services\AIAssignmentService;
use App\Services\RecoveryService;
use App\Services\KanbanMovementService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class OrderController extends Controller
{
    protected $assignmentService;
    protected $kanbanService;
    
    public function __construct(AIAssignmentService $assignmentService, KanbanMovementService $kanbanService)
    {
        $this->assignmentService = $assignmentService;
        $this->kanbanService = $kanbanService;
    }
    
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'phone' => 'required|string',
            'location' => 'required|string',
            'product_name' => 'required|string',
            'quantity' => 'required|integer|min:1',
            'amount' => 'required|numeric|min:0',
            'source' => 'required|in:facebook_ads,instagram,whatsapp,referral'
        ]);
        
        // Find or create customer
        $customer = Customer::firstOrCreate(
            ['phone' => $validated['phone']],
            [
                'name' => $validated['customer_name'],
                'location' => $validated['location']
            ]
        );
        
        // Create order
        $order = Order::create([
            'order_id' => Order::generateOrderId(),
            'customer_id' => $customer->id,
            'product_name' => $validated['product_name'],
            'quantity' => $validated['quantity'],
            'amount' => $validated['amount'],
            'source' => $validated['source'],
            'risk_level' => $customer->calculateRiskLevel()
        ]);
        
        // Process through AI Kanban system
        $this->kanbanService->processOrder($order);
        
        return response()->json([
            'success' => true,
            'order' => $order->load(['customer', 'accountManager', 'deliveryAgent']),
            'ai_status' => $order->canMoveToNextStage()
        ]);
    }
    
    public function index(): JsonResponse
    {
        $orders = Order::with(['customer', 'accountManager', 'deliveryAgent'])
            ->orderBy('created_at', 'desc')
            ->paginate(20);
        
        return response()->json($orders);
    }
    
    public function kanban(): JsonResponse
    {
        $orders = [
            'received' => Order::whereIn('status', ['received', 'blocked_for_review'])
                ->with(['customer', 'accountManager'])
                ->get()
                ->map(function($order) {
                    return array_merge($order->toArray(), [
                        'ai_status' => $order->canMoveToNextStage(),
                        'movement_history' => $order->kanbanMovements()->latest()->take(3)->get()
                    ]);
                }),
            'assigned_to_am' => Order::where('status', 'assigned_to_am')
                ->with(['customer', 'accountManager'])
                ->get()
                ->map(function($order) {
                    return array_merge($order->toArray(), [
                        'ai_status' => $order->canMoveToNextStage()
                    ]);
                }),
            'assigned_to_da' => Order::where('status', 'assigned_to_da')
                ->with(['customer', 'accountManager', 'deliveryAgent'])
                ->get()
                ->map(function($order) {
                    return array_merge($order->toArray(), [
                        'ai_status' => $order->canMoveToNextStage()
                    ]);
                }),
            'payment_received' => Order::where('status', 'payment_received')
                ->with(['customer', 'accountManager', 'deliveryAgent'])
                ->get()
        ];
        
        return response()->json($orders);
    }
    
    public function confirmPayment(Order $order): JsonResponse
    {
        $recoveryService = new RecoveryService();
        $recoveryService->processPaymentProof($order);
        
        return response()->json([
            'success' => true,
            'message' => 'Payment confirmed and order processed'
        ]);
    }
    
    public function assignToDelivery(Order $order, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'delivery_agent_id' => 'required|exists:delivery_agents,id'
        ]);
        
        $deliveryAgent = DeliveryAgent::find($validated['delivery_agent_id']);
        
        $order->update([
            'delivery_agent_id' => $deliveryAgent->id,
            'status' => 'assigned_to_da'
        ]);
        
        return response()->json([
            'success' => true,
            'order' => $order->load(['customer', 'accountManager', 'deliveryAgent'])
        ]);
    }

    public function verifyOrder(Order $order, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'verification_notes' => 'required|string',
            'verified_by' => 'required|integer'
        ]);
        
        // Mark as verified
        $order->update([
            'verified_at' => now(),
            'verification_required' => false
        ]);
        
        // Remove AI restrictions
        $order->setAIRestrictions([]);
        
        // Process through Kanban AI
        $this->kanbanService->processOrder($order);
        
        return response()->json([
            'success' => true,
            'message' => 'Order verified and processed',
            'ai_status' => $order->canMoveToNextStage()
        ]);
    }

    public function overrideAI(Order $order, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'reason' => 'required|string',
            'approved_by' => 'required|integer'
        ]);
        
        // Override AI restrictions
        $result = $this->kanbanService->overrideRestrictions(
            $order, 
            $validated['approved_by'], 
            $validated['reason']
        );
        
        return response()->json([
            'success' => $result,
            'message' => $result ? 'AI restrictions overridden successfully' : 'Failed to override restrictions',
            'ai_status' => $order->canMoveToNextStage()
        ]);
    }

    public function forceProgress(Order $order, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'target_status' => 'required|in:assigned_to_am,assigned_to_da,payment_received,completed',
            'reason' => 'required|string',
            'approved_by' => 'required|integer'
        ]);
        
        $currentStatus = $order->status;
        
        // Update order status
        $order->update(['status' => $validated['target_status']]);
        
        // Log manual movement
        \App\Models\KanbanMovement::create([
            'order_id' => $order->id,
            'from_status' => $currentStatus,
            'to_status' => $validated['target_status'],
            'movement_type' => 'manual_override',
            'movement_reason' => "Manual override: " . $validated['reason'],
            'approved_by' => $validated['approved_by'],
            'moved_at' => now()
        ]);
        
        return response()->json([
            'success' => true,
            'message' => 'Order status manually updated',
            'previous_status' => $currentStatus,
            'new_status' => $validated['target_status']
        ]);
    }

    public function getAIStatus(Order $order): JsonResponse
    {
        return response()->json([
            'order_id' => $order->order_id,
            'current_status' => $order->status,
            'ai_status' => $order->canMoveToNextStage(),
            'restrictions' => $order->getAIRestrictions(),
            'movement_history' => $order->kanbanMovements()->latest()->take(5)->get(),
            'can_override' => auth()->user()->hasRole('manager') // Assuming role-based permissions
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\VitalVidaInventory;

use App\Http\Controllers\Controller;
use App\Models\VitalVidaInventory\DeliveryAgent;
use App\Models\VitalVidaInventory\DeliveryAgentProduct;
use App\Models\VitalVidaInventory\Product;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class VitalVidaDeliveryAgentController extends Controller
{
    /**
     * Get all delivery agents
     */
    public function index(Request $request): JsonResponse
    {
        $query = DeliveryAgent::with(['products']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        if ($request->has('location')) {
            $query->where('location', 'like', '%' . $request->location . '%');
        }

        $agents = $query->paginate($request->get('per_page', 12));

        return response()->json([
            'status' => 'success',
            'data' => $agents
        ]);
    }

    /**
     * Create new delivery agent
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:255',
            'location' => 'required|string|max:255',
            'address' => 'required|string|max:500',
            'vehicle_type' => 'required|string|max:100',
            'license_number' => 'nullable|string|max:100',
            'bank_account' => 'nullable|string|max:255',
            'emergency_contact' => 'nullable|string|max:255'
        ]);

        $agent = DeliveryAgent::create([
            'agent_id' => 'AG' . str_pad(DeliveryAgent::count() + 1, 3, '0', STR_PAD_LEFT),
            'name' => $request->name,
            'phone' => $request->phone,
            'email' => $request->email,
            'location' => $request->location,
            'address' => $request->address,
            'status' => 'Available',
            'rating' => 0,
            'total_deliveries' => 0,
            'completed_deliveries' => 0,
            'success_rate' => 0,
            'stock_value' => 0,
            'pending_orders' => 0,
            'vehicle_type' => $request->vehicle_type,
            'license_number' => $request->license_number,
            'bank_account' => $request->bank_account,
            'emergency_contact' => $request->emergency_contact,
            'hire_date' => now(),
            'is_active' => true
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery agent created successfully',
            'data' => $agent
        ], 201);
    }

    /**
     * Get specific delivery agent
     */
    public function show($id): JsonResponse
    {
        $agent = DeliveryAgent::with(['products.product', 'auditFlags'])
            ->findOrFail($id);

        return response()->json([
            'status' => 'success',
            'data' => $agent
        ]);
    }

    /**
     * Update delivery agent
     */
    public function update(Request $request, $id): JsonResponse
    {
        $agent = DeliveryAgent::findOrFail($id);

        $request->validate([
            'name' => 'sometimes|string|max:255',
            'phone' => 'sometimes|string|max:20',
            'email' => 'sometimes|nullable|email|max:255',
            'location' => 'sometimes|string|max:255',
            'address' => 'sometimes|string|max:500',
            'status' => 'sometimes|in:Available,On Delivery,Offline,Suspended',
            'vehicle_type' => 'sometimes|string|max:100',
            'license_number' => 'sometimes|nullable|string|max:100',
            'bank_account' => 'sometimes|nullable|string|max:255',
            'emergency_contact' => 'sometimes|nullable|string|max:255'
        ]);

        $agent->update($request->only([
            'name', 'phone', 'email', 'location', 'address', 'status',
            'vehicle_type', 'license_number', 'bank_account', 'emergency_contact'
        ]));

        return response()->json([
            'status' => 'success',
            'message' => 'Delivery agent updated successfully',
            'data' => $agent->fresh()
        ]);
    }

    /**
     * Get agent orders
     */
    public function orders($id): JsonResponse
    {
        $agent = DeliveryAgent::findOrFail($id);
        
        // Mock orders data - in production, this would come from orders table
        $orders = [
            [
                'id' => 'ORD001',
                'customer_name' => 'Mrs. Amina Hassan',
                'customer_phone' => '+234 801 234 5678',
                'delivery_address' => 'Victoria Island, Lagos',
                'products' => [
                    ['name' => 'Hair Conditioner 500ml', 'quantity' => 2, 'price' => 2500]
                ],
                'total_amount' => 5000,
                'status' => 'Delivered',
                'order_date' => now()->subDays(2)->format('Y-m-d H:i:s'),
                'delivery_date' => now()->subDays(1)->format('Y-m-d H:i:s')
            ],
            [
                'id' => 'ORD002',
                'customer_name' => 'Mr. Chinedu Okoro',
                'customer_phone' => '+234 802 345 6789',
                'delivery_address' => 'Ikeja, Lagos',
                'products' => [
                    ['name' => 'Shampoo 250ml', 'quantity' => 1, 'price' => 2000]
                ],
                'total_amount' => 2000,
                'status' => 'In Transit',
                'order_date' => now()->subHours(6)->format('Y-m-d H:i:s'),
                'delivery_date' => null
            ]
        ];

        return response()->json([
            'status' => 'success',
            'data' => [
                'agent' => $agent,
                'orders' => $orders
            ]
        ]);
    }

    /**
     * Get agent products/inventory
     */
    public function products($id): JsonResponse
    {
        $agent = DeliveryAgent::with(['products.product'])->findOrFail($id);

        $products = $agent->products->map(function($agentProduct) {
            return [
                'id' => $agentProduct->product->id,
                'name' => $agentProduct->product->name,
                'code' => $agentProduct->product->code,
                'category' => $agentProduct->product->category,
                'quantity' => $agentProduct->quantity,
                'unit_price' => $agentProduct->unit_price,
                'total_value' => $agentProduct->total_value,
                'assigned_date' => $agentProduct->assigned_date,
                'status' => $agentProduct->status
            ];
        });

        return response()->json([
            'status' => 'success',
            'data' => [
                'agent' => $agent,
                'products' => $products,
                'total_stock_value' => $products->sum('total_value')
            ]
        ]);
    }

    /**
     * Assign products to agent
     */
    public function assignProducts(Request $request, $id): JsonResponse
    {
        $request->validate([
            'products' => 'required|array',
            'products.*.product_id' => 'required|exists:vitalvida_products,id',
            'products.*.quantity' => 'required|integer|min:1',
            'products.*.unit_price' => 'required|numeric|min:0'
        ]);

        $agent = DeliveryAgent::findOrFail($id);

        foreach ($request->products as $productData) {
            DeliveryAgentProduct::create([
                'delivery_agent_id' => $agent->id,
                'product_id' => $productData['product_id'],
                'quantity' => $productData['quantity'],
                'unit_price' => $productData['unit_price'],
                'total_value' => $productData['quantity'] * $productData['unit_price'],
                'assigned_date' => now(),
                'status' => 'assigned'
            ]);
        }

        // Update agent stock value
        $agent->stock_value = $agent->products()->sum('total_value');
        $agent->save();

        return response()->json([
            'status' => 'success',
            'message' => 'Products assigned successfully',
            'data' => $agent->fresh(['products.product'])
        ]);
    }
}

<?php

namespace App\Http\Controllers\Api\InventoryPortal;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\DeliveryAgent;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class OrderController extends Controller
{
    /**
     * List all orders
     * GET /api/orders
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $query = Order::with(['customer']);

            // Apply filters
            if ($request->has('status')) {
                $query->where('status', $request->status);
            }

            if ($request->has('payment_status')) {
                $query->where('payment_status', $request->payment_status);
            }

            if ($request->has('date_from')) {
                $query->whereDate('created_at', '>=', $request->date_from);
            }

            if ($request->has('date_to')) {
                $query->whereDate('created_at', '<=', $request->date_to);
            }

            // Pagination
            $perPage = $request->get('per_page', 15);
            $orders = $query->orderBy('created_at', 'desc')->paginate($perPage);

            return response()->json([
                'success' => true,
                'data' => [
                    'orders' => $orders->items(),
                    'pagination' => [
                        'current_page' => $orders->currentPage(),
                        'last_page' => $orders->lastPage(),
                        'per_page' => $orders->perPage(),
                        'total' => $orders->total()
                    ]
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
     * Create new order
     * POST /api/orders
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'customer_name' => 'required|string|max:255',
            'customer_phone' => 'required|string|max:20',
            'customer_email' => 'nullable|email',
            'delivery_address' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
            'payment_method' => 'required|in:pay_on_delivery,pay_before_delivery',
            'delivery_preference' => 'required|in:same_day,express,standard',
            'delivery_state' => 'required|string',
            'notes' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            DB::beginTransaction();

            // Calculate total amount
            $totalAmount = 0;
            $orderItems = [];
            
            foreach ($request->items as $item) {
                $product = Product::find($item['product_id']);
                $itemTotal = $product->unit_price * $item['quantity'];
                $totalAmount += $itemTotal;
                
                $orderItems[] = [
                    'product_id' => $product->id,
                    'name' => $product->name,
                    'sku' => $product->sku,
                    'quantity' => $item['quantity'],
                    'unit_price' => $product->unit_price,
                    'total' => $itemTotal
                ];
            }

            // Calculate delivery cost
            $deliveryCost = $this->calculateDeliveryCost($request->delivery_preference, $request->delivery_state);
            $totalAmount += $deliveryCost;

            // Create or find customer
            $customer = Customer::firstOrCreate(
                ['phone' => $request->customer_phone],
                [
                    'name' => $request->customer_name,
                    'email' => $request->customer_email,
                    'address' => $request->delivery_address,
                    'state' => $request->delivery_state
                ]
            );

            // Create order
            $order = Order::create([
                'order_number' => 'ORD-' . strtoupper(Str::random(8)),
                'customer_id' => $customer->id,
                'customer_name' => $request->customer_name,
                'customer_phone' => $request->customer_phone,
                'customer_email' => $request->customer_email,
                'delivery_address' => $request->delivery_address,
                'items' => $orderItems,
                'total_amount' => $totalAmount,
                'delivery_cost' => $deliveryCost,
                'payment_method' => $request->payment_method,
                'delivery_preference' => $request->delivery_preference,
                'status' => 'pending',
                'payment_status' => $request->payment_method === 'pay_before_delivery' ? 'pending' : 'pending',
                'delivery_notes' => $request->notes
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Order created successfully',
                'data' => [
                    'order_id' => $order->id,
                    'order_number' => $order->order_number,
                    'total_amount' => $order->total_amount,
                    'delivery_cost' => $order->delivery_cost,
                    'estimated_delivery' => $this->getEstimatedDeliveryDate($request->delivery_preference)
                ]
            ], 201);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to create order',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get order details
     * GET /api/orders/{orderId}
     */
    public function show($orderId): JsonResponse
    {
        $order = Order::with(['customer', 'deliveryAgent'])->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $order
        ]);
    }

    /**
     * Update order
     * PUT /api/orders/{orderId}
     */
    public function update(Request $request, $orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'sometimes|in:pending,confirmed,processing,ready_for_delivery,assigned,in_transit,delivered,cancelled',
            'payment_status' => 'sometimes|in:pending,paid,failed,refunded',
            'assigned_da_id' => 'sometimes|exists:users,id',
            'delivery_notes' => 'sometimes|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order->update($request->only(['status', 'payment_status', 'assigned_da_id', 'delivery_notes']));

        if ($request->has('assigned_da_id')) {
            $order->update(['assigned_at' => now()]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Order updated successfully',
            'data' => $order->fresh()
        ]);
    }

    /**
     * Cancel order
     * DELETE /api/orders/{orderId}
     */
    public function destroy($orderId): JsonResponse
    {
        $order = Order::find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        if (!in_array($order->status, ['pending', 'confirmed'])) {
            return response()->json([
                'success' => false,
                'message' => 'Order cannot be cancelled in current status'
            ], 422);
        }

        $order->update(['status' => 'cancelled']);

        return response()->json([
            'success' => true,
            'message' => 'Order cancelled successfully'
        ]);
    }

    /**
     * Get all products with pricing
     * GET /api/products
     */
    public function getProducts(Request $request): JsonResponse
    {
        $query = Product::where('status', 'active');

        if ($request->has('category')) {
            $query->where('category', $request->category);
        }

        if ($request->has('search')) {
            $query->where('name', 'like', '%' . $request->search . '%');
        }

        $products = $query->paginate($request->get('per_page', 20));

        return response()->json([
            'success' => true,
            'data' => $products
        ]);
    }

    /**
     * Get specific product details
     * GET /api/products/{productId}
     */
    public function getProduct($productId): JsonResponse
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Get product bundles
     * GET /api/products/bundles
     */
    public function getBundles(): JsonResponse
    {
        $bundles = [
            [
                'id' => 'self_love_plus',
                'name' => 'SELF LOVE PLUS',
                'description' => 'Buy 1 shampoo, 1 pomade plus 1 conditioner',
                'price' => 32750,
                'original_price' => 45000,
                'savings' => 12250,
                'products' => [
                    ['name' => 'VitalVida Shampoo', 'quantity' => 1],
                    ['name' => 'VitalVida Pomade', 'quantity' => 1],
                    ['name' => 'VitalVida Conditioner', 'quantity' => 1]
                ],
                'active' => true
            ],
            [
                'id' => 'self_love_b2gof',
                'name' => 'SELF LOVE B2GOF',
                'description' => 'Buy 2 shampoo, 2 pomade & Get 1 shampoo, 1 pomade FREE',
                'price' => 52750,
                'original_price' => 90000,
                'savings' => 37250,
                'products' => [
                    ['name' => 'VitalVida Shampoo', 'quantity' => 3],
                    ['name' => 'VitalVida Pomade', 'quantity' => 3]
                ],
                'active' => true
            ],
            [
                'id' => 'family_saves',
                'name' => 'FAMILY SAVES',
                'description' => 'Buy 6 shampoos, 6 pomades, 6 conditioners and Get 4 shampoos, 4 pomades, 4 conditioners FREE',
                'price' => 215750,
                'original_price' => 450000,
                'savings' => 234250,
                'products' => [
                    ['name' => 'VitalVida Shampoo', 'quantity' => 10],
                    ['name' => 'VitalVida Pomade', 'quantity' => 10],
                    ['name' => 'VitalVida Conditioner', 'quantity' => 10]
                ],
                'active' => true
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $bundles
        ]);
    }

    /**
     * Get available states
     * GET /api/locations/states
     */
    public function getStates(): JsonResponse
    {
        $states = [
            'Abuja', 'Lagos', 'Kano', 'Kaduna', 'Katsina', 'Oyo', 'Rivers', 'Bauchi', 'Jigawa', 'Benue',
            'Anambra', 'Borno', 'Sokoto', 'Kebbi', 'Zamfara', 'Yobe', 'Gombe', 'Kwara', 'Kogi', 'Niger',
            'Plateau', 'Adamawa', 'Nasarawa', 'Taraba', 'Delta', 'Edo', 'Cross River', 'Akwa Ibom',
            'Imo', 'Abia', 'Enugu', 'Ebonyi', 'Ekiti', 'Ondo', 'Osun', 'Ogun', 'Kebbi', 'Kogi'
        ];

        return response()->json([
            'success' => true,
            'data' => $states
        ]);
    }

    /**
     * Get delivery options and pricing
     * GET /api/delivery/options
     */
    public function getDeliveryOptions(): JsonResponse
    {
        $options = [
            [
                'id' => 'same_day',
                'name' => 'Same-Day Delivery',
                'description' => 'Orders before 12 noon only',
                'price' => 4000,
                'estimated_time' => 'Same day',
                'cutoff_time' => '12:00 PM',
                'active' => true
            ],
            [
                'id' => 'express',
                'name' => 'Express Delivery',
                'description' => '1-2 days delivery',
                'price' => 3500,
                'estimated_time' => '1-2 days',
                'cutoff_time' => '5:00 PM',
                'active' => true
            ],
            [
                'id' => 'standard',
                'name' => 'Standard Delivery',
                'description' => '3-5 days delivery',
                'price' => 2500,
                'estimated_time' => '3-5 days',
                'cutoff_time' => '5:00 PM',
                'active' => true
            ]
        ];

        return response()->json([
            'success' => true,
            'data' => $options
        ]);
    }

    /**
     * Calculate delivery cost
     * POST /api/delivery/calculate
     */
    public function calculateDelivery(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'delivery_preference' => 'required|in:same_day,express,standard',
            'delivery_state' => 'required|string',
            'order_value' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $baseCost = $this->calculateDeliveryCost($request->delivery_preference, $request->delivery_state);
        
        // Apply free delivery for orders above ₦50,000
        $finalCost = $request->order_value >= 50000 ? 0 : $baseCost;

        return response()->json([
            'success' => true,
            'data' => [
                'delivery_preference' => $request->delivery_preference,
                'delivery_state' => $request->delivery_state,
                'base_cost' => $baseCost,
                'final_cost' => $finalCost,
                'free_delivery_applied' => $request->order_value >= 50000,
                'estimated_delivery_date' => $this->getEstimatedDeliveryDate($request->delivery_preference)
            ]
        ]);
    }

    /**
     * Process payment
     * POST /api/payments/process
     */
    public function processPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'order_id' => 'required|exists:orders,id',
            'payment_method' => 'required|in:pay_on_delivery,pay_before_delivery',
            'payment_reference' => 'required_if:payment_method,pay_before_delivery|string',
            'amount' => 'required|numeric|min:0'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::find($request->order_id);

        if ($order->payment_status === 'paid') {
            return response()->json([
                'success' => false,
                'message' => 'Order is already paid'
            ], 422);
        }

        // For pay before delivery, verify payment reference
        if ($request->payment_method === 'pay_before_delivery') {
            // Here you would integrate with your payment gateway
            // For now, we'll simulate payment verification
            $paymentVerified = $this->verifyPaymentReference($request->payment_reference);
            
            if (!$paymentVerified) {
                return response()->json([
                    'success' => false,
                    'message' => 'Payment verification failed'
                ], 422);
            }
        }

        $order->update([
            'payment_status' => 'paid',
            'payment_reference' => $request->payment_reference ?? null
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Payment processed successfully',
            'data' => [
                'order_id' => $order->id,
                'payment_status' => $order->payment_status,
                'payment_reference' => $order->payment_reference
            ]
        ]);
    }

    /**
     * Check payment status
     * GET /api/payments/{paymentId}/status
     */
    public function getPaymentStatus($paymentId): JsonResponse
    {
        $order = Order::where('payment_reference', $paymentId)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Payment not found'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'payment_id' => $paymentId,
                'order_id' => $order->id,
                'status' => $order->payment_status,
                'amount' => $order->total_amount,
                'processed_at' => $order->updated_at
            ]
        ]);
    }

    /**
     * Verify payment completion
     * POST /api/payments/verify
     */
    public function verifyPayment(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'payment_reference' => 'required|string',
            'amount' => 'required|numeric'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $order = Order::where('payment_reference', $request->payment_reference)->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Payment reference not found'
            ], 404);
        }

        $isValid = $order->total_amount == $request->amount && $order->payment_status === 'paid';

        return response()->json([
            'success' => true,
            'data' => [
                'payment_reference' => $request->payment_reference,
                'is_valid' => $isValid,
                'order_id' => $order->id,
                'expected_amount' => $order->total_amount,
                'actual_amount' => $request->amount,
                'status' => $order->payment_status
            ]
        ]);
    }

    /**
     * Calculate delivery cost based on preference and state
     */
    private function calculateDeliveryCost(string $preference, string $state): float
    {
        $baseCosts = [
            'same_day' => 4000,
            'express' => 3500,
            'standard' => 2500
        ];

        $baseCost = $baseCosts[$preference] ?? 2500;

        // Add state-specific adjustments
        $stateMultipliers = [
            'Lagos' => 1.0,
            'Abuja' => 1.2,
            'Kano' => 1.3,
            'Kaduna' => 1.1,
            'Rivers' => 1.15
        ];

        $multiplier = $stateMultipliers[$state] ?? 1.1;

        return round($baseCost * $multiplier, 2);
    }

    /**
     * Get estimated delivery date
     */
    private function getEstimatedDeliveryDate(string $preference): string
    {
        $dates = [
            'same_day' => now()->format('Y-m-d'),
            'express' => now()->addDays(2)->format('Y-m-d'),
            'standard' => now()->addDays(5)->format('Y-m-d')
        ];

        return $dates[$preference] ?? now()->addDays(5)->format('Y-m-d');
    }

    /**
     * Verify payment reference (simulated)
     */
    private function verifyPaymentReference(string $reference): bool
    {
        // In real implementation, this would call your payment gateway API
        // For now, we'll simulate verification
        return !empty($reference) && strlen($reference) >= 8;
    }
} 
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { text-align: center; color: #2d8f3f; margin-bottom: 30px; }
        .order-details { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .item { border-bottom: 1px solid #eee; padding: 8px 0; }
        .total { font-weight: bold; color: #2d8f3f; margin-top: 15px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>VitalVida</h1>
        <h2>Delivery Successful!</h2>
    </div>

    <p>Hi {{ $order->customer_name }}!</p>
    <p>Great news! Your VitalVida order has been delivered successfully.</p>

    <div class="order-details">
        <h3>Order #{{ $order->order_number }}</h3>
        <p><strong>Customer:</strong> {{ $order->customer_name }}</p>
        <p><strong>Phone:</strong> {{ $order->customer_phone }}</p>
        <p><strong>Delivery Address:</strong> {{ $order->delivery_address }}</p>
        
        <h4>Items Delivered:</h4>
        @foreach($order->items as $item)
        <div class="item">
            {{ $item['name'] }} - Qty: {{ $item['quantity'] }}
        </div>
        @endforeach
        
        <div class="total">
            Total Amount: ₦{{ number_format($order->total_amount / 100, 2) }}
        </div>
    </div>

    <p><strong>Delivered on:</strong> {{ $order->delivery_date->format('l, F j, Y at g:i A') }}</p>
    <p><strong>Payment Status:</strong> {{ ucfirst($order->payment_status) }}</p>

    <p style="text-align: center; background: #2d8f3f; color: white; padding: 20px; border-radius: 8px;">
        Thank You for Choosing VitalVida!<br>
        Your health is our priority.
    </p>
</body>
</html>

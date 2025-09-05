<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>VitalVida Low Stock Alert</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: #e74c3c; color: white; padding: 20px; text-align: center; }
        .content { padding: 20px; background: #f9f9f9; }
        .products-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .products-table th, .products-table td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        .products-table th { background: #f2f2f2; }
        .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
        .alert { background: #fff3cd; border: 1px solid #ffeaa7; padding: 15px; margin: 15px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🚨 Low Stock Alert</h1>
            <p>{{ $state }} • {{ now()->format('M j, Y • h:i A') }}</p>
        </div>

        <div class="content">
            <div class="alert">
                <h3>📊 Alert Summary</h3>
                <p><strong>{{ $alertCount }}</strong> products are below their stock thresholds in <strong>{{ $state }}</strong></p>
            </div>

            <h3>📦 Products Requiring Attention</h3>
            
            <table class="products-table">
                <thead>
                    <tr>
                        <th>Product ID</th>
                        <th>Current Stock</th>
                        <th>Threshold</th>
                        <th>Shortage</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($lowStockProducts as $product)
                        <tr>
                            <td><strong>#{{ $product['product_id'] }}</strong></td>
                            <td>{{ $product['current_stock'] }} units</td>
                            <td>{{ $product['threshold'] }} units</td>
                            <td>{{ $product['shortage'] }} units</td>
                            <td style="color: {{ $product['current_stock'] <= 0 ? '#e74c3c' : '#f39c12' }};">
                                {{ $product['current_stock'] <= 0 ? 'OUT OF STOCK' : 'LOW STOCK' }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="background: #e8f5e8; padding: 15px; margin: 20px 0;">
                <h4>🎯 Recommended Actions:</h4>
                <ul>
                    <li><strong>Immediate:</strong> Review critical stock levels</li>
                    <li><strong>Short-term:</strong> Contact suppliers for restocking</li>
                    <li><strong>Long-term:</strong> Adjust reorder points for {{ $state }}</li>
                </ul>
            </div>
        </div>

        <div class="footer">
            <p><strong>VitalVida Inventory Management System</strong></p>
            <p>Automated alert generated at {{ now()->format('M j, Y h:i A') }}</p>
        </div>
    </div>
</body>
</html>

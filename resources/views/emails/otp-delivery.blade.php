<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
        .container { background: white; border-radius: 10px; padding: 30px; box-shadow: 0 5px 15px rgba(0,0,0,0.1); }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 3px solid #28a745; padding-bottom: 20px; }
        .logo { font-size: 28px; font-weight: bold; color: #28a745; margin-bottom: 10px; }
        .otp-box { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 25px; border-radius: 10px; text-align: center; margin: 25px 0; }
        .otp-code { font-size: 36px; font-weight: bold; letter-spacing: 8px; margin: 10px 0; font-family: 'Courier New', monospace; }
        .warning-box { background: #fff3cd; border: 2px solid #ffeaa7; border-radius: 8px; padding: 20px; margin: 20px 0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <div class="logo">VitalVida</div>
            <div style="font-size: 48px;">🎉</div>
            <h1 style="color: #28a745; margin: 0;">Payment Confirmed!</h1>
        </div>

        <h2>Hello {{ $customerName }}! 👋</h2>
        <p>Great news! Your payment has been successfully processed and your order is now ready for delivery.</p>

        <div style="background: #f8f9fa; padding: 15px; border-radius: 6px; margin: 15px 0;">
            <h3 style="color: #28a745;">📦 Order Details</h3>
            <p><strong>Order Number:</strong> {{ $orderNumber }}</p>
            <p><strong>Customer:</strong> {{ $order->customer_name }}</p>
            <p><strong>Delivery Address:</strong> {{ $order->delivery_address }}</p>
        </div>

        <div class="otp-box">
            <h3 style="margin-top: 0;">🔐 Your Delivery OTP</h3>
            <div class="otp-code">{{ $otp }}</div>
            <p style="margin-bottom: 0;">Keep this code safe for our delivery agent</p>
        </div>

        <div class="warning-box">
            <div style="font-weight: bold; color: #856404;">⚠️ IMPORTANT SECURITY NOTICE</div>
            <ul>
                <li>DO NOT share this OTP with anyone except our official delivery agent</li>
                <li>Our delivery agent will ask for this code before handing over your order</li>
                <li>VitalVida will never ask for your OTP via phone or email</li>
            </ul>
        </div>

        <div style="background: #e8f5e8; border-left: 4px solid #28a745; padding: 20px; margin: 20px 0;">
            <h3 style="color: #28a745;">📋 Next Steps</h3>
            <ol>
                <li>Wait for our delivery agent to contact you</li>
                <li>Have your OTP {{ $otp }} ready</li>
                <li>Verify the agent's ID and VitalVida uniform</li>
                <li>Provide the OTP to receive your order</li>
            </ol>
        </div>

        <div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #eee;">
            <p><strong>Thank you for choosing VitalVida! 💚</strong></p>
            <p style="color: #666; font-size: 14px;">This is an automated message.</p>
        </div>
    </div>
</body>
</html>

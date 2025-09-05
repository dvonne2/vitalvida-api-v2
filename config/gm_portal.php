<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GM Portal Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the GM Portal including eBulkSMS integration,
    | alert templates, and communication settings.
    |
    */

    'ebulksms' => [
        'username' => env('EBULKSMS_USERNAME', 'vitalvida'),
        'api_key' => env('EBULKSMS_API_KEY', 'your-api-key-here'),
        'sender_name' => env('EBULKSMS_SENDER_NAME', 'VitalVida'),
        'base_url' => env('EBULKSMS_BASE_URL', 'https://api.ebulksms.com'),
        
        // Alert recipients configuration
        'fraud_alert_recipients' => [
            env('GM_PRIMARY_PHONE', '+2348001234567'),
            env('COO_BACKUP_PHONE', '+2348001234568'),
        ],
        
        'stock_emergency_recipients' => [
            env('GM_PRIMARY_PHONE', '+2348001234567'),
            env('INVENTORY_MANAGER_PHONE', '+2348001234569'),
        ],
        
        'da_performance_recipients' => [
            env('GM_PRIMARY_PHONE', '+2348001234567'),
            env('HR_MANAGER_PHONE', '+2348001234570'),
        ],
        
        'payment_mismatch_recipients' => [
            env('GM_PRIMARY_PHONE', '+2348001234567'),
            env('FINANCE_MANAGER_PHONE', '+2348001234571'),
        ],
    ],

    'states' => [
        'Lagos',
        'Kano', 
        'Abuja',
        'Ogun',
        'Rivers',
        'Kaduna',
        'Katsina',
        'Jigawa',
        'Zamfara',
        'Yobe',
        'Borno',
        'Gombe',
        'Bauchi',
        'Plateau',
        'Nasarawa',
        'Kogi',
        'Kwara',
        'Niger',
        'Kebbi',
        'Sokoto',
        'Katsina',
        'Kano',
        'Jigawa',
        'Yobe',
        'Borno',
        'Adamawa',
        'Taraba',
        'Gombe',
        'Bauchi',
        'Plateau',
        'Nasarawa',
        'Kogi',
        'Kwara',
        'Niger',
        'Kebbi',
        'Sokoto',
    ],

    'alert_templates' => [
        'fraud_alert' => [
            'name' => 'Fraud Detection Alert',
            'sms_template' => '🚨 FRAUD DETECTED: {staff_name} - {confidence}% confidence. Risk: ₦{amount}. Action: {action}',
            'whatsapp_template' => '🚨 FRAUD DETECTED: {staff_name} - {confidence}% confidence. Risk amount: ₦{amount}. Auto-action: {action}. Immediate investigation required.',
            'priority' => 'critical',
        ],
        
        'stock_emergency' => [
            'name' => 'Stock Emergency Alert',
            'sms_template' => '🚨 STOCK EMERGENCY: {state} {product} stockout in {days} days. Revenue at risk: ₦{amount}',
            'whatsapp_template' => '🚨 STOCK EMERGENCY: {state} {product} stockout in {days} days. Revenue at risk: ₦{amount}. Immediate action required.',
            'priority' => 'high',
        ],
        
        'da_performance' => [
            'name' => 'DA Performance Alert',
            'sms_template' => '📊 DA PERFORMANCE: {da_name} delivery rate: {rate}%. {action}',
            'whatsapp_template' => '📊 DA PERFORMANCE: {da_name} delivery rate: {rate}%. {action}',
            'priority' => 'medium',
        ],
        
        'payment_mismatch' => [
            'name' => 'Payment Mismatch Alert',
            'sms_template' => '💰 PAYMENT MISMATCH: {staff_name} marked {orders} orders paid - ₦{amount} | Moniepoint: ₦0 received',
            'whatsapp_template' => '💰 PAYMENT MISMATCH: {staff_name} marked {orders} orders paid - ₦{amount} | Moniepoint: ₦0 received. Payouts auto-frozen.',
            'priority' => 'critical',
        ],
    ],

    'communication_settings' => [
        'auto_escalation' => true,
        'escalation_delay_minutes' => 15,
        'max_retries' => 3,
        'retry_delay_minutes' => 5,
        'critical_alert_timeout' => 30, // minutes
    ],

    'notification_channels' => [
        'whatsapp' => true,
        'sms' => true,
        'email' => false, // For future implementation
        'push' => false, // For future implementation
    ],
]; 
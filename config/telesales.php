<?php

return [
    'bonus_rules' => [
        'min_orders_per_week' => 20,
        'min_delivery_rate' => 70, // percentage
        'bonus_per_delivery' => 150, // in Naira
        'employment_lock_months' => 3,
    ],
    
    'performance' => [
        'auto_assignment_enabled' => true,
        'stock_sync_interval' => 10, // minutes
        'urgent_order_threshold' => 15, // minutes
        'max_orders_per_agent' => 50, // per week
    ],
    
    'notifications' => [
        'delivery_agent_assignment' => true,
        'customer_otp' => true,
        'performance_alerts' => true,
        'bonus_notifications' => true,
    ],
    
    'kemi' => [
        'enabled' => true,
        'response_timeout' => 30, // seconds
        'max_context_length' => 1000,
    ],
    
    'zoho' => [
        'sync_enabled' => true,
        'sync_interval' => 10, // minutes
        'retry_attempts' => 3,
    ],
    
    'ebulksms' => [
        'enabled' => true,
        'whatsapp_enabled' => true,
        'sms_enabled' => true,
        'rate_limit' => 100, // per minute
    ],
]; 
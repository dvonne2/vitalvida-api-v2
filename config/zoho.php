<?php

return [
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'redirect_uri' => env('ZOHO_REDIRECT_URI'),
    'access_token' => env('ZOHO_ACCESS_TOKEN'),
    'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
    'inventory_api_url' => env('ZOHO_INVENTORY_API_URL', 'https://www.zohoapis.com/inventory/v1'),
    'default_warehouse_id' => env('ZOHO_DEFAULT_WAREHOUSE_ID'),
    
    // Security settings
    'enable_webhook_monitoring' => env('ZOHO_WEBHOOK_MONITORING', true),
    'webhook_secret' => env('ZOHO_WEBHOOK_SECRET'),
    'max_deduction_per_hour' => env('MAX_DEDUCTION_PER_HOUR', 100),
];

<?php

return [
    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'zoho' => [
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
        'organization_id' => env('ZOHO_ORGANIZATION_ID'),
        'region' => env('ZOHO_REGION', 'com'),
        'fhg_location_id' => env('ZOHO_FHG_LOCATION_ID'),
        'fhg_location_name' => env('ZOHO_FHG_LOCATION_NAME'),
    ],

    // Webhook URLs for notifications
    'webhooks' => [
        'slack' => env('WEBHOOK_SLACK_URL'),
        'teams' => env('WEBHOOK_TEAMS_URL'),
        'discord' => env('WEBHOOK_DISCORD_URL'),
        'custom' => env('WEBHOOK_CUSTOM_URL'),
    ],

    // Moniepoint Configuration (for our new system)
    'moniepoint' => [
        'terminal_account' => env('MONIEPOINT_TERMINAL_ACCOUNT'),
        'bank_name' => env('MONIEPOINT_BANK_NAME', 'Moniepoint MFB'),
    ],

    // eBulk SMS Configuration
    'ebulk' => [
        'url' => env('EBULK_URL'),
        'username' => env('EBULK_USERNAME'),
        'apikey' => env('EBULK_APIKEY'),
    ],

    // WAMISION WhatsApp Configuration
    'wamision' => [
        'url' => env('WAMISION_URL'),
        'token' => env('WAMISION_TOKEN'),
    ],
];

<?php

return [
    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        'model' => 'gpt-4',
        'max_tokens' => 1000,
        'temperature' => 0.7,
    ],
    
    'claude' => [
        'api_key' => env('CLAUDE_API_KEY'),
        'model' => 'claude-3-sonnet-20240229',
        'max_tokens' => 1000,
        'temperature' => 0.7,
    ],
    
    'meta_ads' => [
        'access_token' => env('META_ACCESS_TOKEN'),
        'app_id' => env('META_APP_ID'),
        'app_secret' => env('META_APP_SECRET'),
        'pixel_id' => env('META_PIXEL_ID'),
        'ad_account_id' => env('META_AD_ACCOUNT_ID'),
    ],
    
    'whatsapp' => [
        'token' => env('WHATSAPP_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        'webhook_verify_token' => env('WHATSAPP_WEBHOOK_VERIFY_TOKEN'),
        'business_account_id' => env('WHATSAPP_BUSINESS_ACCOUNT_ID'),
    ],
    
    'termii' => [
        'api_key' => env('TERMII_API_KEY'),
        'sender_id' => env('TERMII_SENDER_ID', 'Vitalvida'),
        'base_url' => 'https://api.ng.termii.com',
    ],
    
    'tiktok' => [
        'access_token' => env('TIKTOK_ACCESS_TOKEN'),
        'app_id' => env('TIKTOK_APP_ID'),
        'app_secret' => env('TIKTOK_APP_SECRET'),
        'advertiser_id' => env('TIKTOK_ADVERTISER_ID'),
    ],
    
    'google_ads' => [
        'developer_token' => env('GOOGLE_ADS_DEVELOPER_TOKEN'),
        'client_id' => env('GOOGLE_ADS_CLIENT_ID'),
        'client_secret' => env('GOOGLE_ADS_CLIENT_SECRET'),
        'refresh_token' => env('GOOGLE_ADS_REFRESH_TOKEN'),
        'customer_id' => env('GOOGLE_ADS_CUSTOMER_ID'),
    ],
    
    'youtube' => [
        'api_key' => env('YOUTUBE_API_KEY'),
        'channel_id' => env('YOUTUBE_CHANNEL_ID'),
    ],
    
    'zoho' => [
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'refresh_token' => env('ZOHO_REFRESH_TOKEN'),
        'base_url' => 'https://campaigns.zoho.com',
    ],
    
    'creative_tools' => [
        'midjourney' => [
            'api_key' => env('MIDJOURNEY_API_KEY'),
            'base_url' => 'https://api.midjourney.com',
        ],
        'runway' => [
            'api_key' => env('RUNWAY_API_KEY'),
            'base_url' => 'https://api.runwayml.com',
        ],
        'canva' => [
            'api_key' => env('CANVA_API_KEY'),
            'base_url' => 'https://api.canva.com',
        ],
    ],
    
    'analytics' => [
        'madgicx' => [
            'api_key' => env('MADGICX_API_KEY'),
            'base_url' => 'https://api.madgicx.com',
        ],
        'hyros' => [
            'api_key' => env('HYROS_API_KEY'),
            'base_url' => 'https://api.hyros.com',
        ],
    ],
    
    'performance_targets' => [
        'target_cpo' => 1200, // ₦1,200 target cost per order
        'target_ctr' => 0.015, // 1.5% target click-through rate
        'target_roi' => 200, // 200% target ROI
        'target_repeat_rate' => 80, // 80% target repeat purchase rate
        'daily_orders_target' => 5000, // 5,000 orders per day target
    ],
    
    'ai_optimization' => [
        'confidence_threshold' => 0.8, // Minimum AI confidence score
        'auto_scale_threshold' => 0.85, // Threshold for auto-scaling
        'kill_threshold' => 0.3, // Threshold for killing underperformers
        'max_creative_variations' => 5, // Maximum variations per creative
        'refresh_interval' => 30, // Seconds between metric refreshes
    ],
    
    'platform_priorities' => [
        'meta' => 1, // Highest priority
        'whatsapp' => 2,
        'tiktok' => 3,
        'google' => 4,
        'youtube' => 5,
        'sms' => 6,
        'email' => 7, // Lowest priority
    ],
    
    'customer_segments' => [
        'high_value' => [
            'min_ltv' => 50000,
            'min_orders' => 3,
            'max_churn_risk' => 0.3,
        ],
        'at_risk' => [
            'min_churn_risk' => 0.7,
            'max_days_since_order' => 60,
        ],
        'new_customer' => [
            'max_orders' => 1,
            'max_days_since_first_order' => 30,
        ],
        'loyal' => [
            'min_orders' => 5,
            'min_ltv' => 30000,
            'max_churn_risk' => 0.2,
        ],
    ],
]; 
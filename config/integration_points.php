<?php

return [
    'critical_sync_points' => [
        'da_performance' => [
            'vitalvida_source' => 'VitalVidaDeliveryAgent->rating',
            'role_destination' => 'DeliveryAgent->performance_score',
            'sync_frequency' => 'real-time',
            'priority' => 'high',
            'bidirectional' => false
        ],
        'stock_levels' => [
            'vitalvida_source' => 'VitalVidaProduct->stock_level', 
            'role_destination' => 'Bin->current_stock',
            'sync_frequency' => 'every 5 minutes',
            'priority' => 'high',
            'bidirectional' => false
        ],
        'compliance_actions' => [
            'role_source' => 'SystemCompliance->violation_status',
            'vitalvida_destination' => 'VitalVidaDeliveryAgent->compliance_status',
            'sync_frequency' => 'immediate',
            'priority' => 'critical',
            'bidirectional' => true
        ],
        'agent_locations' => [
            'vitalvida_source' => 'VitalVidaDeliveryAgent->location',
            'role_destination' => 'DeliveryAgent->zone',
            'sync_frequency' => 'real-time',
            'priority' => 'medium',
            'bidirectional' => false
        ],
        'supplier_data' => [
            'vitalvida_source' => 'VitalVidaSupplier->*',
            'role_destination' => 'Supplier->*',
            'sync_frequency' => 'hourly',
            'priority' => 'low',
            'bidirectional' => false
        ]
    ],
    'endpoints_mapping' => [
        'vitalvida_master' => [
            'dashboard' => '/api/vitalvida-inventory/dashboard',
            'agents' => '/api/vitalvida-inventory/delivery-agents',
            'inventory' => '/api/vitalvida-inventory/items',
            'suppliers' => '/api/vitalvida-inventory/suppliers',
            'analytics' => '/api/vitalvida-inventory/analytics/overview'
        ],
        'role_compliance' => [
            'dashboard' => '/api/dashboard/overview',
            'enforcement' => '/api/alerts/enforcement-tasks',
            'compliance' => '/api/dashboard/reviews',
            'bin_visibility' => '/api/dashboard/system-actions',
            'metrics' => '/api/dashboard/weekly-metrics'
        ]
    ],
    'data_flow_rules' => [
        'master_to_slave' => [
            'VitalVida Inventory' => 'Role Inventory Management',
            'direction' => 'unidirectional',
            'override_policy' => 'vitalvida_wins'
        ],
        'compliance_feedback' => [
            'Role Inventory Management' => 'VitalVida Inventory',
            'direction' => 'compliance_only',
            'fields' => ['compliance_score', 'violation_count', 'enforcement_actions']
        ]
    ]
];

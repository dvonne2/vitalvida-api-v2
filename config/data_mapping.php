<?php

return [
    'delivery_agents' => [
        'vitalvida' => [
            'table' => 'vitalvida_delivery_agents',
            'key_field' => 'id',
            'name_field' => 'name',
            'phone_field' => 'phone',
            'location_field' => 'location',
            'rating_field' => 'rating',
            'status_field' => 'status'
        ],
        'role' => [
            'table' => 'delivery_agents', 
            'key_field' => 'id',
            'external_id_field' => 'external_id',
            'name_field' => 'agent_name',
            'phone_field' => 'contact_number',
            'location_field' => 'zone',
            'rating_field' => 'performance_score',
            'status_field' => 'status'
        ],
        'mapping' => [
            'vitalvida.id' => 'role.external_id',
            'vitalvida.name' => 'role.agent_name',
            'vitalvida.phone' => 'role.contact_number',
            'vitalvida.location' => 'role.zone',
            'vitalvida.rating' => 'role.performance_score',
            'vitalvida.status' => 'role.status'
        ]
    ],
    'inventory' => [
        'vitalvida' => [
            'table' => 'vitalvida_products',
            'key_field' => 'id',
            'code_field' => 'product_code',
            'name_field' => 'name',
            'stock_field' => 'stock_level',
            'status_field' => 'status',
            'price_field' => 'price'
        ],
        'role' => [
            'table' => 'bins',
            'key_field' => 'id',
            'product_sku_field' => 'product_sku',
            'name_field' => 'product_name',
            'stock_field' => 'current_stock',
            'status_field' => 'bin_status',
            'price_field' => 'unit_price'
        ],
        'mapping' => [
            'vitalvida.product_code' => 'role.product_sku',
            'vitalvida.name' => 'role.product_name',
            'vitalvida.stock_level' => 'role.current_stock',
            'vitalvida.status' => 'role.bin_status',
            'vitalvida.price' => 'role.unit_price'
        ]
    ],
    'suppliers' => [
        'vitalvida' => [
            'table' => 'vitalvida_suppliers',
            'key_field' => 'id',
            'code_field' => 'supplier_code',
            'name_field' => 'company_name',
            'contact_field' => 'contact_person',
            'phone_field' => 'phone',
            'email_field' => 'email'
        ],
        'role' => [
            'table' => 'suppliers',
            'key_field' => 'id',
            'external_id_field' => 'external_id',
            'name_field' => 'name',
            'contact_field' => 'contact_person',
            'phone_field' => 'phone',
            'email_field' => 'email'
        ]
    ]
];

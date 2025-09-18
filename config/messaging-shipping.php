<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Messaging Shipping API Configuration
    |--------------------------------------------------------------------------
    */
    
    'api_key' => env('MESSAGING_SHIPPING_API_KEY'),
    'api_secret' => env('MESSAGING_SHIPPING_API_SECRET'),
    'api_url' => env('MESSAGING_SHIPPING_API_URL', 'https://api.messaging-shipping.com'),
    'timeout' => env('MESSAGING_SHIPPING_TIMEOUT', 30),
    'sandbox' => env('MESSAGING_SHIPPING_SANDBOX', false),
    'webhook_secret' => env('MESSAGING_SHIPPING_WEBHOOK_SECRET'),
    
    /*
    |--------------------------------------------------------------------------
    | Default Origin Configuration
    |--------------------------------------------------------------------------
    */
    
    'default_origin' => [
        'country' => env('MESSAGING_SHIPPING_ORIGIN_COUNTRY', 'CO'),
        'state' => env('MESSAGING_SHIPPING_ORIGIN_STATE', 'Bogotá D.C.'),
        'city' => env('MESSAGING_SHIPPING_ORIGIN_CITY', 'Bogotá'),
        'city_code' => env('MESSAGING_SHIPPING_ORIGIN_CITY_CODE', '11001'),
        'postal_code' => env('MESSAGING_SHIPPING_ORIGIN_POSTAL_CODE', '110111'),
        'address' => env('MESSAGING_SHIPPING_ORIGIN_ADDRESS', 'Calle 100 #19-61'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Service Types Configuration
    |--------------------------------------------------------------------------
    */
    
    'service_types' => [
        'standard' => [
            'name' => 'Envío Estándar',
            'description' => 'Entrega en 3-5 días hábiles',
            'enabled' => true,
        ],
        'express' => [
            'name' => 'Envío Express',
            'description' => 'Entrega en 1-2 días hábiles',
            'enabled' => true,
        ],
        'overnight' => [
            'name' => 'Envío Nocturno',
            'description' => 'Entrega al siguiente día hábil',
            'enabled' => false,
        ],
        'same_day' => [
            'name' => 'Mismo Día',
            'description' => 'Entrega el mismo día (solo ciudades principales)',
            'enabled' => false,
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Package Configuration
    |--------------------------------------------------------------------------
    */
    
    'package_limits' => [
        'max_weight' => 30, // kg
        'max_length' => 100, // cm
        'max_width' => 100, // cm
        'max_height' => 100, // cm
        'max_packages_per_shipment' => 10,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Carrier Configuration
    |--------------------------------------------------------------------------
    */
    
    'carrier' => [
        'code' => 'messaging-shipping',
        'title' => 'Messaging Shipping',
        'description' => 'Servicio de mensajería y envíos',
        'sort_order' => 1,
        'active' => true,
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Cache Configuration
    |--------------------------------------------------------------------------
    */
    
    'cache' => [
        'rates_ttl' => 3600, // 1 hour in seconds
        'tracking_ttl' => 1800, // 30 minutes in seconds
        'auth_token_ttl' => 7200, // 2 hours in seconds
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Logging Configuration
    |--------------------------------------------------------------------------
    */
    
    'logging' => [
        'enabled' => env('MESSAGING_SHIPPING_LOGGING', true),
        'channel' => env('LOG_CHANNEL', 'stack'),
        'level' => env('MESSAGING_SHIPPING_LOG_LEVEL', 'info'),
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Webhook Configuration
    |--------------------------------------------------------------------------
    */
    
    'webhook' => [
        'enabled' => env('MESSAGING_SHIPPING_WEBHOOK_ENABLED', true),
        'url' => env('APP_URL') . '/api/v1/messaging-shipping/webhook',
        'events' => [
            'shipment.created',
            'shipment.updated',
            'shipment.picked_up',
            'shipment.in_transit',
            'shipment.out_for_delivery',
            'shipment.delivered',
            'shipment.cancelled',
            'shipment.returned',
        ],
    ],
    
    /*
    |--------------------------------------------------------------------------
    | Retry Configuration
    |--------------------------------------------------------------------------
    */
    
    'retry' => [
        'max_attempts' => 3,
        'delay' => 1000, // milliseconds
        'backoff_multiplier' => 2,
    ],
];

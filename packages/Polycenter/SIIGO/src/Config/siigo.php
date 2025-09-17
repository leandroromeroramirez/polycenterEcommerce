<?php

return [
    /*
    |--------------------------------------------------------------------------
    | SIIGO API Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains the configuration options for SIIGO API integration
    |
    */

    // URL base del API de SIIGO
    'api_url' => env('SIIGO_API_URL', 'https://api.siigo.com/v1'),

    // Credenciales de autenticación
    'username' => env('SIIGO_USERNAME'),
    'access_key' => env('SIIGO_ACCESS_KEY'),

    // Configuración de timeout
    'timeout' => env('SIIGO_TIMEOUT', 30),

    // Configuración de retry
    'retry_attempts' => env('SIIGO_RETRY_ATTEMPTS', 3),
    'retry_delay' => env('SIIGO_RETRY_DELAY', 1000), // milliseconds

    // Configuración de logging
    'log_requests' => env('SIIGO_LOG_REQUESTS', true),
    'log_responses' => env('SIIGO_LOG_RESPONSES', true),

    // Configuración de caché
    'cache_enabled' => env('SIIGO_CACHE_ENABLED', true),
    'cache_ttl' => env('SIIGO_CACHE_TTL', 3600), // seconds

    // Endpoints específicos
    'endpoints' => [
        'auth' => '/auth',
        'customers' => '/customers',
        'products' => '/products',
        'invoices' => '/invoices',
        'credit_notes' => '/credit-notes',
        'taxes' => '/taxes',
        'payment_types' => '/payment-types',
    ],

    // Configuración de sincronización
    'sync' => [
        'enabled' => env('SIIGO_SYNC_ENABLED', false),
        'customers' => env('SIIGO_SYNC_CUSTOMERS', true),
        'products' => env('SIIGO_SYNC_PRODUCTS', true),
        'orders' => env('SIIGO_SYNC_ORDERS', true),
        'invoices' => env('SIIGO_SYNC_INVOICES', true),
    ],

    // Configuración de webhook
    'webhook' => [
        'enabled' => env('SIIGO_WEBHOOK_ENABLED', false),
        'secret' => env('SIIGO_WEBHOOK_SECRET'),
        'url' => env('SIIGO_WEBHOOK_URL'),
    ],

    // Configuración de mapeo de campos
    'field_mapping' => [
        'customer' => [
            'identification' => 'id',
            'name' => 'first_name',
            'last_name' => 'last_name',
            'email' => 'email',
            'phone' => 'phone',
            'address' => 'address1',
            'city' => 'city',
            'state' => 'state',
            'country' => 'country',
        ],
        'product' => [
            'code' => 'sku',
            'name' => 'name',
            'description' => 'description',
            'price' => 'price',
            'tax_rate' => 'tax_category_id',
        ],
    ],
];

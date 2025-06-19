<?php

return [
    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for the REST API
    |
    */

    'version' => env('API_VERSION', '1.0.0'),
    'prefix' => env('API_PREFIX', 'api'),

    'rate_limiting' => [
        'enabled' => env('API_RATE_LIMITING_ENABLED', true),
        'default_limit' => env('API_DEFAULT_RATE_LIMIT', 1000), // per hour
        'max_limit' => env('API_MAX_RATE_LIMIT', 10000), // per hour
        'window_seconds' => env('API_RATE_WINDOW', 3600), // 1 hour
    ],

    'authentication' => [
        'api_key_header' => env('API_KEY_HEADER', 'X-API-Key'),
        'api_key_length' => env('API_KEY_LENGTH', 32),
        'api_key_prefix' => env('API_KEY_PREFIX', 'tvs_'),
    ],

    'response' => [
        'include_request_id' => env('API_INCLUDE_REQUEST_ID', true),
        'include_timestamp' => env('API_INCLUDE_TIMESTAMP', true),
        'pretty_json' => env('API_PRETTY_JSON', false),
    ],

    'cors' => [
        'enabled' => env('API_CORS_ENABLED', true),
        'allowed_origins' => env('API_CORS_ORIGINS', '*'),
        'allowed_methods' => env('API_CORS_METHODS', 'GET,POST,PUT,DELETE,OPTIONS'),
        'allowed_headers' => env('API_CORS_HEADERS', 'Content-Type,Authorization,X-API-Key,X-Request-ID'),
    ],

    'documentation' => [
        'enabled' => env('API_DOCS_ENABLED', true),
        'title' => 'Tokenization Vault System API',
        'description' => 'Secure tokenization and vault management API',
        'contact' => [
            'name' => env('API_CONTACT_NAME', 'API Support'),
            'email' => env('API_CONTACT_EMAIL', 'api-support@example.com'),
        ],
    ],
];

<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tokenization Configuration
    |--------------------------------------------------------------------------
    |
    | Settings for tokenization algorithms and token generation
    |
    */

    'token_settings' => [
        'default_length' => env('TOKEN_DEFAULT_LENGTH', 32),
        'min_length' => env('TOKEN_MIN_LENGTH', 16),
        'max_length' => env('TOKEN_MAX_LENGTH', 64),
        'prefix_length' => env('TOKEN_PREFIX_LENGTH', 4),
    ],

    'algorithms' => [
        'random' => [
            'enabled' => true,
            'charset' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789',
        ],
        'format_preserving' => [
            'enabled' => env('TOKEN_FPE_ENABLED', true),
            'preserve_length' => true,
            'preserve_format' => true,
        ],
        'sequential' => [
            'enabled' => env('TOKEN_SEQUENTIAL_ENABLED', false),
            'start_value' => 1000000,
        ],
    ],

    'validation' => [
        'check_luhn' => env('TOKEN_CHECK_LUHN', true),
        'validate_format' => env('TOKEN_VALIDATE_FORMAT', true),
        'check_blacklist' => env('TOKEN_CHECK_BLACKLIST', true),
    ],

    'blacklisted_patterns' => [
        '4111111111111111',
        '4000000000000002',
        '5555555555554444',
        '123456789',
        '987654321',
        '000000000',
    ],
];

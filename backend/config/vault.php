<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Vault System Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration settings for the tokenization vault system
    |
    */

    'default_settings' => [
        'max_tokens' => env('VAULT_DEFAULT_MAX_TOKENS', 1000000),
        'retention_days' => env('VAULT_DEFAULT_RETENTION_DAYS', 2555), // 7 years
        'key_rotation_interval_days' => env('VAULT_DEFAULT_KEY_ROTATION_DAYS', 365),
        'encryption_algorithm' => 'AES-256-GCM',
    ],

    'allowed_data_types' => [
        'card' => 'Credit Card Numbers',
        'ssn' => 'Social Security Numbers',
        'bank_account' => 'Bank Account Numbers',
        'custom' => 'Custom Data Types',
    ],

    'allowed_operations' => [
        'tokenize' => 'Create tokens from sensitive data',
        'detokenize' => 'Retrieve original data from tokens',
        'search' => 'Search tokens by metadata',
        'bulk_tokenize' => 'Batch tokenization operations',
        'bulk_detokenize' => 'Batch detokenization operations',
        'revoke' => 'Revoke existing tokens',
    ],

    'security' => [
        'max_failed_attempts' => env('VAULT_MAX_FAILED_ATTEMPTS', 5),
        'lockout_duration_minutes' => env('VAULT_LOCKOUT_DURATION', 15),
        'require_ip_whitelist' => env('VAULT_REQUIRE_IP_WHITELIST', false),
        'max_bulk_size' => env('VAULT_MAX_BULK_SIZE', 1000),
    ],

    'performance' => [
        'cache_ttl_seconds' => env('VAULT_CACHE_TTL', 300),
        'max_search_results' => env('VAULT_MAX_SEARCH_RESULTS', 1000),
        'batch_size' => env('VAULT_BATCH_SIZE', 100),
    ],

    'compliance' => [
        'pci_dss_enabled' => env('VAULT_PCI_DSS_ENABLED', true),
        'audit_all_operations' => env('VAULT_AUDIT_ALL_OPERATIONS', true),
        'require_encryption_at_rest' => env('VAULT_REQUIRE_ENCRYPTION_AT_REST', true),
        'require_encryption_in_transit' => env('VAULT_REQUIRE_ENCRYPTION_IN_TRANSIT', true),
    ],
];

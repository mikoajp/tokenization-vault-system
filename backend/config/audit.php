<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Audit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for PCI DSS compliant audit logging
    |
    */

    'enabled' => env('AUDIT_ENABLED', true),

    /*
    |--------------------------------------------------------------------------
    | Audit Log Retention
    |--------------------------------------------------------------------------
    |
    | How long to keep audit logs (in days)
    | PCI DSS requires minimum 1 year, recommended 3+ years
    |
    */
    'retention_days' => env('AUDIT_RETENTION_DAYS', 2555), // 7 years

    /*
    |--------------------------------------------------------------------------
    | High Risk Operations
    |--------------------------------------------------------------------------
    |
    | Operations that should be flagged as high risk
    |
    */
    'high_risk_operations' => [
        'detokenize',
        'bulk_detokenize',
        'key_rotation',
        'vault_delete',
        'token_revoke',
        'admin_override',
    ],

    /*
    |--------------------------------------------------------------------------
    | Security Alert Thresholds
    |--------------------------------------------------------------------------
    |
    | Thresholds for security alerting
    |
    */
    'security_thresholds' => [
        'failed_operations_per_ip' => 5,
        'detokenize_operations_per_user_hour' => 100,
        'bulk_operations_per_user_day' => 10,
    ],

    /*
    |--------------------------------------------------------------------------
    | Sensitive Data Patterns
    |--------------------------------------------------------------------------
    |
    | Regex patterns to identify sensitive data that should be redacted
    |
    */
    'sensitive_patterns' => [
        'credit_card' => '/\b\d{4}[\s-]?\d{4}[\s-]?\d{4}[\s-]?\d{4}\b/',
        'ssn' => '/\b\d{3}-?\d{2}-?\d{4}\b/',
        'phone' => '/\b\d{3}-?\d{3}-?\d{4}\b/',
        'email' => '/\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/',
    ],

    /*
    |--------------------------------------------------------------------------
    | Compliance Report Types
    |--------------------------------------------------------------------------
    |
    | Available compliance report types
    |
    */
    'report_types' => [
        'pci_dss' => 'PCI DSS Compliance Report',
        'sox' => 'SOX Compliance Report',
        'gdpr' => 'GDPR Compliance Report',
        'security_audit' => 'Security Audit Report',
        'access_review' => 'Access Review Report',
    ],
];

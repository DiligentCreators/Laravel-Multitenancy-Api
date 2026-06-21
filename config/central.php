<?php

return [
    'billing' => [
        'grace_period' => env('BILLING_GRACE_PERIOD', 7),
        'trial_days' => env('BILLING_TRIAL_DAYS', 14),
        'tax_percentage' => env('BILLING_TAX_PERCENTAGE', 0),
        'currency' => env('BILLING_CURRENCY', 'USD'),
        'dunning' => [
            'max_attempts' => env('BILLING_DUNNING_MAX_ATTEMPTS', 5),
            'retry_delay_hours' => [0, 24, 72, 168, 336],
            'auto_escalate' => env('BILLING_DUNNING_AUTO_ESCALATE', true),
        ],
        'proration' => [
            'enabled' => env('BILLING_PRORATION_ENABLED', true),
            'apply_credits' => env('BILLING_PRORATION_APPLY_CREDITS', true),
        ],
    ],
    'tax' => [
        'default_region' => env('TAX_DEFAULT_REGION', null),
        'auto_calculate' => env('TAX_AUTO_CALCULATE', true),
    ],
    'enforcement' => [
        'enabled' => env('USAGE_ENFORCEMENT_ENABLED', true),
    ],
    'caching' => [
        'analytics_ttl' => env('ANALYTICS_CACHE_TTL', 3600),
        'dashboard_ttl' => env('DASHBOARD_CACHE_TTL', 3600),
    ],
    'exports' => [
        'cleanup_days' => env('EXPORT_CLEANUP_DAYS', 30),
        'disk' => env('EXPORT_DISK', 'local'),
    ],
    'audit' => [
        'log_impersonation' => true,
        'log_config_changes' => true,
        'log_data_exports' => true,
    ],
];

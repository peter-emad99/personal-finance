<?php

return [
    'backup_retention_days' => (int) env('FINANCE_BACKUP_RETENTION_DAYS', 90),
    'owner_email' => env('FINANCE_OWNER_EMAIL', 'owner@localhost'),
    'owner_name' => env('FINANCE_OWNER_NAME', 'Local owner'),
    'owner_password' => env('FINANCE_OWNER_PASSWORD'),
    'demo_enabled' => filter_var(env('FINANCE_SEED_DEMO', true), FILTER_VALIDATE_BOOLEAN),
    'demo_email' => env('FINANCE_DEMO_EMAIL', 'demo@finance.local'),
    'demo_name' => env('FINANCE_DEMO_NAME', 'Demo Wealth Builder'),
    'demo_password' => env('FINANCE_DEMO_PASSWORD', 'demo-finance-2026'),
    'market_rates' => [
        'fx_url' => env('FINANCE_FX_API_URL', 'https://open.er-api.com/v6/latest/USD'),
        'gold_url' => env('FINANCE_GOLD_API_URL', 'https://api.gold-api.com/price/XAU'),
        'stale_after_hours' => (int) env('FINANCE_MARKET_RATES_STALE_HOURS', 48),
    ],
];

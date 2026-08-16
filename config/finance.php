<?php

return [
    'backup_retention_days' => (int) env('FINANCE_BACKUP_RETENTION_DAYS', 90),
    'owner_email' => env('FINANCE_OWNER_EMAIL', 'owner@localhost'),
    'owner_name' => env('FINANCE_OWNER_NAME', 'Local owner'),
    'owner_password' => env('FINANCE_OWNER_PASSWORD'),
];

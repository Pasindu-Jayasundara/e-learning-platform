<?php
// Example config (copy to includes/config.php if you prefer hardcoded defaults).
// Recommended: use environment variables via scripts/env.local.ps1 (Windows) or export in your shell.
return [
    'db_host' => '127.0.0.1',
    'db_name' => 'e_learning',
    'db_user' => 'root',
    'db_pass' => '', // set via env: DB_PASS

    // Debug: set APP_DEBUG=1 in env for development
    'display_errors' => true,

    // Stripe keys (prefer env STRIPE_SECRET_KEY, STRIPE_PUBLISHABLE_KEY, STRIPE_WEBHOOK_SECRET)
    'stripe_secret' => '',
    'stripe_publishable' => '',
    'stripe_webhook_secret' => '',

    // Email
    'mail_from' => 'no-reply@localhost',
    'smtp' => [
        'host' => '',
        'port' => 587,
        'username' => '',
        'password' => '',
        'encryption' => 'tls',
    ],

    'notification_log_path' => __DIR__ . '/../storage/notifications.log',
];

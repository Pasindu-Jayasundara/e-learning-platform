<?php
// Local configuration — using MySQL (local DB)
return [
    // MySQL settings
    'db_host' => '127.0.0.1',
    'db_name' => 'e_learning',
    'db_user' => 'root',
    'db_pass' => 'Pasindu328@Bhathiya',

    // Error display in development
    'display_errors' => true,

    // Stripe keys - set these with your real keys for live integration
    // You can also set environment variables STRIPE_SECRET_KEY and STRIPE_PUBLISHABLE_KEY
    'stripe_secret' => getenv('STRIPE_SECRET_KEY') ?: '',
    'stripe_publishable' => getenv('STRIPE_PUBLISHABLE_KEY') ?: '',
    // Comma-separated or array of moderator emails to notify on new reports
    'moderator_emails' => [
        // 'moderator@example.com',
    ],
    // From address to use when sending notifications
    'mail_from' => 'pasindubathiya28@gmail.com',
    // SMTP settings (optional) for PHPMailer
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'username' => 'pasindubathiya28@gmail.com',
        'password' => '', // Gmail App Password
        'encryption' => 'tls'
    ],
    // Path where file-based notifications are stored if SMTP/mail() unavailable
    'notification_log_path' => __DIR__ . '/../storage/notifications.log',
];

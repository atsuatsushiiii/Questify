<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Outbound mail.
 *
 * Local development writes messages to storage/logs/mail.log. Production uses
 * SMTP; if MAIL_TRANSPORT is omitted outside local, SMTP is assumed and the
 * send fails fast when MAIL_HOST is missing instead of silently dropping mail.
 */
return [
    'transport' => (string) env(
        'MAIL_TRANSPORT',
        (string) env('APP_ENV', 'production') === 'local' ? 'log' : 'smtp',
    ),

    'host' => (string) env('MAIL_HOST', ''),
    'port' => (int) env('MAIL_PORT', 587),
    'username' => (string) env('MAIL_USERNAME', ''),
    'password' => (string) env('MAIL_PASSWORD', ''),
    'encryption' => (string) env('MAIL_ENCRYPTION', 'tls'),
    'timeout' => (int) env('MAIL_TIMEOUT', 10),

    'from_address' => (string) env('MAIL_FROM_ADDRESS', 'no-reply@questify.local'),
    'from_name' => (string) env('MAIL_FROM_NAME', (string) env('APP_NAME', 'Questify')),
];

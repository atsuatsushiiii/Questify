<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Authentication and account-security rules.
 */
return [
    'remember_cookie' => (string) env('REMEMBER_COOKIE_NAME', 'questify_remember'),
    'remember_lifetime' => 60 * 60 * 24 * 30,
    'remember_secure' => (bool) env('SESSION_SECURE', false),

    'password_reset_ttl' => 60 * 60,
    'password_reset_per_hour' => 3,

    'email_change_ttl' => 60 * 60,
    'email_change_per_hour' => 3,

    'max_login_attempts' => 5,
    'max_login_attempts_per_ip' => 20,
    'login_decay' => 60 * 15,

    'min_password_length' => 10,
    'max_password_length' => 72,
];

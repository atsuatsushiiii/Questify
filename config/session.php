<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return [
    'cookie_name' => (string) env('SESSION_COOKIE_NAME', 'questify_session'),
    'idle_timeout' => 1800,
    'absolute_timeout' => 43200,
    'secure' => (bool) env('SESSION_SECURE', false),
    'same_site' => 'Lax',
];

<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return [
    'name' => (string) env('APP_NAME', 'Questify'),
    'env' => (string) env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => rtrim((string) env('APP_URL', 'http://localhost:8000'), '/'),
    'timezone' => 'UTC',
    'version' => '0.1.0',
];

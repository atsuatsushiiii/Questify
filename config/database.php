<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

return [
    'host' => (string) env('DB_HOST', 'localhost'),
    'port' => (int) env('DB_PORT', 3306),
    'database' => (string) env('DB_DATABASE', 'questify'),
    'username' => (string) env('DB_USERNAME', 'questify'),
    'password' => (string) env('DB_PASSWORD', ''),
    'charset' => 'utf8mb4',
];

<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Router for PHP's built-in development server.
 *
 * Usage: php -S localhost:8000 -t public scripts/router.php
 */
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/';
$file = __DIR__ . '/../public' . $path;

if ($path !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/../public/index.php';

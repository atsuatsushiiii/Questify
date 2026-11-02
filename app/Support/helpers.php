<?php

declare(strict_types=1);

use App\Core\Config;
use App\Core\HttpException;
use App\Core\Response;
use App\Core\View;

if (!function_exists('env')) {
    /**
     * Read an environment variable with light type coercion.
     */
    function env(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === null || $value === '') {
            return $default;
        }

        if (!is_string($value)) {
            return $value;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Config::get($key, $default);
    }
}

if (!function_exists('e')) {
    /**
     * Escape output for HTML.
     */
    function e(mixed $value): string
    {
        return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

if (!function_exists('url')) {
    function url(string $path = ''): string
    {
        $base = rtrim((string) config('app.url', ''), '/');

        if ($path === '') {
            return $base === '' ? '/' : $base;
        }

        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('asset')) {
    /**
     * Root-relative asset URL with a cache-busting file timestamp.
     */
    function asset(string $path): string
    {
        $relative = '/' . ltrim($path, '/');
        $file = (string) config('paths.public', '') . $relative;
        $version = is_file($file) ? (string) filemtime($file) : (string) config('app.version', '1');

        return $relative . '?v=' . $version;
    }
}

if (!function_exists('view')) {
    /**
     * @param array<string, mixed> $data
     */
    function view(string $view, array $data = [], ?string $layout = 'layouts/public'): Response
    {
        return View::render($view, $data, $layout);
    }
}

if (!function_exists('redirect')) {
    function redirect(string $path, int $status = 302): Response
    {
        return Response::redirect(url($path), $status);
    }
}

if (!function_exists('abort')) {
    function abort(int $status, string $message = ''): never
    {
        throw new HttpException($status, $message);
    }
}

if (!function_exists('nav_active')) {
    /**
     * True when the current request path matches, or sits below, the target.
     */
    function nav_active(string $path): bool
    {
        $current = '/' . trim((string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH), '/');

        if ($path === '/') {
            return $current === '/';
        }

        $target = '/' . trim($path, '/');

        return $current === $target || str_starts_with($current, $target . '/');
    }
}

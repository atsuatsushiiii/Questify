<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

/**
 * Lightweight dot-notation configuration store.
 */
final class Config
{
    /** @var array<string, mixed> */
    private static array $items = [];

    public static function load(string $directory): void
    {
        $directory = rtrim($directory, '/\\');

        foreach (glob($directory . DIRECTORY_SEPARATOR . '*.php') ?: [] as $file) {
            $key = pathinfo($file, PATHINFO_FILENAME);
            self::$items[$key] = require $file;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }

            $value = $value[$segment];
        }

        return $value;
    }

    public static function set(string $key, mixed $value): void
    {
        $target = &self::$items;

        foreach (explode('.', $key) as $segment) {
            if (!isset($target[$segment]) || !is_array($target[$segment])) {
                $target[$segment] = [];
            }

            $target = &$target[$segment];
        }

        $target = $value;
    }

    /** @return array<string, mixed> */
    public static function all(): array
    {
        return self::$items;
    }
}

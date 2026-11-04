<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

use PDO;
use Throwable;

/**
 * Shared PDO connection. Every query in the application goes through
 * prepared statements; emulation is disabled.
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $config = (array) Config::get('database', []);

        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            (string) ($config['host'] ?? 'localhost'),
            (int) ($config['port'] ?? 3306),
            (string) ($config['database'] ?? ''),
            (string) ($config['charset'] ?? 'utf8mb4'),
        );

        self::$pdo = new PDO(
            $dsn,
            (string) ($config['username'] ?? ''),
            (string) ($config['password'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ],
        );

        // Every DATETIME in the application is UTC.
        self::$pdo->exec("SET time_zone = '+00:00'");

        return self::$pdo;
    }

    public static function ping(): bool
    {
        try {
            return (bool) self::connection()->query('SELECT 1')->fetchColumn();
        } catch (Throwable) {
            return false;
        }
    }

    public static function reset(): void
    {
        self::$pdo = null;
    }
}

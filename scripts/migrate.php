<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

use App\Core\App;
use App\Core\Database;

/**
 * Minimal forward-only migration runner with batch rollback.
 *
 * Usage:
 *   php scripts/migrate.php [migrate|status|rollback]
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

App::boot(BASE_PATH, false);

$command = $argv[1] ?? 'migrate';

if (!in_array($command, ['migrate', 'status', 'rollback'], true)) {
    fwrite(STDERR, "Unknown command: {$command}\n");
    fwrite(STDERR, "Usage: php scripts/migrate.php [migrate|status|rollback]\n");
    exit(1);
}

$pdo = Database::connection();

$pdo->exec(
    'CREATE TABLE IF NOT EXISTS migrations (
        id INT UNSIGNED NOT NULL AUTO_INCREMENT,
        migration VARCHAR(255) NOT NULL,
        batch INT UNSIGNED NOT NULL,
        applied_at DATETIME NOT NULL,
        PRIMARY KEY (id),
        UNIQUE KEY uq_migrations_name (migration)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci',
);

$directory = BASE_PATH . '/database/migrations';
$files = glob($directory . '/*.php') ?: [];
sort($files, SORT_STRING);

$appliedRows = $pdo->query('SELECT migration FROM migrations ORDER BY migration')->fetchAll(PDO::FETCH_COLUMN);
$applied = array_flip($appliedRows);

if ($command === 'status') {
    echo "Migration status\n";
    echo "----------------\n";

    foreach ($files as $file) {
        $name = basename($file);
        echo (isset($applied[$name]) ? '[applied] ' : '[pending] ') . $name . PHP_EOL;
    }

    if ($files === []) {
        echo "No migration files found.\n";
    }

    exit(0);
}

if ($command === 'rollback') {
    $batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) FROM migrations')->fetchColumn();

    if ($batch === 0) {
        echo "Nothing to roll back.\n";
        exit(0);
    }

    $statement = $pdo->prepare('SELECT migration FROM migrations WHERE batch = ? ORDER BY migration DESC');
    $statement->execute([$batch]);
    $names = $statement->fetchAll(PDO::FETCH_COLUMN);

    foreach ($names as $name) {
        $file = $directory . '/' . $name;

        if (!is_file($file)) {
            fwrite(STDERR, "[FAIL] Missing migration file: {$name}\n");
            exit(1);
        }

        $migration = require $file;

        if (!is_object($migration) || !method_exists($migration, 'down')) {
            fwrite(STDERR, "[FAIL] {$name} has no down() method.\n");
            exit(1);
        }

        try {
            $migration->down($pdo);
            $delete = $pdo->prepare('DELETE FROM migrations WHERE migration = ?');
            $delete->execute([$name]);
            echo "[ROLLED BACK] {$name}\n";
        } catch (Throwable $exception) {
            fwrite(STDERR, "[FAIL] {$name}: {$exception->getMessage()}\n");
            exit(1);
        }
    }

    exit(0);
}

$pending = array_values(array_filter(
    $files,
    static fn (string $file): bool => !isset($applied[basename($file)]),
));

if ($pending === []) {
    echo "Nothing to migrate.\n";
    exit(0);
}

$batch = (int) $pdo->query('SELECT COALESCE(MAX(batch), 0) + 1 FROM migrations')->fetchColumn();

foreach ($pending as $file) {
    $name = basename($file);
    $migration = require $file;

    if (!is_object($migration) || !method_exists($migration, 'up')) {
        fwrite(STDERR, "[FAIL] {$name} has no up() method.\n");
        exit(1);
    }

    $pdo->beginTransaction();

    try {
        $migration->up($pdo);

        $insert = $pdo->prepare('INSERT INTO migrations (migration, batch, applied_at) VALUES (?, ?, UTC_TIMESTAMP())');
        $insert->execute([$name, $batch]);

        if ($pdo->inTransaction()) {
            $pdo->commit();
        }

        echo "[MIGRATED] {$name}\n";
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }

        fwrite(STDERR, "[FAIL] {$name}: {$exception->getMessage()}\n");
        exit(1);
    }
}

echo "Batch {$batch} complete.\n";

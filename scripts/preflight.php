<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Production readiness checks.
 *
 * Usage: php scripts/preflight.php
 *
 * Exits non-zero when a blocking check fails. Warnings never block.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("This script is CLI only.\n");
}

use App\Core\App;
use App\Core\Database;

define('BASE_PATH', dirname(__DIR__));

$passed = 0;
$failed = 0;
$warnings = 0;

/**
 * @param bool $warnOnly Report a failure as a warning instead of a blocker.
 */
function line(string $label, bool $ok, string $detail = '', bool $warnOnly = false): void
{
    global $passed, $failed, $warnings;

    if ($ok) {
        $passed++;
        echo "  [ok]   {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;

        return;
    }

    if ($warnOnly) {
        $warnings++;
        echo "  [warn] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;

        return;
    }

    $failed++;
    echo "  [fail] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
}

function section(string $name): void
{
    echo PHP_EOL . $name . PHP_EOL . str_repeat('-', strlen($name)) . PHP_EOL;
}

echo "Questify production preflight\n";

section('Runtime');
line('PHP 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);

foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'json', 'fileinfo'] as $extension) {
    line("Extension: {$extension}", extension_loaded($extension));
}

section('Files');
$autoload = BASE_PATH . '/vendor/autoload.php';
$canBoot = is_file($autoload);
line('Composer dependencies installed', $canBoot, $canBoot ? 'vendor/autoload.php' : 'run composer install');
line('.env exists', is_file(BASE_PATH . '/.env'), is_file(BASE_PATH . '/.env') ? '.env' : 'copy .env.production.example to .env');
$cssBuilt = is_file(BASE_PATH . '/public/assets/css/app.css');
$jsBuilt = is_file(BASE_PATH . '/public/assets/js/app.js');
line('Built CSS exists', $cssBuilt, $cssBuilt ? 'public/assets/css/app.css' : 'run npm ci && npm run build');
line('Built JavaScript exists', $jsBuilt, $jsBuilt ? 'public/assets/js/app.js' : 'run npm ci && npm run build');

foreach ([
    'storage',
    'storage/logs',
    'storage/cache',
    'storage/uploads',
    'public/uploads',
    'public/uploads/avatars',
] as $path) {
    $absolute = BASE_PATH . '/' . $path;
    line("Writable: {$path}", is_dir($absolute) && is_writable($absolute));
}

if (!$canBoot) {
    echo PHP_EOL . "Blocking problems found: {$failed}. Fix the items above and re-run.\n";
    exit(1);
}

require $autoload;

App::boot(BASE_PATH, false);

section('Configuration');
$environment = (string) config('app.env');
$url = (string) config('app.url');
$https = str_starts_with(strtolower($url), 'https://');
$production = $environment === 'production';

line('APP_ENV is production', $production, $production ? 'production' : "got {$environment}", !$production);
line('APP_DEBUG is off', !(bool) config('app.debug'), (bool) config('app.debug') ? 'set APP_DEBUG=false' : 'false', !$production);
line('APP_URL is set', $url !== '', $url);
line('APP_URL uses HTTPS', $https, $url, !$production);

if ($https) {
    $secureCookie = (bool) env('SESSION_SECURE', false);
    line('SESSION_SECURE is enabled', $secureCookie, $secureCookie ? 'true' : 'set SESSION_SECURE=true');
}

$googleId = (string) config('oauth.google.client_id');
$googleSecret = (string) config('oauth.google.client_secret');

if ($googleId !== '' || $googleSecret !== '') {
    $googleReady = $googleId !== '' && $googleSecret !== '';
    line('Google client ID and secret are both set', $googleReady, $googleReady ? 'configured' : 'set both values or neither');

    $redirect = (string) config('oauth.google.redirect_uri');
    $redirect = $redirect !== '' ? $redirect : $url . '/auth/google/callback';

    line(
        'Google redirect URI matches APP_URL',
        str_starts_with($redirect, $url . '/'),
        $redirect,
    );
} else {
    echo "  [info] Google sign-in is not configured (optional).\n";
}

section('Database');

try {
    $pdo = Database::connection();
    line('Database connection', true, 'MySQL ' . (string) $pdo->query('SELECT VERSION()')->fetchColumn());

    $directory = BASE_PATH . '/database/migrations';
    $files = glob($directory . '/*.php') ?: [];
    sort($files, SORT_STRING);

    $applied = [];

    try {
        $applied = $pdo->query('SELECT migration FROM migrations')->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable) {
        // The migrations table does not exist yet, so every file is pending.
    }

    $applied = array_flip(array_map('strval', $applied));
    $pending = array_values(array_filter(
        $files,
        static fn (string $file): bool => !isset($applied[basename($file)]),
    ));

    line(
        'All migrations applied',
        $pending === [],
        $pending === []
            ? count($files) . ' applied'
            : count($pending) . ' pending — run php scripts/migrate.php migrate',
    );
} catch (Throwable $exception) {
    line('Database connection', false, $exception->getMessage());
}

section('Mail');

if ($production) {
    $transport = (string) config('mail.transport');
    line('Mail transport is not the local log', $transport !== 'log', "got {$transport}");

    if ($transport === 'smtp') {
        $mailHost = (string) config('mail.host');
        $mailFrom = (string) config('mail.from_address');
        line('MAIL_HOST is set', $mailHost !== '', $mailHost !== '' ? $mailHost : 'set MAIL_HOST');
        line('MAIL_FROM_ADDRESS is set', $mailFrom !== '', $mailFrom !== '' ? $mailFrom : 'set MAIL_FROM_ADDRESS');
    }
} else {
    echo "  [info] Non-production environment; mail checks skipped.\n";
}

echo PHP_EOL . str_repeat('=', 46) . PHP_EOL;
echo "  {$passed} passed, {$warnings} warning(s), {$failed} failure(s)\n";

if ($failed > 0) {
    echo "Preflight failed. Fix the blocking items before going live.\n";
    exit(1);
}

echo "Preflight passed.\n";
exit(0);

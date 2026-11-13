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

final class PreflightReport
{
    public int $passed = 0;
    public int $failed = 0;
    public int $warnings = 0;

    /**
     * @param bool $warnOnly Report a failure as a warning instead of a blocker.
     */
    public function line(string $label, bool $ok, string $detail = '', bool $warnOnly = false): void
    {
        if ($ok) {
            $this->passed++;
            echo "  [ok]   {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;

            return;
        }

        if ($warnOnly) {
            $this->warnings++;
            echo "  [warn] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;

            return;
        }

        $this->failed++;
        echo "  [fail] {$label}" . ($detail !== '' ? " — {$detail}" : '') . PHP_EOL;
    }
}

$report = new PreflightReport();

function section(string $name): void
{
    echo PHP_EOL . $name . PHP_EOL . str_repeat('-', strlen($name)) . PHP_EOL;
}

echo "Questify production preflight\n";

section('Runtime');
$report->line('PHP 8.2 or newer', version_compare(PHP_VERSION, '8.2.0', '>='), PHP_VERSION);

foreach (['pdo', 'pdo_mysql', 'mbstring', 'openssl', 'curl', 'json', 'fileinfo'] as $extension) {
    $report->line("Extension: {$extension}", extension_loaded($extension));
}

section('Files');
$autoload = BASE_PATH . '/vendor/autoload.php';
$canBoot = is_file($autoload);
$report->line('Composer dependencies installed', $canBoot, $canBoot ? 'vendor/autoload.php' : 'run composer install');
$report->line('.env exists', is_file(BASE_PATH . '/.env'), is_file(BASE_PATH . '/.env') ? '.env' : 'copy .env.production.example to .env');
$cssBuilt = is_file(BASE_PATH . '/public/assets/css/app.css');
$jsBuilt = is_file(BASE_PATH . '/public/assets/js/app.js');
$report->line('Built CSS exists', $cssBuilt, $cssBuilt ? 'public/assets/css/app.css' : 'run npm ci && npm run build');
$report->line('Built JavaScript exists', $jsBuilt, $jsBuilt ? 'public/assets/js/app.js' : 'run npm ci && npm run build');

foreach ([
    'storage',
    'storage/logs',
    'storage/cache',
    'storage/uploads',
    'public/uploads',
    'public/uploads/avatars',
] as $path) {
    $absolute = BASE_PATH . '/' . $path;
    $report->line("Writable: {$path}", is_dir($absolute) && is_writable($absolute));
}

if (!$canBoot) {
    echo PHP_EOL . "Blocking problems found: {$report->failed}. Fix the items above and re-run.\n";
    exit(1);
}

require $autoload;

App::boot(BASE_PATH, false);

section('Configuration');
$environment = (string) config('app.env');
$url = (string) config('app.url');
$https = str_starts_with(strtolower($url), 'https://');
$production = $environment === 'production';

$report->line('APP_ENV is production', $production, $production ? 'production' : "got {$environment}", !$production);
$report->line('APP_DEBUG is off', !(bool) config('app.debug'), (bool) config('app.debug') ? 'set APP_DEBUG=false' : 'false', !$production);
$report->line('APP_URL is set', $url !== '', $url);
$report->line('APP_URL uses HTTPS', $https, $url, !$production);

if ($https) {
    $secureCookie = (bool) env('SESSION_SECURE', false);
    $report->line('SESSION_SECURE is enabled', $secureCookie, $secureCookie ? 'true' : 'set SESSION_SECURE=true');
}

$googleId = (string) config('oauth.google.client_id');
$googleSecret = (string) config('oauth.google.client_secret');

if ($googleId !== '' || $googleSecret !== '') {
    $googleReady = $googleId !== '' && $googleSecret !== '';
    $report->line('Google client ID and secret are both set', $googleReady, $googleReady ? 'configured' : 'set both values or neither');

    $redirect = (string) config('oauth.google.redirect_uri');
    $redirect = $redirect !== '' ? $redirect : $url . '/auth/google/callback';

    $report->line(
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
    $report->line('Database connection', true, 'MySQL ' . (string) $pdo->query('SELECT VERSION()')->fetchColumn());

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

    $report->line(
        'All migrations applied',
        $pending === [],
        $pending === []
            ? count($files) . ' applied'
            : count($pending) . ' pending — run php scripts/migrate.php migrate',
    );
} catch (Throwable $exception) {
    $report->line('Database connection', false, $exception->getMessage());
}

section('Mail');

if ($production) {
    $transport = (string) config('mail.transport');
    $report->line('Mail transport is not the local log', $transport !== 'log', "got {$transport}");

    if ($transport === 'smtp') {
        $mailHost = (string) config('mail.host');
        $mailFrom = (string) config('mail.from_address');
        $report->line('MAIL_HOST is set', $mailHost !== '', $mailHost !== '' ? $mailHost : 'set MAIL_HOST');
        $report->line('MAIL_FROM_ADDRESS is set', $mailFrom !== '', $mailFrom !== '' ? $mailFrom : 'set MAIL_FROM_ADDRESS');
    }
} else {
    echo "  [info] Non-production environment; mail checks skipped.\n";
}

echo PHP_EOL . str_repeat('=', 46) . PHP_EOL;
echo "  {$report->passed} passed, {$report->warnings} warning(s), {$report->failed} failure(s)\n";

if ($report->failed > 0) {
    echo "Preflight failed. Fix the blocking items before going live.\n";
    exit(1);
}

echo "Preflight passed.\n";
exit(0);

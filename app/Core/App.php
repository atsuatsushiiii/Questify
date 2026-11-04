<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

use Dotenv\Dotenv;
use RuntimeException;

/**
 * Application bootstrap.
 */
final class App
{
    private static string $root = '';

    public static function boot(string $root, bool $withSession = true): void
    {
        self::$root = rtrim($root, '/\\');

        $autoload = self::$root . '/vendor/autoload.php';

        if (!is_file($autoload)) {
            throw new RuntimeException('Composer dependencies are missing. Run: php composer.phar install');
        }

        require_once $autoload;

        if (class_exists(Dotenv::class)) {
            Dotenv::createImmutable(self::$root)->safeLoad();
        }

        Config::set('paths.root', self::$root);
        Config::set('paths.public', self::$root . '/public');
        Config::set('paths.storage', self::$root . '/storage');

        Config::load(self::$root . '/config');

        date_default_timezone_set((string) Config::get('app.timezone', 'UTC'));

        ErrorHandler::register((bool) Config::get('app.debug', false));
        View::init(self::$root . '/app/Views');

        self::sendSecurityHeaders();

        if ($withSession) {
            try {
                Session::start((array) Config::get('session', []));
            } catch (\Throwable $exception) {
                // Keep public pages available if the session store is down.
                ErrorHandler::report($exception);
            }
        }
    }

    public static function run(): void
    {
        $router = new Router();

        require self::$root . '/routes/web.php';
        require self::$root . '/routes/api.php';

        $response = $router->dispatch(Request::capture());
        $response->send();
    }

    public static function root(): string
    {
        return self::$root;
    }

    public static function sendSecurityHeaders(): void
    {
        if (headers_sent()) {
            return;
        }

        foreach ((array) Config::get('security.headers', []) as $name => $value) {
            header($name . ': ' . $value, true);
        }
    }
}

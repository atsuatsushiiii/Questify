<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * PHPUnit bootstrap.
 *
 * Boots the application's config/env layer without starting a session or
 * touching the database, so pure unit tests stay fast and isolated.
 */

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

App\Core\App::boot(BASE_PATH, false);

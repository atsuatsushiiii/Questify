<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

App\Core\App::boot(BASE_PATH);
App\Core\App::run();

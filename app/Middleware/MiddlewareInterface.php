<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Request;
use App\Core\Response;

/**
 * A route middleware layer.
 *
 * Closures stay supported for one-off checks; this interface gives the router a
 * concrete type for class-based middleware so static analysis can verify the
 * handle() contract.
 */
interface MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response;
}

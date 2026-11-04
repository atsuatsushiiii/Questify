<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;

/**
 * Keeps signed-in users out of guest-only pages.
 */
final class GuestMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return redirect('/dashboard');
        }

        return $next($request);
    }
}

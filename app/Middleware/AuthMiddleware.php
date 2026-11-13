<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\HttpException;

/**
 * Requires an authenticated user and remembers the intended destination.
 */
final class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (Auth::check()) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw HttpException::unauthorized();
        }

        if ($request->isMethod('GET')) {
            $target = (string) ($_SERVER['REQUEST_URI'] ?? $request->path());

            if (str_starts_with($target, '/') && !str_starts_with($target, '//')) {
                Session::put('url.intended', $target);
            }
        }

        Flash::error('Please sign in to continue.');

        return redirect('/login');
    }
}

<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;

/**
 * Rejects state-changing requests without a matching session token.
 */
final class VerifyCsrfToken
{
    public function handle(Request $request, callable $next): Response
    {
        $token = $request->input('_csrf_token');

        if (!Csrf::verify(is_string($token) ? $token : null)) {
            abort(419, 'Your session expired or the form token was invalid. Please try again.');
        }

        return $next($request);
    }
}

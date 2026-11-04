<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;

/**
 * Requires an authenticated administrator.
 *
 * Guests are sent to sign-in exactly like the regular auth middleware. An
 * authenticated account that is not an admin always receives 403, whether the
 * request asks for HTML or JSON.
 */
final class AdminMiddleware
{
    public function handle(Request $request, callable $next): Response
    {
        if (!Auth::check()) {
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

        $user = Auth::user();

        if ($user === null || ($user['role'] ?? '') !== 'admin') {
            throw HttpException::forbidden('Administrator access is required for this page.');
        }

        return $next($request);
    }
}

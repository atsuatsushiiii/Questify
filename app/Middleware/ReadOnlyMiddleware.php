<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Middleware;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\User;

/**
 * Blocks quest mutations and report submission for read-only accounts.
 *
 * Browsing, account settings, and sign-out stay available. The account is not
 * suspended: it keeps its session and can still review everything it owns.
 */
final class ReadOnlyMiddleware
{
    public const MESSAGE = 'Your account is read-only. You can browse and review your quests, but creating, editing, completing, archiving, restoring, or deleting quests and submitting reports is disabled while a moderator reviews your account. You can request a review from the read-only banner.';

    public function handle(Request $request, callable $next): Response
    {
        $user = Auth::user();

        if ($user === null || !User::isRestricted($user)) {
            return $next($request);
        }

        if ($request->wantsJson()) {
            throw HttpException::forbidden(self::MESSAGE);
        }

        Flash::error(self::MESSAGE);

        return redirect($this->fallback($request->path()));
    }

    private function fallback(string $path): string
    {
        if (str_starts_with($path, '/reports')) {
            return '/reports';
        }

        if (str_starts_with($path, '/quests')) {
            return '/quests';
        }

        return '/dashboard';
    }
}

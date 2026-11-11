<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\EmailVerification;
use App\Models\User;
use App\Services\EmailVerificationService;

/**
 * Signup email verification.
 *
 * The confirmation page is available without a session because the link is
 * often opened on a different device. The POST consumes the token; a guest is
 * returned to sign-in, while a signed-in owner lands back in Settings.
 */
final class EmailVerificationController
{
    public function show(Request $request): Response
    {
        $token = is_string($request->input('token')) ? trim((string) $request->input('token')) : '';

        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            Flash::error('This verification link is invalid.');

            return redirect($this->fallback());
        }

        $pending = EmailVerification::findValidByHash(hash('sha256', $token));
        $user = $pending === null ? null : User::find((int) $pending['user_id']);

        if ($pending === null || $user === null) {
            Flash::error('This verification link is invalid or has expired. Request a new one from Settings.');

            return redirect($this->fallback());
        }

        return view('pages/auth/verify-email', [
            'title' => 'Verify your email — Questify',
            'description' => 'Confirm the email address on your Questify account.',
            'email' => (string) $user['email'],
            'displayName' => (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer'),
            'token' => $token,
            'signedIn' => Auth::check(),
        ], 'layouts/auth');
    }

    public function verify(Request $request): Response
    {
        $token = is_string($request->body()['token'] ?? null) ? (string) $request->body()['token'] : '';
        $result = (new EmailVerificationService())->verify($token);

        if (!$result['ok']) {
            Flash::error((string) ($result['message'] ?? 'The email address could not be verified.'));

            return redirect($this->fallback());
        }

        Flash::success((string) $result['message']);

        return redirect($this->fallback());
    }

    public function resend(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        $result = (new EmailVerificationService())->send($user);

        if ($result['ok']) {
            Flash::success((string) $result['message']);
        } else {
            Flash::error((string) $result['message']);
        }

        return redirect('/settings#email');
    }

    private function fallback(): string
    {
        return Auth::check() ? '/settings' : '/login';
    }
}

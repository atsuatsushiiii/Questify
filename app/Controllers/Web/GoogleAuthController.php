<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\GoogleAuthService;

/**
 * Google sign-in entry points.
 *
 * Starting the flow is a CSRF-protected POST. The callback is a GET because
 * Google redirects the browser; the session-bound state value is the CSRF
 * defence for that leg.
 */
final class GoogleAuthController
{
    public function redirect(Request $request): Response
    {
        $service = new GoogleAuthService();

        if (!$service->isConfigured()) {
            Flash::error('Google sign-in is not available right now.');

            return redirect('/login');
        }

        $timezone = $request->body()['timezone'] ?? null;

        return Response::redirect($service->authorizationUrl([
            'intent' => 'login',
            'timezone' => is_string($timezone) ? $timezone : 'UTC',
        ]));
    }

    public function callback(Request $request): Response
    {
        $error = $request->input('error');

        if (is_string($error) && $error !== '') {
            Flash::error($error === 'access_denied'
                ? 'Google sign-in was cancelled.'
                : 'Google sign-in was not completed. Please try again.');

            return redirect(Auth::check() ? '/settings#security' : '/login');
        }

        $code = $request->input('code');
        $state = $request->input('state');

        $result = (new GoogleAuthService())->handleCallback(
            is_string($code) ? $code : '',
            is_string($state) ? $state : '',
            $request,
        );

        if (!$result['ok']) {
            Flash::error((string) ($result['message'] ?? 'Google sign-in failed. Please try again.'));

            return redirect(Auth::check() ? '/settings#security' : '/login');
        }

        Flash::success((string) $result['message']);

        return redirect((string) ($result['redirect'] ?? '/dashboard'));
    }

    public function link(Request $request): Response
    {
        $user = $this->user();
        $service = new GoogleAuthService();

        if (!$service->isConfigured()) {
            Flash::error('Google sign-in is not available right now.');

            return redirect('/settings#security');
        }

        return Response::redirect($service->authorizationUrl([
            'intent' => 'link',
            'user_id' => (int) $user['id'],
        ]));
    }

    public function unlink(Request $request): Response
    {
        $user = $this->user();
        $result = (new GoogleAuthService())->disconnect((int) $user['id'], $request->ip());

        if ($result['ok']) {
            Flash::success((string) $result['message']);
        } else {
            Flash::error((string) $result['message']);
        }

        return redirect('/settings#security');
    }

    /** @return array<string, mixed> */
    private function user(): array
    {
        $user = Auth::user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        return $user;
    }
}

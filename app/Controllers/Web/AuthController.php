<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController
{
    public function showLogin(Request $request): Response
    {
        return view('pages/auth/login', [
            'title' => 'Sign in — Questify',
            'description' => 'Sign in to Questify and pick up your quest log where you left off.',
            'identifier' => '',
            'errors' => [],
            'message' => null,
        ], 'layouts/auth');
    }

    public function login(Request $request): Response
    {
        $result = (new AuthService())->attempt($request->body(), $request);

        if (!$result['ok']) {
            $response = view('pages/auth/login', [
                'title' => 'Sign in — Questify',
                'description' => 'Sign in to Questify and pick up your quest log where you left off.',
                'identifier' => (string) ($result['old']['identifier'] ?? ''),
                'errors' => $result['errors'] ?? [],
                'message' => $result['message'] ?? null,
            ], 'layouts/auth')->withStatus((int) ($result['status'] ?? 422));

            if (isset($result['retry_after'])) {
                $response = $response->withHeader('Retry-After', (string) $result['retry_after']);
            }

            return $response;
        }

        Flash::success((string) $result['message']);

        return redirect(Auth::intended('/dashboard'));
    }

    public function showRegister(Request $request): Response
    {
        return view('pages/auth/register', [
            'title' => 'Create your account — Questify',
            'description' => 'Create a Questify account and turn everyday tasks into quests worth completing.',
            'old' => [
                'display_name' => '',
                'username' => '',
                'email' => '',
                'timezone' => 'UTC',
            ],
            'errors' => [],
            'message' => null,
            'timezones' => timezone_identifiers_list(),
        ], 'layouts/auth');
    }

    public function register(Request $request): Response
    {
        $result = (new AuthService())->register($request->body(), $request);

        if (!$result['ok']) {
            return view('pages/auth/register', [
                'title' => 'Create your account — Questify',
                'description' => 'Create a Questify account and turn everyday tasks into quests worth completing.',
                'old' => $result['old'] ?? [
                    'display_name' => '',
                    'username' => '',
                    'email' => '',
                    'timezone' => 'UTC',
                ],
                'errors' => $result['errors'] ?? [],
                'message' => $result['message'] ?? null,
                'timezones' => timezone_identifiers_list(),
            ], 'layouts/auth')->withStatus((int) ($result['status'] ?? 422));
        }

        Flash::success((string) $result['message']);

        return redirect((string) ($result['redirect'] ?? '/dashboard'));
    }

    public function logout(Request $request): Response
    {
        (new AuthService())->logout($request);
        Flash::success('You have been signed out. See you on the next quest.');

        return redirect('/login');
    }
}

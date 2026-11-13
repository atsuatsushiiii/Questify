<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Flash;
use App\Core\Request;
use App\Core\Response;
use App\Models\PasswordReset;
use App\Services\AuthService;

final class PasswordResetController
{
    public function showRequest(Request $request): Response
    {
        return view('pages/auth/forgot-password', [
            'title' => 'Reset your password — Questify',
            'description' => 'Request a password reset link for your Questify account.',
            'old' => ['email' => ''],
            'errors' => [],
            'message' => null,
        ], 'layouts/auth');
    }

    public function send(Request $request): Response
    {
        $result = (new AuthService())->requestPasswordReset($request->body(), $request);

        if (!$result['ok']) {
            return view('pages/auth/forgot-password', [
                'title' => 'Reset your password — Questify',
                'description' => 'Request a password reset link for your Questify account.',
                'old' => $result['old'] ?? ['email' => ''],
                'errors' => $result['errors'] ?? [],
                'message' => $result['message'],
            ], 'layouts/auth')->withStatus((int) ($result['status'] ?? 422));
        }

        Flash::success((string) $result['message']);

        return redirect('/forgot-password');
    }

    public function showReset(Request $request): Response
    {
        $token = $this->token($request);
        $valid = $token !== null && $this->isValidToken($token);

        return view('pages/auth/reset-password', [
            'title' => 'Choose a new password — Questify',
            'description' => 'Choose a new password for your Questify account.',
            'token' => $token ?? '',
            'invalidToken' => !$valid,
            'errors' => [],
            'message' => null,
        ], 'layouts/auth');
    }

    public function reset(Request $request): Response
    {
        $result = (new AuthService())->resetPassword($request->body());
        $token = $this->token($request);

        if (!$result['ok']) {
            return view('pages/auth/reset-password', [
                'title' => 'Choose a new password — Questify',
                'description' => 'Choose a new password for your Questify account.',
                'token' => $token ?? '',
                'invalidToken' => $token === null || !$this->isValidToken($token),
                'errors' => $result['errors'] ?? [],
                'message' => $result['message'],
            ], 'layouts/auth')->withStatus((int) ($result['status'] ?? 422));
        }

        Flash::success((string) $result['message']);

        return redirect('/login');
    }

    private function token(Request $request): ?string
    {
        $token = $request->input('token');

        if (!is_string($token) || strlen($token) !== 64 || !ctype_xdigit($token)) {
            return null;
        }

        return $token;
    }

    private function isValidToken(string $token): bool
    {
        return PasswordReset::findValidByHash(hash('sha256', $token)) !== null;
    }
}

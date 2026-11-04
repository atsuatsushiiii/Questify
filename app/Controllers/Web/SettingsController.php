<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\EmailChangeRequest;
use App\Services\SettingsService;

/**
 * Owner-only account settings.
 *
 * Password, email, notification, session, export, and deletion actions all use
 * the authenticated user id; no action accepts a target user from the client.
 */
final class SettingsController
{
    public function show(Request $request): Response
    {
        $user = $this->user();

        return $this->render($user);
    }

    public function updatePassword(Request $request): Response
    {
        $user = $this->user();
        $result = (new SettingsService())->changePassword($user, $request->body());

        if (!$result['ok']) {
            return $this->render(
                $user,
                $result['errors'] ?? [],
                [],
                $result['message'] ?? 'Please check the security fields.',
                (int) ($result['status'] ?? 422),
                'password',
            );
        }

        Flash::success((string) $result['message']);

        return redirect('/settings');
    }

    public function requestEmailChange(Request $request): Response
    {
        $user = $this->user();
        $result = (new SettingsService())->requestEmailChange($user, $request->body());

        if (!$result['ok']) {
            $old = [
                'new_email' => is_string($request->body()['new_email'] ?? null)
                    ? (string) $request->body()['new_email']
                    : '',
            ];

            return $this->render(
                $user,
                $result['errors'] ?? [],
                $old,
                $result['message'] ?? 'Please check the email fields.',
                (int) ($result['status'] ?? 422),
                'email',
            );
        }

        Flash::success((string) $result['message']);

        return redirect('/settings');
    }

    public function showEmailConfirmation(Request $request): Response
    {
        $user = $this->user();
        $token = is_string($request->input('token')) ? (string) $request->input('token') : '';

        if (strlen($token) !== 64 || !ctype_xdigit($token)) {
            Flash::error('This confirmation link is invalid.');

            return redirect('/settings');
        }

        $pending = EmailChangeRequest::findValidByHash(hash('sha256', $token));
        $latest = EmailChangeRequest::latestForUser((int) $user['id']);

        if ($pending === null
            || (int) $pending['user_id'] !== (int) $user['id']
            || $latest === null
            || (int) $latest['id'] !== (int) $pending['id']
        ) {
            Flash::error('This confirmation link is invalid, superseded, or has expired. Request a new one from Settings.');

            return redirect('/settings');
        }

        return view('pages/settings/confirm-email', [
            'title' => 'Confirm email change — Questify',
            'description' => 'Confirm the new email address for your Questify account.',
            'user' => $user,
            'token' => $token,
            'newEmail' => (string) $pending['new_email'],
        ], 'layouts/app');
    }

    public function confirmEmailChange(Request $request): Response
    {
        $user = $this->user();
        $token = is_string($request->body()['token'] ?? null) ? (string) $request->body()['token'] : '';
        $result = (new SettingsService())->confirmEmailChange($user, $token);

        if (!$result['ok']) {
            Flash::error((string) ($result['message'] ?? 'The email change could not be completed.'));

            return redirect('/settings');
        }

        Flash::success((string) $result['message']);

        return redirect('/settings');
    }

    public function updateNotifications(Request $request): Response
    {
        $user = $this->user();
        $result = (new SettingsService())->updateNotifications($user, $request->body());

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            Flash::error($result['message']);
        }

        return redirect('/settings');
    }

    public function revokeSession(Request $request, string $id): Response
    {
        $user = $this->user();
        $result = (new SettingsService())->revokeSession($user, $id);

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            Flash::error($result['message']);
        }

        return redirect('/settings#sessions');
    }

    public function revokeOtherSessions(Request $request): Response
    {
        $user = $this->user();
        $result = (new SettingsService())->revokeOtherSessions($user);
        Flash::success($result['message']);

        return redirect('/settings#sessions');
    }

    public function export(Request $request): Response
    {
        $user = $this->user();
        $data = (new SettingsService())->exportData($user);
        $username = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($user['username'] ?? 'account')) ?: 'account';
        $filename = 'questify-export-' . $username . '-' . gmdate('Ymd-His') . '.json';

        return Response::json($data)
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function destroy(Request $request): Response
    {
        $user = $this->user();
        $result = (new SettingsService())->deleteAccount($user, $request->body());

        if (!$result['ok']) {
            return $this->render(
                $user,
                $result['errors'] ?? [],
                [],
                $result['message'] ?? 'Confirm the deletion before continuing.',
                (int) ($result['status'] ?? 422),
                'delete',
            );
        }

        Auth::forgetRemember();
        Auth::logout();
        Flash::success((string) $result['message']);

        return redirect('/login');
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function render(array $user, array $errors = [], array $old = [], ?string $message = null, int $status = 200, ?string $form = null): Response
    {
        $service = new SettingsService();

        return view('pages/settings/index', [
            'title' => 'Settings — Questify',
            'description' => 'Password, email, sessions, notification preferences, export, and account deletion.',
            'user' => $user,
            'sessions' => $service->sessions($user),
            'pendingEmail' => EmailChangeRequest::latestForUser((int) $user['id']),
            'old' => $old,
            'errors' => $errors,
            'message' => $message,
            'form' => $form,
        ], 'layouts/app')->withStatus($status);
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

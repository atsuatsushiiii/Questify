<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Appeal;
use App\Services\AppealService;

/**
 * Read-only account appeal form.
 *
 * The read-only guard intentionally does not cover these routes: asking for a
 * review is the one write-like action a limited account must retain.
 */
final class AppealController
{
    public function show(Request $request): Response
    {
        $actor = $this->actor();
        $userId = (int) $actor['id'];

        if (!account_restricted($actor)) {
            Flash::info('Your account is not limited to read-only access.');

            return redirect('/dashboard');
        }

        return view('pages/appeals/show', [
            'title' => 'Request a review — Questify',
            'description' => 'Ask a moderator to review the read-only limit on your account.',
            'openAppeal' => Appeal::findOpenForUser($userId),
            'history' => Appeal::latestForUser($userId, 8),
            'minMessage' => AppealService::MIN_MESSAGE,
            'maxMessage' => AppealService::MAX_MESSAGE,
        ], 'layouts/app');
    }

    public function store(Request $request): Response
    {
        $message = $request->input('message', '');
        $result = (new AppealService())->submit(
            $this->actor(),
            is_string($message) ? $message : '',
            $request->ip(),
        );

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        }

        return redirect('/appeal');
    }

    /** @return array<string, mixed> */
    private function actor(): array
    {
        $actor = Auth::user();

        if ($actor === null) {
            throw HttpException::unauthorized();
        }

        return $actor;
    }
}

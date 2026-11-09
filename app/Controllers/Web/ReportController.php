<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Report;
use App\Services\ReportService;

/**
 * Signed-in report submission for public profiles and, when a public quest
 * surface exists, quests.
 */
final class ReportController
{
    public function create(Request $request): Response
    {
        $actor = $this->actor();
        $service = new ReportService();
        $targetType = $this->stringInput($request, 'target_type');
        $targetId = $this->intInput($request, 'target_id');
        $target = $targetId === null ? null : $service->target($targetType, $targetId);

        if ($target === null) {
            throw HttpException::notFound('That report target could not be found.');
        }

        if ($target['owner_id'] === (int) $actor['id']) {
            Flash::error('You cannot report your own profile.');

            return redirect($this->targetPath($target));
        }

        return view('pages/reports/create', [
            'title' => 'Report a ' . $target['type'] . ' — Questify',
            'description' => 'Flag a public profile for administrator review.',
            'target' => $target,
            'existing' => Report::findForReporter((int) $actor['id'], $targetType, (int) $target['id']),
            'reasons' => Report::REASONS,
            'old' => [
                'target_type' => $targetType,
                'target_id' => (string) $target['id'],
                'reason' => '',
                'details' => '',
            ],
            'errors' => [],
        ], 'layouts/app');
    }

    public function store(Request $request): Response
    {
        $actor = $this->actor();
        $service = new ReportService();
        $result = $service->submit($request->body(), $actor);

        if ($result['ok']) {
            Flash::success((string) $result['message']);

            return redirect($this->targetPath($result['target']));
        }

        if (($result['status'] ?? 0) === 404) {
            throw HttpException::notFound($result['message']);
        }

        if (($result['status'] ?? 0) === 409) {
            Flash::error((string) $result['message']);

            return redirect($this->targetPath($result['target']));
        }

        $old = $result['old'] ?? [];
        $targetType = (string) ($old['target_type'] ?? '');
        $targetId = filter_var($old['target_id'] ?? null, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $target = $targetId === false ? null : $service->target($targetType, (int) $targetId);

        if ($target === null) {
            throw HttpException::notFound('That report target could not be found.');
        }

        return view('pages/reports/create', [
            'title' => 'Report a ' . $target['type'] . ' — Questify',
            'description' => 'Flag a public profile for administrator review.',
            'target' => $target,
            'existing' => null,
            'reasons' => Report::REASONS,
            'old' => $old,
            'errors' => $result['errors'] ?? [],
        ], 'layouts/app')->withStatus((int) ($result['status'] ?? 422));
    }

    /**
     * @param array{type: string, handle: string|null} $target
     */
    private function targetPath(array $target): string
    {
        if ($target['type'] === 'profile' && $target['handle'] !== null) {
            return '/u/' . rawurlencode((string) $target['handle']);
        }

        return '/dashboard';
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

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }

    private function intInput(Request $request, string $key): ?int
    {
        $value = filter_var($request->input($key, ''), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        return $value === false ? null : (int) $value;
    }
}

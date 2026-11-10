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

final class AdminAppealController
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $this->stringInput($request, 'search'),
            'status' => $this->stringInput($request, 'status'),
        ];

        if (!in_array($filters['status'], Appeal::STATUSES, true)) {
            $filters['status'] = '';
        }

        $page = filter_var($request->input('page', '1'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $queue = (new AppealService())->queue($filters, $page === false ? 1 : (int) $page);

        return view('pages/admin/appeals/index', [
            'title' => 'Appeals queue — Admin — Questify',
            'description' => 'Review read-only account appeals and restore access with an audited decision.',
            'filters' => $filters,
            'directory' => $queue,
        ], 'layouts/app');
    }

    public function show(Request $request, string $id): Response
    {
        $appealId = $this->idOr404($id);
        $detail = (new AppealService())->detail($appealId);

        if ($detail === null) {
            throw HttpException::notFound('That appeal could not be found.');
        }

        return view('pages/admin/appeals/show', [
            'title' => 'Appeal #' . $appealId . ' — Admin — Questify',
            'description' => 'The account appeal, its moderation context, and the accept or deny decision.',
            'detail' => $detail,
        ], 'layouts/app');
    }

    public function accept(Request $request, string $id): Response
    {
        return $this->decide($request, $id, true);
    }

    public function deny(Request $request, string $id): Response
    {
        return $this->decide($request, $id, false);
    }

    private function decide(Request $request, string $id, bool $accept): Response
    {
        $appealId = $this->idOr404($id);
        $note = $request->input('resolution_note', '');
        $note = is_string($note) ? trim($note) : null;
        $result = (new AppealService())->resolve(
            $this->actor(),
            $appealId,
            $accept,
            $note,
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

        return redirect('/admin/appeals/' . $appealId);
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

    private function idOr404(string $id): int
    {
        $appealId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($appealId === false) {
            throw HttpException::notFound('That appeal could not be found.');
        }

        return (int) $appealId;
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }
}

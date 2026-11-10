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
use App\Services\AdminService;

final class AdminReportController
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $this->stringInput($request, 'search'),
            'status' => $this->stringInput($request, 'status'),
            'target_type' => $this->stringInput($request, 'target_type'),
            'reason' => $this->stringInput($request, 'reason'),
        ];

        if (!in_array($filters['status'], Report::STATUSES, true)) {
            $filters['status'] = '';
        }

        if (!in_array($filters['target_type'], Report::TARGET_TYPES, true)) {
            $filters['target_type'] = '';
        }

        if (!array_key_exists($filters['reason'], Report::REASONS)) {
            $filters['reason'] = '';
        }

        $page = filter_var($request->input('page', '1'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $directory = (new AdminService())->reports($filters, $page === false ? 1 : (int) $page);

        return view('pages/admin/reports/index', [
            'title' => 'Reports queue — Admin — Questify',
            'description' => 'Review user-submitted reports and resolve or dismiss them with an audited decision note.',
            'filters' => $filters,
            'directory' => $directory,
            'reasons' => Report::REASONS,
        ], 'layouts/app');
    }

    public function show(Request $request, string $id): Response
    {
        $reportId = $this->idOr404($id);
        $detail = (new AdminService())->reportDetail($reportId);

        if ($detail === null) {
            throw HttpException::notFound('That report could not be found.');
        }

        return view('pages/admin/reports/show', [
            'title' => 'Report #' . $reportId . ' — Admin — Questify',
            'description' => 'Reported content, reporter context, resolution actions, and moderation history.',
            'detail' => $detail,
            'reasons' => Report::REASONS,
            'suspensionOptions' => AdminService::SUSPENSION_OPTIONS,
            'restrictionOptions' => AdminService::RESTRICTION_OPTIONS,
        ], 'layouts/app');
    }

    public function resolve(Request $request, string $id): Response
    {
        return $this->moderate($request, $id, 'resolved');
    }

    public function dismiss(Request $request, string $id): Response
    {
        return $this->moderate($request, $id, 'dismissed');
    }

    public function warn(Request $request, string $id): Response
    {
        $reportId = $this->idOr404($id);
        $message = $request->input('message', '');
        $message = is_string($message) ? trim($message) : null;
        $result = (new AdminService())->warnReportedUser(
            $this->actor(),
            $reportId,
            $message,
            $request->ip(),
        );

        return $this->accountActionResponse($reportId, $result);
    }

    public function suspend(Request $request, string $id): Response
    {
        $reportId = $this->idOr404($id);
        $duration = $request->input('duration', '');
        $duration = is_string($duration) ? trim($duration) : null;
        $message = $request->input('message', '');
        $message = is_string($message) ? trim($message) : null;
        $result = (new AdminService())->suspendReportedUser(
            $this->actor(),
            $reportId,
            $duration,
            $message,
            $request->ip(),
        );

        return $this->accountActionResponse($reportId, $result);
    }

    public function restrict(Request $request, string $id): Response
    {
        $reportId = $this->idOr404($id);
        $duration = $request->input('duration', '');
        $duration = is_string($duration) ? trim($duration) : null;
        $message = $request->input('message', '');
        $message = is_string($message) ? trim($message) : null;
        $result = (new AdminService())->restrictReportedUser(
            $this->actor(),
            $reportId,
            $message,
            $request->ip(),
            $duration,
        );

        return $this->accountActionResponse($reportId, $result);
    }

    public function unrestrict(Request $request, string $id): Response
    {
        $reportId = $this->idOr404($id);
        $message = $request->input('message', '');
        $message = is_string($message) ? trim($message) : null;
        $result = (new AdminService())->unrestrictReportedUser(
            $this->actor(),
            $reportId,
            $message,
            $request->ip(),
        );

        return $this->accountActionResponse($reportId, $result);
    }

    /**
     * @param array{ok: bool, status?: int, message: string} $result
     */
    private function accountActionResponse(int $reportId, array $result): Response
    {
        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        }

        return redirect('/admin/reports/' . $reportId);
    }

    private function moderate(Request $request, string $id, string $decision): Response
    {
        $reportId = $this->idOr404($id);
        $note = $request->input('resolution_note', '');
        $note = is_string($note) ? trim($note) : null;
        $service = new AdminService();
        $actor = $this->actor();

        $result = $decision === 'resolved'
            ? $service->resolveReport($actor, $reportId, $note, $request->ip())
            : $service->dismissReport($actor, $reportId, $note, $request->ip());

        if ($result['ok']) {
            Flash::success($result['message']);
        } else {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        }

        return redirect('/admin/reports/' . $reportId);
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
        $reportId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($reportId === false) {
            throw HttpException::notFound('That report could not be found.');
        }

        return (int) $reportId;
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }
}

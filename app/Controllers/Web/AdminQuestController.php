<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AdminService;

final class AdminQuestController
{
    public function index(Request $request): Response
    {
        $filters = [
            'search' => $this->stringInput($request, 'search'),
            'status' => $this->stringInput($request, 'status'),
            'difficulty' => $this->stringInput($request, 'difficulty'),
            'category' => $this->stringInput($request, 'category'),
        ];

        $page = filter_var($request->input('page', '1'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);
        $directory = (new AdminService())->quests($filters, $page === false ? 1 : (int) $page);

        return view('pages/admin/quests/index', [
            'title' => 'Quest moderation — Admin — Questify',
            'description' => 'Search every quest, review its owner, and archive or delete abusive content.',
            'filters' => $filters,
            'directory' => $directory,
        ], 'layouts/app');
    }

    public function show(Request $request, string $id): Response
    {
        $questId = $this->idOr404($id);
        $detail = (new AdminService())->questDetail($questId);

        if ($detail === null) {
            throw HttpException::notFound('That quest could not be found.');
        }

        return view('pages/admin/quests/show', [
            'title' => (string) $detail['quest']['title'] . ' — Admin — Questify',
            'description' => 'Quest definition, owner, XP ledger entries, and moderation history.',
            'detail' => $detail,
        ], 'layouts/app');
    }

    public function archive(Request $request, string $id): Response
    {
        $questId = $this->idOr404($id);
        $result = (new AdminService())->archiveQuest($this->actor(), $questId, $request->ip());

        return $this->respond($result, $questId);
    }

    public function restore(Request $request, string $id): Response
    {
        $questId = $this->idOr404($id);
        $result = (new AdminService())->restoreQuest($this->actor(), $questId, $request->ip());

        return $this->respond($result, $questId);
    }

    public function destroy(Request $request, string $id): Response
    {
        $questId = $this->idOr404($id);
        $result = (new AdminService())->deleteQuest($this->actor(), $questId, $request->ip());

        if ($result['ok']) {
            Flash::success($result['message']);

            return redirect('/admin/quests');
        }

        if (($result['status'] ?? 0) === 404) {
            throw HttpException::notFound($result['message']);
        }

        Flash::error($result['message']);

        return redirect('/admin/quests/' . $questId);
    }

    /**
     * @param array{ok: bool, status?: int, message: string} $result
     */
    private function respond(array $result, int $questId): Response
    {
        if ($result['ok']) {
            Flash::success($result['message']);

            return redirect('/admin/quests/' . $questId);
        }

        if (($result['status'] ?? 0) === 404) {
            throw HttpException::notFound($result['message']);
        }

        Flash::error($result['message']);

        return redirect('/admin/quests/' . $questId);
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
        $questId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($questId === false) {
            throw HttpException::notFound('That quest could not be found.');
        }

        return (int) $questId;
    }

    private function stringInput(Request $request, string $key): string
    {
        $value = $request->input($key, '');

        return is_string($value) ? trim($value) : '';
    }
}

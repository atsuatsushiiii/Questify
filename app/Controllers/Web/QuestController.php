<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Category;
use App\Models\Quest;
use App\Models\XpTransaction;
use App\Services\QuestService;

final class QuestController
{
    /** @var list<string> */
    private const STATUSES = ['active', 'completed', 'archived'];

    public function index(Request $request): Response
    {
        $user = $this->user();
        $userId = (int) $user['id'];

        $status = $request->input('status', 'active');
        $status = is_string($status) ? $status : 'active';

        if (!in_array($status, self::STATUSES, true)) {
            $status = 'active';
        }

        $difficulty = $request->input('difficulty', '');
        $difficulty = is_string($difficulty) ? $difficulty : '';

        if (!array_key_exists($difficulty, QuestService::rewards())) {
            $difficulty = '';
        }

        $categories = Category::allActive();
        $category = $request->input('category', '');
        $category = is_string($category) ? $category : '';

        if ($category !== '') {
            $known = array_column($categories, 'slug');

            if (!in_array($category, $known, true)) {
                $category = '';
            }
        }

        $counts = Quest::countsForUser($userId);
        $quests = Quest::listForUser($userId, $status, $difficulty, $category);

        return view('pages/quests/index', [
            'title' => 'My Quests — Questify',
            'description' => 'Your quest log: create objectives, complete them, and earn XP.',
            'user' => $user,
            'quests' => $quests,
            'counts' => $counts,
            'status' => $status,
            'difficulty' => $difficulty,
            'category' => $category,
            'categories' => $categories,
            'rewards' => QuestService::rewards(),
            'questXp' => XpTransaction::totalForUser($userId),
        ], 'layouts/app');
    }

    public function create(Request $request): Response
    {
        $this->user();

        return $this->form(null, [
            'title' => '',
            'description' => '',
            'difficulty' => 'easy',
            'category_id' => '',
            'recurrence' => 'none',
        ], [], null, 200);
    }

    public function store(Request $request): Response
    {
        $user = $this->user();
        $result = (new QuestService())->create($request->body(), (int) $user['id']);

        if (!$result['ok']) {
            return $this->form(null, $result['old'] ?? [
                'title' => '',
                'description' => '',
                'difficulty' => 'easy',
                'category_id' => '',
                'recurrence' => 'none',
            ], $result['errors'] ?? [], $result['message'] ?? null, (int) ($result['status'] ?? 422));
        }

        Flash::success((string) $result['message']);

        return redirect('/quests/' . (int) $result['quest_id']);
    }

    public function show(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);

        return view('pages/quests/show', [
            'title' => (string) $quest['title'] . ' — Questify',
            'description' => 'Quest details, reward, and actions.',
            'user' => $user,
            'quest' => $quest,
            'award' => XpTransaction::forQuest((int) $quest['id']),
        ], 'layouts/app');
    }

    public function edit(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);

        if ((string) $quest['status'] !== 'active') {
            Flash::error('Only active quests can be edited. Restore this quest first.');

            return redirect('/quests/' . (int) $quest['id']);
        }

        return $this->form($quest, [
            'title' => (string) $quest['title'],
            'description' => (string) $quest['description'],
            'difficulty' => (string) $quest['difficulty'],
            'category_id' => $quest['category_id'] !== null ? (string) $quest['category_id'] : '',
            'recurrence' => (string) ($quest['recurrence'] ?? 'none'),
        ], [], null, 200);
    }

    public function update(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);
        $result = (new QuestService())->update((int) $quest['id'], (int) $user['id'], $request->body());

        if (!$result['ok']) {
            $status = (int) ($result['status'] ?? 422);

            if ($status === 404) {
                throw HttpException::notFound('That quest could not be found.');
            }

            if ($status === 409) {
                Flash::error((string) ($result['message'] ?? 'That quest could not be updated.'));

                return redirect('/quests/' . (int) $quest['id']);
            }

            return $this->form($quest, $result['old'] ?? [
                'title' => (string) $quest['title'],
                'description' => (string) $quest['description'],
                'difficulty' => (string) $quest['difficulty'],
                'category_id' => $quest['category_id'] !== null ? (string) $quest['category_id'] : '',
                'recurrence' => (string) ($quest['recurrence'] ?? 'none'),
            ], $result['errors'] ?? [], $result['message'] ?? null, $status);
        }

        Flash::success((string) $result['message']);

        return redirect('/quests/' . (int) $quest['id']);
    }

    public function complete(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);
        $result = (new QuestService())->complete((int) $quest['id'], (int) $user['id']);

        if (!$result['ok']) {
            $status = (int) ($result['status'] ?? 409);

            if ($status === 404) {
                throw HttpException::notFound('That quest could not be found.');
            }

            Flash::info((string) ($result['message'] ?? 'This quest could not be completed.'));

            return redirect('/quests/' . (int) $quest['id']);
        }

        $message = (string) $result['message'];

        if (($result['level_up'] ?? false) === true) {
            $message .= ' Level up — you reached level ' . (int) $result['level']
                . ' and the rank of ' . (string) $result['rank'] . '.';
        }

        foreach ((array) ($result['achievements'] ?? []) as $achievementName) {
            $message .= ' Achievement unlocked — ' . (string) $achievementName . '.';
        }

        if (($result['streak_milestone'] ?? null) !== null) {
            $message .= ' ' . (int) $result['streak_milestone'] . '-day streak!';
        }

        Flash::success($message);

        return redirect('/quests/' . (int) $quest['id']);
    }

    public function archive(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);
        $result = (new QuestService())->archive((int) $quest['id'], (int) $user['id']);

        if (!$result['ok']) {
            if ((int) ($result['status'] ?? 0) === 404) {
                throw HttpException::notFound('That quest could not be found.');
            }

            Flash::error((string) ($result['message'] ?? 'That quest could not be archived.'));

            return redirect('/quests/' . (int) $quest['id']);
        }

        Flash::success((string) $result['message']);

        return redirect('/quests?status=archived');
    }

    public function restore(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);
        $result = (new QuestService())->restore((int) $quest['id'], (int) $user['id']);

        if (!$result['ok']) {
            if ((int) ($result['status'] ?? 0) === 404) {
                throw HttpException::notFound('That quest could not be found.');
            }

            Flash::error((string) ($result['message'] ?? 'That quest could not be restored.'));

            return redirect('/quests?status=archived');
        }

        Flash::success((string) $result['message']);

        return redirect('/quests');
    }

    public function destroy(Request $request, string $id): Response
    {
        $user = $this->user();
        $quest = $this->questOr404($id, (int) $user['id']);
        $result = (new QuestService())->delete((int) $quest['id'], (int) $user['id']);

        if (!$result['ok']) {
            if ((int) ($result['status'] ?? 0) === 404) {
                throw HttpException::notFound('That quest could not be found.');
            }

            Flash::error((string) ($result['message'] ?? 'That quest could not be deleted.'));

            return redirect('/quests?status=archived');
        }

        Flash::success((string) $result['message']);

        return redirect('/quests?status=archived');
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

    /** @return array<string, mixed> */
    private function questOr404(string $id, int $userId): array
    {
        $questId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($questId === false) {
            throw HttpException::notFound('That quest could not be found.');
        }

        $quest = Quest::findForUser((int) $questId, $userId);

        if ($quest === null) {
            throw HttpException::notFound('That quest could not be found.');
        }

        return $quest;
    }

    /**
     * @param array<string, mixed>|null $quest
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?array $quest, array $old, array $errors, ?string $message, int $status): Response
    {
        $mode = $quest === null ? 'create' : 'edit';

        return view('pages/quests/form', [
            'title' => ($mode === 'create' ? 'New quest' : 'Edit quest') . ' — Questify',
            'description' => 'Create a quest, choose its difficulty, and see the XP reward before you commit.',
            'mode' => $mode,
            'quest' => $quest,
            'old' => $old,
            'errors' => $errors,
            'message' => $message,
            'rewards' => QuestService::rewards(),
            'categories' => Category::allActive(),
        ], 'layouts/app')->withStatus($status);
    }
}

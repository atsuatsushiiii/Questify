<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Achievement;
use App\Services\AchievementAdminService;
use App\Services\AchievementService;

final class AdminAchievementController
{
    public function index(Request $request): Response
    {
        return view('pages/admin/achievements/index', [
            'title' => 'Achievements — Admin — Questify',
            'description' => 'Create, edit, deactivate, and delete achievement definitions.',
            'achievements' => Achievement::allForAdmin(),
            'categories' => AchievementService::categories(),
            'criteriaTypes' => AchievementService::criteriaTypes(),
        ], 'layouts/app');
    }

    public function create(Request $request): Response
    {
        return $this->form(null, [
            'achievement_key' => '',
            'name' => '',
            'description' => '',
            'icon' => 'trophy',
            'category' => 'milestone',
            'criteria_type' => 'quests_completed',
            'threshold' => '1',
            'sort_order' => '0',
            'is_active' => '1',
        ], [], null, 200);
    }

    public function store(Request $request): Response
    {
        $result = (new AchievementAdminService())->create($request->body(), $this->actor(), $request->ip());

        if (!$result['ok']) {
            return $this->form(null, $result['old'] ?? [], $result['errors'] ?? [], $result['message'] ?? null, (int) ($result['status'] ?? 422));
        }

        Flash::success((string) $result['message']);

        return redirect('/admin/achievements');
    }

    public function edit(Request $request, string $id): Response
    {
        $achievement = $this->achievementOr404($id);

        return $this->form($achievement, [
            'achievement_key' => (string) $achievement['achievement_key'],
            'name' => (string) $achievement['name'],
            'description' => (string) $achievement['description'],
            'icon' => (string) $achievement['icon'],
            'category' => (string) $achievement['category'],
            'criteria_type' => (string) $achievement['criteria_type'],
            'threshold' => (string) $achievement['threshold'],
            'sort_order' => (string) $achievement['sort_order'],
            'is_active' => (int) $achievement['is_active'] === 1 ? '1' : '',
        ], [], null, 200);
    }

    public function update(Request $request, string $id): Response
    {
        $achievement = $this->achievementOr404($id);
        $result = (new AchievementAdminService())->update(
            (int) $achievement['id'],
            $request->body(),
            $this->actor(),
            $request->ip(),
        );

        if (!$result['ok']) {
            $status = (int) ($result['status'] ?? 422);

            if ($status === 404) {
                throw HttpException::notFound((string) ($result['message'] ?? 'That achievement could not be found.'));
            }

            return $this->form($achievement, $result['old'] ?? [], $result['errors'] ?? [], $result['message'] ?? null, $status);
        }

        Flash::success((string) $result['message']);

        return redirect('/admin/achievements');
    }

    public function destroy(Request $request, string $id): Response
    {
        $achievement = $this->achievementOr404($id);
        $result = (new AchievementAdminService())->delete((int) $achievement['id'], $this->actor(), $request->ip());

        if (!$result['ok']) {
            if (($result['status'] ?? 0) === 404) {
                throw HttpException::notFound($result['message']);
            }

            Flash::error($result['message']);
        } else {
            Flash::success($result['message']);
        }

        return redirect('/admin/achievements');
    }

    /** @return array<string, mixed> */
    private function achievementOr404(string $id): array
    {
        $achievementId = filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        if ($achievementId === false) {
            throw HttpException::notFound('That achievement could not be found.');
        }

        $achievement = Achievement::findById((int) $achievementId);

        if ($achievement === null) {
            throw HttpException::notFound('That achievement could not be found.');
        }

        return $achievement;
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

    /**
     * @param array<string, mixed>|null $achievement
     * @param array<string, string> $old
     * @param array<string, string> $errors
     */
    private function form(?array $achievement, array $old, array $errors, ?string $message, int $status): Response
    {
        return view('pages/admin/achievements/form', [
            'title' => ($achievement === null ? 'New achievement' : 'Edit achievement') . ' — Admin — Questify',
            'description' => 'Define an achievement threshold and how it is evaluated.',
            'achievement' => $achievement,
            'old' => $old,
            'errors' => $errors,
            'message' => $message,
            'categories' => AchievementService::categories(),
            'criteriaTypes' => AchievementService::criteriaTypes(),
        ], 'layouts/app')->withStatus($status);
    }
}

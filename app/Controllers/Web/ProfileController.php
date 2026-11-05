<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Flash;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Models\Quest;
use App\Models\UserAchievement;
use App\Services\AchievementService;
use App\Services\ProfileService;
use App\Services\StreakService;
use App\Support\Progression;

final class ProfileController
{
    public function show(Request $request): Response
    {
        $user = $this->user();

        return $this->render($user, [], $this->oldFromUser($user), null, 200);
    }

    public function update(Request $request): Response
    {
        $user = $this->user();
        $result = (new ProfileService())->update($user, $request->body());

        if (!$result['ok']) {
            return $this->render(
                $user,
                $result['errors'] ?? ['display_name' => 'Please check the profile fields.'],
                $result['old'] ?? $this->oldFromUser($user),
                'Please fix the highlighted fields below.',
                422,
            );
        }

        Flash::success((string) ($result['message'] ?? 'Profile updated.'));

        return redirect('/profile');
    }

    public function updateAvatar(Request $request): Response
    {
        $user = $this->user();
        $files = $request->files();
        $file = isset($files['avatar']) && is_array($files['avatar']) ? $files['avatar'] : [];
        $result = (new ProfileService())->uploadAvatar($user, $file);

        if (!$result['ok']) {
            Flash::error($result['message']);

            return redirect('/profile');
        }

        Flash::success($result['message']);

        return redirect('/profile');
    }

    public function removeAvatar(Request $request): Response
    {
        $user = $this->user();
        $result = (new ProfileService())->removeAvatar($user);

        if (!$result['ok']) {
            Flash::error($result['message']);
        } else {
            Flash::success($result['message']);
        }

        return redirect('/profile');
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, string> $errors
     * @param array<string, string> $old
     */
    private function render(array $user, array $errors, array $old, ?string $message, int $status): Response
    {
        $userId = (int) $user['id'];
        $timezone = (string) ($user['timezone'] ?? 'UTC');
        $progression = Progression::snapshot($user);
        $counts = Quest::countsForUser($userId);
        $overview = AchievementService::overview($userId, $user);
        $current = StreakService::effectiveCurrent($user);

        return view('pages/profile/index', [
            'title' => 'My profile — Questify',
            'description' => 'Your identity, achievements, streak history, and public leaderboard preferences.',
            'user' => $user,
            'progression' => $progression,
            'completedQuests' => $counts['completed'],
            'overview' => $overview,
            'achievementShowcase' => UserAchievement::recentForUser($userId, 4),
            'streak' => [
                'current' => $current,
                'longest' => max($current, (int) ($user['longest_streak'] ?? 0)),
                'heatmap' => StreakService::heatmap($userId, $timezone, 12),
                'days' => StreakService::lastSevenDays($userId, $timezone),
            ],
            'timezones' => timezone_identifiers_list(),
            'old' => $old,
            'errors' => $errors,
            'message' => $message,
        ], 'layouts/app')->withStatus($status);
    }

    /**
     * @param array<string, mixed> $user
     * @return array<string, string>
     */
    private function oldFromUser(array $user): array
    {
        return [
            'display_name' => (string) ($user['display_name'] ?? ''),
            'timezone' => (string) ($user['timezone'] ?? 'UTC'),
            'show_on_leaderboard' => (int) ($user['show_on_leaderboard'] ?? 1) === 1 ? '1' : '0',
            'show_profile' => (int) ($user['show_profile'] ?? 1) === 1 ? '1' : '0',
        ];
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

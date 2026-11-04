<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\AchievementService;

final class AchievementController
{
    public function index(Request $request): Response
    {
        $user = $this->user();
        $categories = AchievementService::categories();
        $requested = $request->input('category', 'all');
        $category = is_string($requested) ? $requested : 'all';

        if ($category !== 'all' && !array_key_exists($category, $categories)) {
            $category = 'all';
        }

        $overview = AchievementService::overview((int) $user['id'], $user);
        $achievements = $category === 'all'
            ? $overview['achievements']
            : array_values(array_filter(
                $overview['achievements'],
                static fn (array $achievement): bool => $achievement['category'] === $category,
            ));

        return view('pages/achievements/index', [
            'title' => 'Achievements — Questify',
            'description' => 'Track every badge you have earned and the milestones still ahead.',
            'user' => $user,
            'overview' => $overview,
            'achievements' => $achievements,
            'category' => $category,
            'categories' => $categories,
        ], 'layouts/app');
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

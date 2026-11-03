<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\Request;
use App\Core\Response;
use App\Services\LeaderboardService;
use App\Support\Progression;

final class LeaderboardController
{
    public function index(Request $request): Response
    {
        $user = $this->user();
        $requested = $request->input('period', 'all');
        $period = LeaderboardService::normalizePeriod(is_string($requested) ? $requested : 'all');
        $requestedPage = $request->input('page', 1);
        $page = filter_var($requestedPage, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $page = $page === false ? 1 : (int) $page;
        $perPage = (int) config('game.leaderboard.per_page', 20);
        $board = LeaderboardService::board($period, $page, $perPage, $user);

        LeaderboardService::detectRankChange($user, $board['viewer_rank'], $board['period']);

        return view('pages/leaderboard/index', [
            'title' => 'Leaderboard — Questify',
            'description' => 'Weekly, monthly, and all-time rankings built from the XP ledger.',
            'user' => $user,
            'progression' => Progression::snapshot($user),
            'board' => $board,
            'periods' => LeaderboardService::periods(),
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

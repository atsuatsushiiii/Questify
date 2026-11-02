<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Auth;
use App\Core\Request;
use App\Core\Response;
use App\Core\HttpException;
use DateTimeImmutable;
use DateTimeZone;
use Throwable;

final class DashboardController
{
    public function index(Request $request): Response
    {
        $user = Auth::user();

        if ($user === null) {
            throw HttpException::unauthorized();
        }

        $level = max(1, (int) $user['level']);
        $xp = max(0, (int) $user['xp_total']);
        $base = (int) config('game.level_curve_base', 100);
        $currentFloor = $base * ($level - 1) ** 2;
        $nextFloor = $base * $level ** 2;
        $needed = max(1, $nextFloor - $currentFloor);
        $progress = min(100, max(0, (($xp - $currentFloor) / $needed) * 100));

        return view('pages/dashboard', [
            'title' => 'Your command center — Questify',
            'description' => 'Your Questify account, level progress, and streak at a glance.',
            'user' => $user,
            'level' => $level,
            'progress' => $progress,
            'xpToNext' => max(0, $nextFloor - $xp),
            'rank' => $this->rank($level),
            'memberSince' => $this->formatDate((string) $user['created_at'], (string) $user['timezone']),
            'lastLogin' => $this->formatDate(
                isset($user['last_login_at']) ? (string) $user['last_login_at'] : null,
                (string) $user['timezone'],
            ),
        ], 'layouts/app');
    }

    private function rank(int $level): string
    {
        $rank = 'Novice';

        foreach ((array) config('game.ranks', []) as $threshold => $name) {
            if ($level >= (int) $threshold) {
                $rank = (string) $name;
            }
        }

        return $rank;
    }

    private function formatDate(?string $value, string $timezone): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            $date = new DateTimeImmutable($value, new DateTimeZone('UTC'));
            $date = $date->setTimezone(new DateTimeZone($timezone));

            return $date->format('j M Y');
        } catch (Throwable) {
            return null;
        }
    }
}

<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Level and rank calculations shared by the interface, the quest XP awarder
 * and the tests.
 *
 * The curve is defined once in config/game.php:
 * total XP required to reach level L = base * (L - 1)^2.
 * Ranks are derived from the level, so a user can never store an arbitrary
 * rank separately from their XP.
 */
final class Progression
{
    /**
     * @param array<string, mixed> $user
     * @return array{
     *     level: int,
     *     xp: int,
     *     rank: string,
     *     next_rank: string,
     *     next_rank_level: int|null,
     *     progress: float,
     *     xp_to_next: int,
     *     next_level: int|null
     * }
     */
    public static function snapshot(array $user): array
    {
        $maxLevel = max(1, (int) config('game.max_level', 100));
        $xp = max(0, (int) ($user['xp_total'] ?? 0));
        $level = self::levelForXp($xp);
        $base = max(1, (int) config('game.level_curve_base', 100));

        $currentFloor = $base * ($level - 1) ** 2;
        $nextFloor = $base * $level ** 2;
        $needed = max(1, $nextFloor - $currentFloor);
        $atMaxLevel = $level >= $maxLevel;

        $progress = $atMaxLevel
            ? 100.0
            : min(100.0, max(0.0, (($xp - $currentFloor) / $needed) * 100));

        return [
            'level' => $level,
            'xp' => $xp,
            'rank' => self::rank($level),
            'next_rank' => self::nextRank($level)['name'],
            'next_rank_level' => self::nextRank($level)['level'],
            'progress' => $progress,
            'xp_to_next' => $atMaxLevel ? 0 : max(0, $nextFloor - $xp),
            'next_level' => $atMaxLevel ? null : $level + 1,
        ];
    }

    /**
     * The highest level whose XP floor has been reached, capped by config.
     */
    public static function levelForXp(int $xp): int
    {
        $maxLevel = max(1, (int) config('game.max_level', 100));
        $base = max(1, (int) config('game.level_curve_base', 100));
        $xp = max(0, $xp);

        if ($xp === 0) {
            return 1;
        }

        $level = (int) floor(sqrt($xp / $base)) + 1;

        return min($maxLevel, max(1, $level));
    }

    /**
     * Total XP required to reach a given level.
     */
    public static function xpForLevel(int $level): int
    {
        $base = max(1, (int) config('game.level_curve_base', 100));
        $level = max(1, $level);

        return $base * ($level - 1) ** 2;
    }

    public static function rank(int $level): string
    {
        $rank = 'Novice';

        foreach ((array) config('game.ranks', []) as $threshold => $name) {
            if ($level >= (int) $threshold) {
                $rank = (string) $name;
            }
        }

        return $rank;
    }

    /**
     * @return array{name: string, level: int|null}
     */
    public static function nextRank(int $level): array
    {
        $next = ['name' => 'Mythic', 'level' => null];

        foreach ((array) config('game.ranks', []) as $threshold => $name) {
            if ((int) $threshold > $level) {
                return ['name' => (string) $name, 'level' => (int) $threshold];
            }
        }

        return $next;
    }
}

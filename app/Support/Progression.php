<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Read-only level and rank calculations for display.
 *
 * Awarding XP, recalculating levels and streak transitions belong to Phase 3.
 * This class only turns the stored values on the users row into the numbers the
 * interface renders, using the same curve defined in config/game.php.
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
        $level = min($maxLevel, max(1, (int) ($user['level'] ?? 1)));
        $xp = max(0, (int) ($user['xp_total'] ?? 0));
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

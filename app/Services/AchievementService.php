<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\Achievement;
use App\Models\UserAchievement;
use App\Support\Progression;
use PDO;

/**
 * Server-side achievement evaluation and progress calculation.
 *
 * A definition is a typed criteria type plus a threshold. Unknown criteria are
 * ignored at runtime so a malformed definition can never block quest
 * completion.
 */
final class AchievementService
{
    /** @var list<'quests_completed'|'xp_total'|'level'|'epic_quests'|'longest_streak'> */
    private const CRITERIA = [
        'quests_completed',
        'xp_total',
        'level',
        'epic_quests',
        'longest_streak',
    ];

    /**
     * @param array{xp_total?: int, level?: int, longest_streak?: int} $context
     * @return array{quests_completed: int, xp_total: int, level: int, epic_quests: int, longest_streak: int}
     */
    public static function metrics(PDO $db, int $userId, array $context = []): array
    {
        $summary = $db->prepare(
            "SELECT COUNT(*) AS quests, COALESCE(SUM(amount), 0) AS xp
             FROM xp_transactions
             WHERE user_id = ? AND source = 'quest_completion'",
        );
        $summary->execute([$userId]);
        $row = $summary->fetch(PDO::FETCH_ASSOC) ?: ['quests' => 0, 'xp' => 0];

        $epic = $db->prepare(
            "SELECT COUNT(*)
             FROM xp_transactions xt
             INNER JOIN quests q ON q.id = xt.quest_id
             WHERE xt.user_id = ? AND xt.source = 'quest_completion' AND q.difficulty = 'epic'",
        );
        $epic->execute([$userId]);

        $xp = array_key_exists('xp_total', $context)
            ? max(0, (int) $context['xp_total'])
            : (int) ($row['xp'] ?? 0);
        $level = array_key_exists('level', $context)
            ? max(1, (int) $context['level'])
            : Progression::levelForXp($xp);
        $longest = array_key_exists('longest_streak', $context)
            ? max(0, (int) $context['longest_streak'])
            : 0;

        return [
            'quests_completed' => (int) ($row['quests'] ?? 0),
            'xp_total' => $xp,
            'level' => $level,
            'epic_quests' => (int) $epic->fetchColumn(),
            'longest_streak' => $longest,
        ];
    }

    /**
     * Evaluate every active definition and return the rows that were newly
     * unlocked by this call.
     *
     * @param array{xp_total?: int, level?: int, longest_streak?: int} $context
     * @return list<array<string, mixed>>
     */
    public static function evaluate(PDO $db, int $userId, array $context = []): array
    {
        $metrics = self::metrics($db, $userId, $context);
        $definitions = Achievement::allActiveWith($db);
        $newlyUnlocked = [];

        foreach ($definitions as $definition) {
            $type = (string) ($definition['criteria_type'] ?? '');

            if (!in_array($type, self::CRITERIA, true)) {
                continue;
            }

            $threshold = max(1, (int) ($definition['threshold'] ?? 1));
            $value = $metrics[$type];

            if ($value < $threshold) {
                continue;
            }

            if (UserAchievement::insertIgnore($db, $userId, (int) $definition['id'])) {
                $definition['unlocked_at'] = gmdate('Y-m-d H:i:s');
                $newlyUnlocked[] = $definition;
            }
        }

        return $newlyUnlocked;
    }

    /**
     * @param array<string, mixed> $user
     * @return array{
     *     metrics: array<string, int>,
     *     achievements: list<array<string, mixed>>,
     *     summary: array{total: int, unlocked: int, locked: int, percent: int}
     * }
     */
    public static function overview(int $userId, array $user): array
    {
        $xp = max(0, (int) ($user['xp_total'] ?? 0));
        $context = [
            'xp_total' => $xp,
            'level' => Progression::levelForXp($xp),
            'longest_streak' => max(0, (int) ($user['longest_streak'] ?? 0)),
        ];
        $metrics = self::metrics(Database::connection(), $userId, $context);
        $rows = Achievement::allWithUnlocks($userId);
        $achievements = [];
        $unlockedCount = 0;

        foreach ($rows as $row) {
            $type = (string) ($row['criteria_type'] ?? '');
            $threshold = max(1, (int) ($row['threshold'] ?? 1));
            $metric = in_array($type, self::CRITERIA, true) ? $metrics[$type] : 0;
            $isUnlocked = ($row['unlocked_at'] ?? null) !== null;
            $current = $isUnlocked ? $threshold : min($metric, $threshold);
            $percent = $isUnlocked
                ? 100
                : (int) min(100, floor(($current / max(1, $threshold)) * 100));

            if ($isUnlocked) {
                $unlockedCount++;
            }

            $achievements[] = [
                'id' => (int) $row['id'],
                'achievement_key' => (string) $row['achievement_key'],
                'name' => (string) $row['name'],
                'description' => (string) $row['description'],
                'icon' => (string) $row['icon'],
                'category' => (string) $row['category'],
                'criteria_type' => $type,
                'threshold' => $threshold,
                'current' => $current,
                'remaining' => max(0, $threshold - $current),
                'percent' => $percent,
                'unlocked' => $isUnlocked,
                'unlocked_at' => $isUnlocked ? (string) $row['unlocked_at'] : null,
            ];
        }

        $total = count($achievements);
        $summary = [
            'total' => $total,
            'unlocked' => $unlockedCount,
            'locked' => $total - $unlockedCount,
            'percent' => $total > 0 ? (int) round(($unlockedCount / $total) * 100) : 0,
        ];

        return [
            'metrics' => $metrics,
            'achievements' => $achievements,
            'summary' => $summary,
        ];
    }

    /**
     * Unlocked-first preview for the dashboard.
     *
     * @param array{achievements: list<array<string, mixed>>} $overview
     * @return list<array<string, mixed>>
     */
    public static function preview(array $overview, int $limit = 4): array
    {
        $items = $overview['achievements'];

        usort($items, static function (array $a, array $b): int {
            if ($a['unlocked'] !== $b['unlocked']) {
                return $a['unlocked'] ? -1 : 1;
            }

            if ($a['unlocked'] && $b['unlocked']) {
                return strcmp((string) $b['unlocked_at'], (string) $a['unlocked_at']);
            }

            return $b['percent'] <=> $a['percent'];
        });

        return array_slice($items, 0, max(1, $limit));
    }

    /**
     * Criteria types the runtime evaluator understands. Admin forms use this
     * list so a definition can never be created that the engine ignores.
     *
     * @return list<string>
     */
    public static function criteriaTypes(): array
    {
        return self::CRITERIA;
    }

    /** @return array<string, string> */
    public static function categories(): array
    {
        return [
            'milestone' => 'Milestones',
            'xp' => 'XP',
            'level' => 'Levels',
            'difficulty' => 'Difficulty',
            'streak' => 'Streaks',
        ];
    }
}

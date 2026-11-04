<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Validator;
use App\Models\Category;
use App\Models\Quest;
use App\Models\User;
use App\Models\XpTransaction;
use App\Support\Progression;
use PDOException;
use RuntimeException;
use Throwable;

/**
 * Quest rules: validation, server-derived rewards, and the single-use XP
 * award transaction.
 */
final class QuestService
{
    /**
     * Difficulty => XP map. config/game.php is the only source of reward
     * values; client input is never trusted.
     *
     * @return array<string, int>
     */
    public static function rewards(): array
    {
        $configured = (array) config('game.xp_per_difficulty', []);
        $rewards = [];

        foreach ($configured as $difficulty => $reward) {
            $rewards[(string) $difficulty] = max(0, (int) $reward);
        }

        return $rewards;
    }

    public static function rewardFor(string $difficulty): ?int
    {
        return self::rewards()[$difficulty] ?? null;
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, quest_id?: int, xp_reward?: int}
     */
    public function create(array $input, int $userId): array
    {
        $validated = $this->validate($input);

        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];
        $reward = self::rewardFor($data['difficulty']);

        if ($reward === null) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['difficulty' => 'Choose a valid quest difficulty.'],
                'old' => $validated['old'],
            ];
        }

        $questId = Quest::create([
            'user_id' => $userId,
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'difficulty' => $data['difficulty'],
            'xp_reward' => $reward,
        ]);

        return [
            'ok' => true,
            'status' => 201,
            'quest_id' => $questId,
            'xp_reward' => $reward,
            'message' => 'Quest added to your log. Complete it to earn ' . $reward . ' XP.',
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, xp_reward?: int}
     */
    public function update(int $questId, int $userId, array $input): array
    {
        $validated = $this->validate($input);

        if (!$validated['ok']) {
            return $validated;
        }

        $quest = Quest::findForUser($questId, $userId);

        if ($quest === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
        }

        if ((string) $quest['status'] !== 'active') {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'Only active quests can be edited. Restore this quest first.',
            ];
        }

        $data = $validated['data'];
        $reward = self::rewardFor($data['difficulty']);

        if ($reward === null) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => ['difficulty' => 'Choose a valid quest difficulty.'],
                'old' => $validated['old'],
            ];
        }

        $updated = Quest::updateActive($questId, $userId, [
            'category_id' => $data['category_id'],
            'title' => $data['title'],
            'description' => $data['description'],
            'difficulty' => $data['difficulty'],
            'xp_reward' => $reward,
        ]);

        if (!$updated) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'This quest changed while you were editing it. Reload and try again.',
            ];
        }

        return [
            'ok' => true,
            'xp_reward' => $reward,
            'message' => 'Quest updated. It is now worth ' . $reward . ' XP.',
        ];
    }

    /**
     * Award XP exactly once. The quest row and the user row are both locked,
     * the status transition and the ledger unique key make duplicate awards
     * impossible, and the whole operation is atomic.
     *
     * @return array{ok: bool, status?: int, message?: string, reward?: int, xp?: int, level?: int, old_level?: int, level_up?: bool, rank?: string, quest_id?: int, quest_title?: string, streak?: int, streak_milestone?: int|null, achievements?: list<string>}
     */
    public function complete(int $questId, int $userId): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            $quest = Quest::findForUserForUpdate($pdo, $questId, $userId);

            if ($quest === null) {
                $pdo->rollBack();

                return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
            }

            if ((string) $quest['status'] === 'completed') {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'This quest is already complete. XP is only awarded once.',
                ];
            }

            if ((string) $quest['status'] !== 'active') {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'Archived quests cannot be completed. Restore it first.',
                ];
            }

            $user = User::findForUpdate($pdo, $userId);

            if ($user === null) {
                $pdo->rollBack();

                return ['ok' => false, 'status' => 404, 'message' => 'Your account could not be loaded.'];
            }

            $reward = (int) $quest['xp_reward'];

            if ($reward < 1) {
                $reward = self::rewardFor((string) $quest['difficulty']) ?? 0;
            }

            if ($reward < 1) {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'status' => 500,
                    'message' => 'This quest has no valid XP reward. Contact support.',
                ];
            }

            if (!Quest::complete($pdo, $questId, $userId)) {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'This quest was already completed. XP is only awarded once.',
                ];
            }

            $oldXp = max(0, (int) $user['xp_total']);
            $oldLevel = Progression::levelForXp($oldXp);
            $newXp = $oldXp + $reward;
            $newLevel = Progression::levelForXp($newXp);

            try {
                XpTransaction::create($pdo, [
                    'user_id' => $userId,
                    'quest_id' => $questId,
                    'quest_title' => (string) $quest['title'],
                    'amount' => $reward,
                    'level_before' => $oldLevel,
                    'level_after' => $newLevel,
                ]);
            } catch (PDOException $exception) {
                if (($exception->errorInfo[1] ?? null) === 1062) {
                    $pdo->rollBack();

                    return [
                        'ok' => false,
                        'status' => 409,
                        'message' => 'This quest has already paid out its XP.',
                    ];
                }

                throw $exception;
            }

            User::updateProgression($pdo, $userId, $newXp, $newLevel);

            // Phase 4 side effects share this transaction: a failure anywhere
            // rolls the XP award back instead of leaving partial progress.
            $streak = StreakService::recordCompletion($pdo, $user, $questId);
            $newlyUnlocked = AchievementService::evaluate($pdo, $userId, [
                'xp_total' => $newXp,
                'level' => $newLevel,
                'longest_streak' => $streak['longest'],
            ]);

            foreach ($newlyUnlocked as $achievement) {
                NotificationService::achievementUnlocked($pdo, $userId, $achievement);
            }

            if ($newLevel > $oldLevel) {
                NotificationService::levelUp($pdo, $userId, $newLevel, Progression::rank($newLevel));
            }

            if ($streak['milestone'] !== null) {
                NotificationService::streakMilestone(
                    $pdo,
                    $userId,
                    (int) $streak['milestone'],
                    (string) $streak['date'],
                );
            }

            $pdo->commit();

            return [
                'ok' => true,
                'quest_id' => $questId,
                'quest_title' => (string) $quest['title'],
                'reward' => $reward,
                'xp' => $newXp,
                'level' => $newLevel,
                'old_level' => $oldLevel,
                'level_up' => $newLevel > $oldLevel,
                'rank' => Progression::rank($newLevel),
                'streak' => $streak['current'],
                'streak_milestone' => $streak['milestone'],
                'achievements' => array_map(
                    static fn (array $achievement): string => (string) $achievement['name'],
                    $newlyUnlocked,
                ),
                'message' => 'Quest complete. +' . $reward . ' XP awarded.',
            ];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array{ok: bool, status?: int, message?: string}
     */
    public function archive(int $questId, int $userId): array
    {
        $quest = Quest::findForUser($questId, $userId);

        if ($quest === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
        }

        if ((string) $quest['status'] === 'archived') {
            return ['ok' => true, 'message' => 'This quest is already archived.'];
        }

        Quest::setStatus($questId, $userId, 'archived');

        $message = (string) $quest['status'] === 'completed'
            ? 'Quest archived. Its XP remains on your account.'
            : 'Quest archived. You can restore it from the Archived tab.';

        return ['ok' => true, 'message' => $message];
    }

    /**
     * @return array{ok: bool, status?: int, message?: string}
     */
    public function restore(int $questId, int $userId): array
    {
        $quest = Quest::findForUser($questId, $userId);

        if ($quest === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
        }

        if ((string) $quest['status'] !== 'archived') {
            return ['ok' => false, 'status' => 409, 'message' => 'Only archived quests can be restored.'];
        }

        $target = $quest['completed_at'] !== null ? 'completed' : 'active';

        if (!Quest::setStatus($questId, $userId, $target)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That quest could not be restored.'];
        }

        return [
            'ok' => true,
            'message' => $target === 'completed'
                ? 'Quest returned to your completed log.'
                : 'Quest restored to your active log.',
        ];
    }

    /**
     * A completed quest is never destructively deleted because its XP ledger
     * entry is part of the account history. Archive it instead.
     *
     * @return array{ok: bool, status?: int, message?: string}
     */
    public function delete(int $questId, int $userId): array
    {
        $pdo = Database::connection();
        $pdo->beginTransaction();

        try {
            // Lock the row so a concurrent completion cannot slip between the
            // "never paid XP" check and the delete.
            $quest = Quest::findForUserForUpdate($pdo, $questId, $userId);

            if ($quest === null) {
                $pdo->rollBack();

                return ['ok' => false, 'status' => 404, 'message' => 'That quest could not be found.'];
            }

            if ($quest['completed_at'] !== null || XpTransaction::existsForQuest($questId)) {
                $pdo->rollBack();

                return [
                    'ok' => false,
                    'status' => 409,
                    'message' => 'Completed quests keep their XP history and can only be archived.',
                ];
            }

            if (!Quest::delete($questId, $userId)) {
                $pdo->rollBack();

                return ['ok' => false, 'status' => 409, 'message' => 'That quest could not be deleted.'];
            }

            $pdo->commit();

            return ['ok' => true, 'message' => 'Quest deleted permanently.'];
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $exception;
        }
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: true, data: array{title: string, description: string, difficulty: string, category_id: int|null}, old: array<string, string>}|array{ok: false, status: int, errors: array<string, string>, old: array<string, string>}
     */
    private function validate(array $input): array
    {
        $rewards = self::rewards();
        $difficulties = array_keys($rewards);

        $old = [
            'title' => $this->scalar($input['title'] ?? null),
            'description' => $this->scalar($input['description'] ?? null),
            'difficulty' => $this->scalar($input['difficulty'] ?? null),
            'category_id' => $this->scalar($input['category_id'] ?? null),
        ];

        $validator = Validator::make(
            $old,
            [
                'title' => 'required|string|min:3|max:120',
                'description' => 'required|string|min:10|max:2000',
                'difficulty' => 'required|string|in:' . implode(',', $difficulties),
            ],
            [
                'title' => 'Quest title',
                'description' => 'Description',
                'difficulty' => 'Difficulty',
            ],
        );

        $valid = $validator->validate();
        $errors = $validator->errors();
        $categoryId = null;

        if ($old['category_id'] !== '') {
            if (preg_match('/^\d+$/', $old['category_id']) !== 1) {
                $errors['category_id'] = 'Choose a valid category.';
            } else {
                $category = Category::find((int) $old['category_id']);

                if ($category === null || (int) $category['is_active'] !== 1) {
                    $errors['category_id'] = 'Choose an active category.';
                } else {
                    $categoryId = (int) $category['id'];
                }
            }
        }

        if (!$valid || $errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $errors,
                'old' => $old,
            ];
        }

        $validated = $validator->validated();

        if (self::rewardFor((string) $validated['difficulty']) === null) {
            throw new RuntimeException('Quest reward configuration is invalid.');
        }

        return [
            'ok' => true,
            'data' => [
                'title' => trim((string) $validated['title']),
                'description' => trim((string) $validated['description']),
                'difficulty' => (string) $validated['difficulty'],
                'category_id' => $categoryId,
            ],
            'old' => $old,
        ];
    }
}

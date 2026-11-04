<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Models\Achievement;
use App\Models\AuditLog;
use PDOException;

/**
 * Administrator CRUD for achievement definitions.
 *
 * The runtime evaluator stays the source of truth for which criteria types are
 * supported; this service only accepts definitions it can actually evaluate.
 */
final class AchievementAdminService
{
    private const ICON_PATTERN = '/^[a-z0-9-]{1,40}$/';
    private const KEY_PATTERN = '/^[a-z0-9_]{3,64}$/';

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, id?: int}
     */
    public function create(array $input, array $actor, string $ipAddress): array
    {
        $validated = $this->validate($input);

        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];
        $id = Achievement::create($data);

        $this->audit($actor, 'achievement.created', $id, 'Created achievement ' . $data['name'], [
            'achievement_key' => $data['achievement_key'],
            'criteria_type' => $data['criteria_type'],
            'threshold' => $data['threshold'],
        ], $ipAddress);

        return ['ok' => true, 'status' => 201, 'id' => $id, 'message' => 'Achievement created.'];
    }

    /**
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, id?: int}
     */
    public function update(int $id, array $input, array $actor, string $ipAddress): array
    {
        $achievement = Achievement::findById($id);

        if ($achievement === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That achievement could not be found.'];
        }

        $validated = $this->validate($input, $achievement);

        if (!$validated['ok']) {
            return $validated;
        }

        $data = $validated['data'];

        if (!Achievement::update($id, $data)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That achievement could not be updated.'];
        }

        $this->audit($actor, 'achievement.updated', $id, 'Updated achievement ' . $data['name'], [
            'achievement_key' => $data['achievement_key'],
            'criteria_type' => $data['criteria_type'],
            'threshold' => $data['threshold'],
            'is_active' => $data['is_active'],
        ], $ipAddress);

        return ['ok' => true, 'id' => $id, 'message' => 'Achievement updated.'];
    }

    /**
     * Definitions that users have already unlocked are retained so their
     * trophy history survives. Deactivate them instead.
     *
     * @return array{ok: bool, status?: int, message: string}
     */
    public function delete(int $id, array $actor, string $ipAddress): array
    {
        $achievement = Achievement::findById($id);

        if ($achievement === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That achievement could not be found.'];
        }

        $unlocks = Achievement::unlockedCount($id);

        if ($unlocks > 0) {
            return [
                'ok' => false,
                'status' => 409,
                'message' => 'This achievement has been unlocked by ' . $unlocks
                    . ' adventurer' . ($unlocks === 1 ? '' : 's') . '. Deactivate it instead of deleting it.',
            ];
        }

        if (!Achievement::delete($id)) {
            return ['ok' => false, 'status' => 409, 'message' => 'That achievement could not be deleted.'];
        }

        $this->audit($actor, 'achievement.deleted', $id, 'Deleted achievement ' . (string) $achievement['name'], [
            'achievement_key' => (string) $achievement['achievement_key'],
        ], $ipAddress);

        return ['ok' => true, 'message' => 'Achievement deleted.'];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed>|null $existing
     * @return array{ok: true, data: array{achievement_key: string, name: string, description: string, icon: string, category: string, criteria_type: string, threshold: int, sort_order: int, is_active: bool}, old: array<string, string>}|array{ok: false, status: int, errors: array<string, string>, old: array<string, string>}
     */
    private function validate(array $input, ?array $existing = null): array
    {
        $key = strtolower($this->scalar($input['achievement_key'] ?? null));
        $name = $this->scalar($input['name'] ?? null);
        $description = $this->scalar($input['description'] ?? null);
        $icon = strtolower($this->scalar($input['icon'] ?? null));
        $category = strtolower($this->scalar($input['category'] ?? null));
        $criteria = strtolower($this->scalar($input['criteria_type'] ?? null));
        $threshold = $this->scalar($input['threshold'] ?? null);
        $sortOrder = $this->scalar($input['sort_order'] ?? null);
        $isActive = in_array(
            strtolower($this->scalar($input['is_active'] ?? '')),
            ['1', 'on', 'yes', 'true'],
            true,
        );

        $old = [
            'achievement_key' => $key,
            'name' => $name,
            'description' => $description,
            'icon' => $icon,
            'category' => $category,
            'criteria_type' => $criteria,
            'threshold' => $threshold,
            'sort_order' => $sortOrder,
            'is_active' => $isActive ? '1' : '',
        ];

        $errors = [];

        if (preg_match(self::KEY_PATTERN, $key) !== 1) {
            $errors['achievement_key'] = 'Key must be 3–64 lowercase letters, numbers, or underscores.';
        } elseif (Achievement::keyExists($key, $existing !== null ? (int) $existing['id'] : null)) {
            $errors['achievement_key'] = 'That achievement key is already in use.';
        }

        if (mb_strlen($name) < 3 || mb_strlen($name) > 80) {
            $errors['name'] = 'Achievement name must be 3–80 characters.';
        }

        if (mb_strlen($description) < 10 || mb_strlen($description) > 255) {
            $errors['description'] = 'Description must be 10–255 characters.';
        }

        if (preg_match(self::ICON_PATTERN, $icon) !== 1) {
            $errors['icon'] = 'Use a Lucide icon name such as trophy or star.';
        }

        if (!array_key_exists($category, AchievementService::categories())) {
            $errors['category'] = 'Choose a supported achievement category.';
        }

        if (!in_array($criteria, AchievementService::criteriaTypes(), true)) {
            $errors['criteria_type'] = 'Choose a criteria type the evaluator supports.';
        }

        $thresholdValue = filter_var($threshold, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 4294967295],
        ]);

        if ($thresholdValue === false) {
            $errors['threshold'] = 'Threshold must be a whole number of at least 1.';
        }

        $sort = filter_var($sortOrder, FILTER_VALIDATE_INT, ['options' => ['min_range' => 0, 'max_range' => 65535]]);

        if ($sort === false) {
            $errors['sort_order'] = 'Sort order must be a whole number between 0 and 65535.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'status' => 422, 'errors' => $errors, 'old' => $old];
        }

        return [
            'ok' => true,
            'data' => [
                'achievement_key' => $key,
                'name' => $name,
                'description' => $description,
                'icon' => $icon,
                'category' => $category,
                'criteria_type' => $criteria,
                'threshold' => (int) $thresholdValue,
                'sort_order' => (int) $sort,
                'is_active' => $isActive,
            ],
            'old' => $old,
        ];
    }

    /**
     * @param array<string, mixed> $actor
     * @param array<string, mixed> $metadata
     */
    private function audit(
        array $actor,
        string $action,
        int $targetId,
        string $summary,
        array $metadata,
        string $ipAddress,
    ): void {
        try {
            AuditLog::record([
                'actor_user_id' => (int) $actor['id'],
                'action' => $action,
                'target_type' => 'achievement',
                'target_id' => $targetId,
                'summary' => mb_substr($summary, 0, 255),
                'metadata' => $metadata,
                'ip_address' => $ipAddress,
            ]);
        } catch (PDOException) {
            // The definition change already committed.
        }
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}

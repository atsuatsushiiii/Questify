<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Models\Quest;
use App\Models\Report;
use App\Models\User;
use PDOException;

/**
 * Submission rules for user reports.
 *
 * A report can only point at a currently reachable target: an opted-in active
 * profile, or a quest that still exists. Self-reports and duplicate reports are
 * rejected before the unique index has a chance to raise a database error.
 */
final class ReportService
{
    /**
     * Resolve a report target to a stable snapshot.
     *
     * @return array{type: string, id: int, label: string, owner_id: int, handle: string|null, record: array<string, mixed>}|null
     */
    public function target(string $type, int $id): ?array
    {
        if (!in_array($type, Report::TARGET_TYPES, true) || $id < 1) {
            return null;
        }

        if ($type === 'profile') {
            $user = User::findPublicById($id);

            if ($user === null) {
                return null;
            }

            $displayName = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
            $username = (string) ($user['username'] ?? '');

            return [
                'type' => 'profile',
                'id' => (int) $user['id'],
                'label' => $displayName . ($username !== '' ? ' (@' . $username . ')' : ''),
                'owner_id' => (int) $user['id'],
                'handle' => $username !== '' ? $username : null,
                'record' => $user,
            ];
        }

        $quest = Quest::findForAdmin($id);

        if ($quest === null) {
            return null;
        }

        return [
            'type' => 'quest',
            'id' => (int) $quest['id'],
            'label' => (string) $quest['title'],
            'owner_id' => (int) $quest['user_id'],
            'handle' => null,
            'record' => $quest,
        ];
    }

    /**
     * @param array<string, mixed> $input
     * @param array<string, mixed> $reporter
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string, id?: int, target?: array<string, mixed>}
     */
    public function submit(array $input, array $reporter): array
    {
        $targetType = $this->scalar($input['target_type'] ?? '');
        $targetIdInput = $this->scalar($input['target_id'] ?? '');
        $reason = $this->scalar($input['reason'] ?? '');
        $details = $this->scalar($input['details'] ?? '');

        $old = [
            'target_type' => $targetType,
            'target_id' => $targetIdInput,
            'reason' => $reason,
            'details' => $details,
        ];

        $errors = [];
        $targetId = filter_var($targetIdInput, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1],
        ]);

        if (!in_array($targetType, Report::TARGET_TYPES, true)) {
            $errors['target_type'] = 'Choose a valid report target.';
        }

        if ($targetId === false) {
            $errors['target_id'] = 'That report target could not be found.';
        }

        if (!array_key_exists($reason, Report::REASONS)) {
            $errors['reason'] = 'Choose a reason for the report.';
        }

        if (mb_strlen($details) > 1000) {
            $errors['details'] = 'Details must be 1000 characters or fewer.';
        } elseif ($reason === 'other' && mb_strlen($details) < 10) {
            $errors['details'] = 'Tell us what happened in at least 10 characters.';
        }

        if ($errors !== []) {
            return [
                'ok' => false,
                'status' => 422,
                'errors' => $errors,
                'old' => $old,
                'message' => 'Please fix the highlighted fields below.',
            ];
        }

        $target = $this->target($targetType, (int) $targetId);

        if ($target === null) {
            return ['ok' => false, 'status' => 404, 'message' => 'That report target could not be found.'];
        }

        if ($target['owner_id'] === (int) $reporter['id']) {
            return ['ok' => false, 'status' => 409, 'message' => 'You cannot report your own content.', 'target' => $target];
        }

        if (Report::findForReporter((int) $reporter['id'], $targetType, (int) $targetId) !== null) {
            return ['ok' => false, 'status' => 409, 'message' => 'You have already reported this target.', 'target' => $target];
        }

        try {
            $id = Report::create([
                'reporter_user_id' => (int) $reporter['id'],
                'target_type' => $targetType,
                'target_id' => (int) $targetId,
                'target_label' => mb_substr((string) $target['label'], 0, 190),
                'reason' => $reason,
                'details' => $details !== '' ? $details : null,
            ]);
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                return ['ok' => false, 'status' => 409, 'message' => 'You have already reported this target.', 'target' => $target];
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'status' => 201,
            'id' => $id,
            'target' => $target,
            'message' => 'Report submitted. An administrator will review it.',
        ];
    }

    private function scalar(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }
}

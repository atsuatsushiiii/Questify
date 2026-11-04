<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Models\User;
use Throwable;

/**
 * Profile edits and avatar storage.
 *
 * Avatars are validated by detected content, renamed server-side, written only
 * inside public/uploads/avatars, and never re-encoded because GD/Imagick are
 * not available on this host.
 */
final class ProfileService
{
    private const ALLOWED_MIME = [
        'image/jpeg' => ['extension' => 'jpg', 'type' => IMAGETYPE_JPEG],
        'image/png' => ['extension' => 'png', 'type' => IMAGETYPE_PNG],
        'image/webp' => ['extension' => 'webp', 'type' => IMAGETYPE_WEBP],
    ];

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $input
     * @return array{ok: bool, status?: int, errors?: array<string, string>, old?: array<string, string>, message?: string}
     */
    public function update(array $user, array $input): array
    {
        $userId = (int) $user['id'];
        $rawName = is_string($input['display_name'] ?? null) ? (string) $input['display_name'] : '';
        $rawTimezone = is_string($input['timezone'] ?? null) ? (string) $input['timezone'] : '';
        $displayName = preg_replace('/\s+/u', ' ', trim($rawName)) ?? '';
        $timezone = trim($rawTimezone);
        $visible = in_array(
            strtolower(is_string($input['show_on_leaderboard'] ?? null) ? (string) $input['show_on_leaderboard'] : ''),
            ['1', 'on', 'yes', 'true'],
            true,
        );

        $old = [
            'display_name' => $rawName,
            'timezone' => $rawTimezone,
            'show_on_leaderboard' => $visible ? '1' : '0',
        ];
        $errors = [];

        if (mb_strlen($displayName) < 2) {
            $errors['display_name'] = 'Display name must be at least 2 characters.';
        } elseif (mb_strlen($displayName) > 50) {
            $errors['display_name'] = 'Display name must be 50 characters or fewer.';
        } elseif (preg_match('/[\x00-\x1F\x7F]/u', $displayName) === 1) {
            $errors['display_name'] = 'Display name contains invalid control characters.';
        } elseif (
            preg_match('/^[\p{L}\p{N}](?:[\p{L}\p{N} ._\'’\-]*[\p{L}\p{N}])?$/u', $displayName) !== 1
        ) {
            $errors['display_name'] = 'Use letters, numbers, spaces, apostrophes, hyphens, periods, or underscores.';
        }

        if (!in_array($timezone, timezone_identifiers_list(), true)) {
            $errors['timezone'] = 'Choose a recognised timezone.';
        }

        if ($errors !== []) {
            return ['ok' => false, 'status' => 422, 'errors' => $errors, 'old' => $old];
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            User::updateProfile($userId, $displayName, $timezone, $visible);
            NotificationService::profileUpdated($db, $userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        return [
            'ok' => true,
            'message' => 'Profile updated. Your public identity and streak timezone are now current.',
            'old' => [
                'display_name' => $displayName,
                'timezone' => $timezone,
                'show_on_leaderboard' => $visible ? '1' : '0',
            ],
        ];
    }

    /**
     * @param array<string, mixed> $user
     * @param array<string, mixed> $file
     * @return array{ok: bool, message: string, errors?: array<string, string>}
     */
    public function uploadAvatar(array $user, array $file): array
    {
        $userId = (int) $user['id'];

        if (!isset($file['error'], $file['tmp_name'], $file['size'])) {
            return ['ok' => false, 'message' => 'Choose an image to upload.'];
        }

        $error = (int) $file['error'];

        if ($error === UPLOAD_ERR_NO_FILE) {
            return ['ok' => false, 'message' => 'Choose an image to upload.'];
        }

        if ($error !== UPLOAD_ERR_OK) {
            return ['ok' => false, 'message' => $this->uploadErrorMessage($error)];
        }

        $tmp = (string) $file['tmp_name'];

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            return ['ok' => false, 'message' => 'The upload could not be verified.'];
        }

        $maxBytes = max(1024, (int) config('game.avatar.max_bytes', 2 * 1024 * 1024));

        if ((int) $file['size'] < 1 || (int) $file['size'] > $maxBytes) {
            return [
                'ok' => false,
                'message' => 'Images must be smaller than ' . $this->formatBytes($maxBytes) . '.',
            ];
        }

        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp);

        if (!is_string($mime) || !isset(self::ALLOWED_MIME[$mime])) {
            return ['ok' => false, 'message' => 'Upload a JPEG, PNG, or WebP image.'];
        }

        $info = @getimagesize($tmp);

        if ($info === false) {
            return ['ok' => false, 'message' => 'That file is not a readable image.'];
        }

        $allowed = self::ALLOWED_MIME[$mime];

        if ((int) ($info[2] ?? 0) !== $allowed['type']) {
            return ['ok' => false, 'message' => 'The image content does not match its format.'];
        }

        $width = (int) ($info[0] ?? 0);
        $height = (int) ($info[1] ?? 0);
        $minDimension = max(1, (int) config('game.avatar.min_dimension', 64));
        $maxDimension = max($minDimension, (int) config('game.avatar.max_dimension', 4096));

        if ($width < $minDimension || $height < $minDimension) {
            return [
                'ok' => false,
                'message' => 'Images must be at least ' . $minDimension . '×' . $minDimension . ' pixels.',
            ];
        }

        if ($width > $maxDimension || $height > $maxDimension) {
            return [
                'ok' => false,
                'message' => 'Images must be no larger than ' . $maxDimension . '×' . $maxDimension . ' pixels.',
            ];
        }

        $directory = rtrim((string) config('paths.public', ''), '/\\') . '/uploads/avatars';

        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            return ['ok' => false, 'message' => 'The avatar directory is unavailable.'];
        }

        if (!is_writable($directory)) {
            return ['ok' => false, 'message' => 'The avatar directory is not writable.'];
        }

        $directoryReal = realpath($directory);

        if ($directoryReal === false) {
            return ['ok' => false, 'message' => 'The avatar destination could not be verified.'];
        }

        $filename = 'user-' . $userId . '-' . bin2hex(random_bytes(8)) . '.' . $allowed['extension'];
        $target = $directoryReal . DIRECTORY_SEPARATOR . $filename;

        if (!str_starts_with($target, $directoryReal . DIRECTORY_SEPARATOR)) {
            return ['ok' => false, 'message' => 'The avatar destination could not be verified.'];
        }

        if (!move_uploaded_file($tmp, $target)) {
            return ['ok' => false, 'message' => 'The image could not be saved.'];
        }

        $storedPath = 'uploads/avatars/' . $filename;
        $db = Database::connection();
        $db->beginTransaction();

        try {
            User::updateAvatar($userId, $storedPath);
            NotificationService::profileUpdated($db, $userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            @unlink($target);

            throw $exception;
        }

        $this->deleteStoredAvatar(
            isset($user['avatar_path']) ? (string) $user['avatar_path'] : null,
            $userId,
        );

        return ['ok' => true, 'message' => 'Profile picture updated.'];
    }

    /**
     * @param array<string, mixed> $user
     * @return array{ok: bool, message: string}
     */
    public function removeAvatar(array $user): array
    {
        $userId = (int) $user['id'];
        $path = isset($user['avatar_path']) ? (string) $user['avatar_path'] : null;

        if ($path === null || $path === '') {
            return ['ok' => true, 'message' => 'There is no profile picture to remove.'];
        }

        $db = Database::connection();
        $db->beginTransaction();

        try {
            User::updateAvatar($userId, null);
            NotificationService::profileUpdated($db, $userId);
            $db->commit();
        } catch (Throwable $exception) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }

            throw $exception;
        }

        $this->deleteStoredAvatar($path, $userId);

        return ['ok' => true, 'message' => 'Profile picture removed.'];
    }

    /**
     * Remove the avatar file when the account itself is being deleted.
     *
     * @param array<string, mixed> $user
     */
    public function deleteAvatarFile(array $user): void
    {
        $this->deleteStoredAvatar(
            isset($user['avatar_path']) ? (string) $user['avatar_path'] : null,
            (int) $user['id'],
        );
    }

    private function deleteStoredAvatar(?string $path, int $userId): void
    {
        if ($path === null || $path === '') {
            return;
        }

        $relative = ltrim($path, '/');
        $pattern = '#^uploads/avatars/user-' . $userId . '-[a-f0-9]{16}\.(jpg|png|webp)$#';

        if (preg_match($pattern, $relative) !== 1) {
            return;
        }

        $directory = rtrim((string) config('paths.public', ''), '/\\') . '/uploads/avatars';
        $directoryReal = realpath($directory);
        $file = $directory . '/' . basename($relative);
        $fileReal = realpath($file);

        if (
            $directoryReal !== false
            && $fileReal !== false
            && str_starts_with($fileReal, $directoryReal . DIRECTORY_SEPARATOR)
        ) {
            @unlink($fileReal);
        }
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'That image is too large.',
            UPLOAD_ERR_PARTIAL => 'The upload did not finish. Try again.',
            UPLOAD_ERR_NO_TMP_DIR, UPLOAD_ERR_CANT_WRITE => 'The server could not store that image.',
            default => 'The upload failed. Try again.',
        };
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes >= 1024 * 1024) {
            return rtrim(rtrim(number_format($bytes / (1024 * 1024), 1), '0'), '.') . ' MB';
        }

        return rtrim(rtrim(number_format($bytes / 1024, 1), '0'), '.') . ' KB';
    }
}

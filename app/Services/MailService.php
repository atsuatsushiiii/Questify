<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

/**
 * Development mail transport.
 *
 * Password reset emails are written to storage/logs/mail.log so the flow is
 * fully testable without SMTP. Replace this class with a real transport later;
 * callers only depend on sendPasswordReset().
 */
final class MailService
{
    /**
     * @param array<string, mixed> $user
     */
    public function sendPasswordReset(array $user, string $token): void
    {
        if ((string) config('app.env', 'production') !== 'local') {
            throw new RuntimeException('No mail transport is configured for this environment.');
        }

        $root = (string) config('paths.storage', '');
        $directory = $root . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the mail log directory.');
        }

        $url = url('/reset-password') . '?token=' . $token;
        $minutes = (int) round(((int) config('auth.password_reset_ttl', 3600)) / 60);

        $message = sprintf(
            "[%s] PASSWORD RESET\nTo: %s <%s>\nSubject: Reset your Questify password\nExpires: %d minutes\nLink: %s\n\n",
            gmdate('c'),
            (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer'),
            (string) ($user['email'] ?? ''),
            $minutes,
            $url,
        );

        if (@file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'mail.log',
            $message,
            FILE_APPEND | LOCK_EX,
        ) === false) {
            throw new RuntimeException('Unable to write the password reset email.');
        }
    }

    /**
     * Send a confirmation link for a pending email change.
     *
     * @param array<string, mixed> $user
     */
    public function sendEmailChange(array $user, string $newEmail, string $token): void
    {
        if ((string) config('app.env', 'production') !== 'local') {
            throw new RuntimeException('No mail transport is configured for this environment.');
        }

        $root = (string) config('paths.storage', '');
        $directory = $root . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the mail log directory.');
        }

        $url = url('/settings/email/confirm') . '?token=' . $token;
        $minutes = (int) round(((int) config('auth.email_change_ttl', 3600)) / 60);

        $message = sprintf(
            "[%s] EMAIL CHANGE\nTo: %s <%s>\nSubject: Confirm your new Questify email address\nRequested from account: %s\nExpires: %d minutes\nLink: %s\n\n",
            gmdate('c'),
            (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer'),
            $newEmail,
            (string) ($user['email'] ?? ''),
            $minutes,
            $url,
        );

        if (@file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'mail.log',
            $message,
            FILE_APPEND | LOCK_EX,
        ) === false) {
            throw new RuntimeException('Unable to write the email change message.');
        }
    }
}

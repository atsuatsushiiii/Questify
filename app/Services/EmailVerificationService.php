<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\ErrorHandler;
use App\Models\EmailVerification;
use App\Models\User;
use Throwable;

/**
 * Signup email verification.
 *
 * Registration issues a single-use, hashed token and mails a confirmation
 * link. A token can be consumed without a session because possession of the
 * link already proves control of the mailbox; the account itself is resolved
 * from the stored row, never from request input.
 */
final class EmailVerificationService
{
    private const TOKEN_BYTES = 32;

    /**
     * Issue a verification link. Outstanding links stay valid until one is
     * consumed or they expire, and requests are limited per account per hour.
     *
     * @param array<string, mixed> $user
     * @return array{ok: bool, status?: int, message: string}
     */
    public function send(array $user): array
    {
        $userId = (int) ($user['id'] ?? 0);
        $email = mb_strtolower(trim((string) ($user['email'] ?? '')));

        if ($userId < 1 || $email === '') {
            return ['ok' => false, 'status' => 422, 'message' => 'That account cannot be verified.'];
        }

        if (!empty($user['email_verified_at'])) {
            return ['ok' => false, 'status' => 409, 'message' => 'This email address is already verified.'];
        }

        $perHour = max(1, (int) config('auth.email_verification_per_hour', 3));

        if (EmailVerification::countRecentForUser($userId, 3600) >= $perHour) {
            return [
                'ok' => false,
                'status' => 429,
                'message' => 'Too many verification emails. Try again in an hour.',
            ];
        }

        $ttl = max(300, (int) config('auth.email_verification_ttl', 86400));
        $token = bin2hex(random_bytes(self::TOKEN_BYTES));

        EmailVerification::purgeExpired();
        $requestId = EmailVerification::create(
            $userId,
            hash('sha256', $token),
            gmdate('Y-m-d H:i:s', time() + $ttl),
        );

        try {
            (new MailService())->sendEmailVerification($user, $token);
        } catch (Throwable $exception) {
            EmailVerification::deleteById($requestId);
            ErrorHandler::report($exception);

            return [
                'ok' => false,
                'status' => 500,
                'message' => 'The verification email could not be sent. Try again later.',
            ];
        }

        return [
            'ok' => true,
            'message' => 'Verification link sent to ' . $email . '. Open it to confirm your address.',
        ];
    }

    /**
     * @return array{ok: bool, status?: int, message: string, user_id?: int}
     */
    public function verify(string $token): array
    {
        $token = trim($token);

        if (strlen($token) !== self::TOKEN_BYTES * 2 || !ctype_xdigit($token)) {
            return ['ok' => false, 'status' => 422, 'message' => 'This verification link is invalid.'];
        }

        $pending = EmailVerification::findValidByHash(hash('sha256', $token));

        if ($pending === null) {
            return [
                'ok' => false,
                'status' => 422,
                'message' => 'This verification link is invalid or has expired. Request a new one from Settings.',
            ];
        }

        if (!EmailVerification::consume((int) $pending['id'])) {
            return ['ok' => false, 'status' => 422, 'message' => 'This verification link has already been used.'];
        }

        $userId = (int) $pending['user_id'];
        User::markEmailVerified($userId);

        // Verification clears every outstanding link for the account, so an
        // older emailed link cannot be replayed after the address is settled.
        EmailVerification::deleteForUser($userId);

        return [
            'ok' => true,
            'message' => 'Email address verified. Your account recovery options are now protected.',
            'user_id' => $userId,
        ];
    }
}

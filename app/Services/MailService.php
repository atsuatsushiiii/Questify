<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use RuntimeException;
use Throwable;

/**
 * Outbound mail.
 *
 * Callers only depend on sendPasswordReset() and sendEmailChange(). The
 * transport is chosen by config/mail.php: local development appends to
 * storage/logs/mail.log, production sends over SMTP (STARTTLS or implicit TLS).
 */
final class MailService
{
    /**
     * @param array<string, mixed> $user
     */
    public function sendPasswordReset(array $user, string $token): void
    {
        $name = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
        $email = (string) ($user['email'] ?? '');
        $url = url('/reset-password') . '?token=' . $token;
        $minutes = (int) round(((int) config('auth.password_reset_ttl', 3600)) / 60);

        $this->deliver(
            $email,
            $name,
            'Reset your Questify password',
            sprintf(
                "Hi %s,\n\nUse the link below to choose a new Questify password. It expires in %d minutes and can only be used once.\n\n%s\n\nIf you did not request this, you can ignore this email.\n",
                $name,
                $minutes,
                $url,
            ),
            sprintf(
                "[%s] PASSWORD RESET\nTo: %s <%s>\nSubject: Reset your Questify password\nExpires: %d minutes\nLink: %s\n\n",
                gmdate('c'),
                $name,
                $email,
                $minutes,
                $url,
            ),
        );
    }

    /**
     * Send a confirmation link for a pending email change.
     *
     * @param array<string, mixed> $user
     */
    public function sendEmailChange(array $user, string $newEmail, string $token): void
    {
        $name = (string) ($user['display_name'] ?? $user['username'] ?? 'Adventurer');
        $email = (string) ($user['email'] ?? '');
        $url = url('/settings/email/confirm') . '?token=' . $token;
        $minutes = (int) round(((int) config('auth.email_change_ttl', 3600)) / 60);

        $this->deliver(
            $newEmail,
            $name,
            'Confirm your new Questify email address',
            sprintf(
                "Hi %s,\n\nConfirm this address as the new sign-in email for your Questify account. The link expires in %d minutes and can only be used once.\n\n%s\n\nIf you did not request this change, ignore this email; the current address stays active.\n",
                $name,
                $minutes,
                $url,
            ),
            sprintf(
                "[%s] EMAIL CHANGE\nTo: %s <%s>\nSubject: Confirm your new Questify email address\nRequested from account: %s\nExpires: %d minutes\nLink: %s\n\n",
                gmdate('c'),
                $name,
                $newEmail,
                $email,
                $minutes,
                $url,
            ),
        );
    }

    /**
     * @param string $logEntry Pre-formatted line for the local mail log.
     */
    private function deliver(string $toEmail, string $toName, string $subject, string $body, string $logEntry): void
    {
        $transport = strtolower((string) config('mail.transport', 'log'));

        if ($transport === 'log') {
            $this->writeLog($logEntry);

            return;
        }

        if ($transport !== 'smtp') {
            throw new RuntimeException(sprintf('Unsupported mail transport [%s].', $transport));
        }

        $this->sendSmtp($toEmail, $toName, $subject, $body);
    }

    private function writeLog(string $message): void
    {
        $root = (string) config('paths.storage', '');
        $directory = $root . DIRECTORY_SEPARATOR . 'logs';

        if (!is_dir($directory) && !@mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new RuntimeException('Unable to create the mail log directory.');
        }

        if (@file_put_contents(
            $directory . DIRECTORY_SEPARATOR . 'mail.log',
            $message,
            FILE_APPEND | LOCK_EX,
        ) === false) {
            throw new RuntimeException('Unable to write the mail log entry.');
        }
    }

    private function sendSmtp(string $toEmail, string $toName, string $subject, string $body): void
    {
        if ($toEmail === '') {
            throw new RuntimeException('Cannot send mail without a recipient address.');
        }

        $host = trim((string) config('mail.host', ''));

        if ($host === '') {
            throw new RuntimeException('MAIL_HOST must be set when MAIL_TRANSPORT=smtp.');
        }

        $mailer = new PHPMailer(true);

        try {
            $mailer->isSMTP();
            $mailer->Host = $host;
            $mailer->Port = max(1, (int) config('mail.port', 587));
            $mailer->Timeout = max(1, (int) config('mail.timeout', 10));
            $mailer->CharSet = 'UTF-8';
            $mailer->isHTML(false);

            $username = (string) config('mail.username', '');

            if ($username !== '') {
                $mailer->SMTPAuth = true;
                $mailer->Username = $username;
                $mailer->Password = (string) config('mail.password', '');
            }

            $encryption = strtolower((string) config('mail.encryption', 'tls'));

            if ($encryption === 'tls') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
            } elseif ($encryption === 'ssl') {
                $mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
            } elseif ($encryption !== 'none') {
                throw new RuntimeException(sprintf('Unsupported MAIL_ENCRYPTION [%s].', $encryption));
            }

            $mailer->setFrom(
                (string) config('mail.from_address', 'no-reply@questify.local'),
                (string) config('mail.from_name', 'Questify'),
            );
            $mailer->addAddress($toEmail, $toName);
            $mailer->Subject = $subject;
            $mailer->Body = $body;

            $mailer->send();
        } catch (RuntimeException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            // Never surface SMTP credentials or raw server responses to the UI.
            throw new RuntimeException('Unable to send the email. Check the mail configuration and logs.', 0, $exception);
        }
    }
}

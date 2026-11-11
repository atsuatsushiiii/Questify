<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Services\MailService;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Local mail is a log transport, so the reset/change links can be asserted
 * without SMTP. The production guard must fail loudly when misconfigured.
 */
final class MailServiceTest extends TestCase
{
    private string $storage;
    private mixed $originalTransport;
    private mixed $originalStorage;
    private mixed $originalHost;

    protected function setUp(): void
    {
        $this->storage = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'questify-mail-' . bin2hex(random_bytes(6));
        $this->originalTransport = config('mail.transport');
        $this->originalStorage = config('paths.storage');
        $this->originalHost = config('mail.host');

        Config::set('mail.transport', 'log');
        Config::set('paths.storage', $this->storage);
    }

    protected function tearDown(): void
    {
        Config::set('mail.transport', $this->originalTransport);
        Config::set('paths.storage', $this->originalStorage);
        Config::set('mail.host', $this->originalHost);

        $log = $this->storage . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log';

        if (is_file($log)) {
            unlink($log);
        }

        if (is_dir(dirname($log))) {
            rmdir(dirname($log));
        }
    }

    public function testPasswordResetIsWrittenToTheLocalLog(): void
    {
        (new MailService())->sendPasswordReset(
            ['display_name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
            'reset-token-123',
        );

        $log = (string) file_get_contents(
            $this->storage . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log',
        );

        $this->assertStringContainsString('PASSWORD RESET', $log);
        $this->assertStringContainsString('To: Ada Lovelace <ada@example.com>', $log);
        $this->assertStringContainsString('/reset-password?token=reset-token-123', $log);
        $this->assertStringContainsString('Expires: 60 minutes', $log);
    }

    public function testEmailChangeGoesToTheNewAddress(): void
    {
        (new MailService())->sendEmailChange(
            ['display_name' => 'Ada Lovelace', 'email' => 'old@example.com'],
            'new@example.com',
            'change-token-456',
        );

        $log = (string) file_get_contents(
            $this->storage . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log',
        );

        $this->assertStringContainsString('EMAIL CHANGE', $log);
        $this->assertStringContainsString('To: Ada Lovelace <new@example.com>', $log);
        $this->assertStringContainsString('Requested from account: old@example.com', $log);
        $this->assertStringContainsString('/settings/email/confirm?token=change-token-456', $log);
    }

    public function testEmailVerificationGoesToTheNewAccount(): void
    {
        (new MailService())->sendEmailVerification(
            ['display_name' => 'Ada Lovelace', 'email' => 'ada@example.com'],
            'verify-token-789',
        );

        $log = (string) file_get_contents(
            $this->storage . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'mail.log',
        );

        $this->assertStringContainsString('EMAIL VERIFICATION', $log);
        $this->assertStringContainsString('To: Ada Lovelace <ada@example.com>', $log);
        $this->assertStringContainsString('/verify-email?token=verify-token-789', $log);
        $this->assertStringContainsString('Expires: 1440 minutes', $log);
    }

    public function testUnknownTransportFailsLoudly(): void
    {
        Config::set('mail.transport', 'carrier-pigeon');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Unsupported mail transport [carrier-pigeon].');

        (new MailService())->sendPasswordReset(
            ['display_name' => 'Ada', 'email' => 'ada@example.com'],
            'token',
        );
    }

    public function testSmtpWithoutAHostFailsBeforeConnecting(): void
    {
        Config::set('mail.transport', 'smtp');
        Config::set('mail.host', '');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('MAIL_HOST must be set when MAIL_TRANSPORT=smtp.');

        (new MailService())->sendPasswordReset(
            ['display_name' => 'Ada', 'email' => 'ada@example.com'],
            'token',
        );
    }
}

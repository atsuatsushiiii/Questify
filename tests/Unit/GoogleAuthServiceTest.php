<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Config;
use App\Services\GoogleAuthService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Google sign-in trust rules: usernames derived from email addresses and the
 * ID-token claims that must hold before an account is linked or created.
 */
final class GoogleAuthServiceTest extends TestCase
{
    /** @var array<string, mixed> */
    private array $original = [];

    protected function setUp(): void
    {
        $this->original = (array) config('oauth.google', []);

        Config::set('oauth.google.client_id', 'test-client.apps.googleusercontent.com');
        Config::set('oauth.google.client_secret', 'test-secret');
        Config::set('oauth.google.issuers', ['https://accounts.google.com', 'accounts.google.com']);
    }

    protected function tearDown(): void
    {
        foreach ($this->original as $key => $value) {
            Config::set('oauth.google.' . $key, $value);
        }
    }

    public function testIsConfiguredRequiresBothCredentials(): void
    {
        $service = new GoogleAuthService();

        $this->assertTrue($service->isConfigured());

        Config::set('oauth.google.client_secret', '');

        $this->assertFalse($service->isConfigured());
    }

    #[DataProvider('emails')]
    public function testUsernameFromEmailStaysWithinTheValidatorRules(string $email, string $expected): void
    {
        $username = GoogleAuthService::usernameFromEmail($email);

        $this->assertSame($expected, $username);
        $this->assertMatchesRegularExpression('/^[A-Za-z0-9_]{3,30}$/', $username);
    }

    /** @return array<string, array{string, string}> */
    public static function emails(): array
    {
        return [
            'simple local part' => ['kevin@example.com', 'kevin'],
            'dots and plus signs collapse' => ['Kevin.Cyril+quest@example.com', 'kevin_cyril_quest'],
            'short local part gets a prefix' => ['a@example.com', 'quest_a'],
            'long local part is truncated' => ['abcdefghijklmnopqrstuvwxyz1234@example.com', 'abcdefghijklmnopqrstuvwx'],
        ];
    }

    public function testAValidIdTokenIsAcceptedAndNormalised(): void
    {
        $service = new GoogleAuthService();
        $token = self::token(self::claims([
            'email' => 'Kevin@Example.com',
            'nonce' => 'nonce-value',
        ]));

        $payload = $service->validateIdToken($token, 'nonce-value');

        $this->assertSame('kevin@example.com', $payload['email']);
        $this->assertSame('google-subject-1', $payload['sub']);
    }

    #[DataProvider('invalidClaims')]
    public function testInvalidClaimsAreRejected(array $overrides, string $nonce, string $message): void
    {
        $service = new GoogleAuthService();
        $token = self::token(self::claims($overrides));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage($message);

        $service->validateIdToken($token, $nonce);
    }

    /** @return array<string, array{array<string, mixed>, string, string}> */
    public static function invalidClaims(): array
    {
        return [
            'wrong issuer' => [['iss' => 'https://evil.example'], 'nonce-value', 'issuer'],
            'wrong audience' => [['aud' => 'another-client'], 'nonce-value', 'audience'],
            'expired token' => [['exp' => time() - 3600], 'nonce-value', 'expired'],
            'issued in the future' => [['iat' => time() + 3600], 'nonce-value', 'future'],
            'nonce mismatch' => [[], 'different-nonce', 'nonce'],
            'unverified email' => [['email_verified' => false], 'nonce-value', 'verified'],
            'missing subject' => [['sub' => ''], 'nonce-value', 'subject'],
            'unusable email' => [['email' => 'not-an-email'], 'nonce-value', 'email address'],
        ];
    }

    public function testMalformedTokensAreRejected(): void
    {
        $service = new GoogleAuthService();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Malformed ID token');

        $service->validateIdToken('not-a-jwt', '');
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private static function claims(array $overrides = []): array
    {
        return array_merge([
            'iss' => 'https://accounts.google.com',
            'aud' => 'test-client.apps.googleusercontent.com',
            'iat' => time(),
            'exp' => time() + 300,
            'nonce' => 'nonce-value',
            'email' => 'kevin@example.com',
            'email_verified' => true,
            'sub' => 'google-subject-1',
        ], $overrides);
    }

    /**
     * @param array<string, mixed> $payload
     */
    private static function token(array $payload): string
    {
        $encode = static function (array $value): string {
            $json = json_encode($value, JSON_UNESCAPED_SLASHES);

            return rtrim(strtr(base64_encode($json === false ? '' : $json), '+/', '-_'), '=');
        };

        return $encode(['alg' => 'RS256', 'typ' => 'JWT'])
            . '.' . $encode($payload)
            . '.signature';
    }
}

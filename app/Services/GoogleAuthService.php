<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\ErrorHandler;
use App\Core\Request;
use App\Core\Session;
use App\Models\AuditLog;
use App\Models\User;
use RuntimeException;
use Throwable;

/**
 * Google OpenID Connect sign-in.
 *
 * The flow uses the authorization-code grant with PKCE and a session-bound
 * state value. The ID token is received directly from Google's token endpoint
 * over TLS, so per Google's guidance its signature does not need separate
 * verification; the issuer, audience, nonce, and expiry claims are still
 * validated before the account is trusted.
 */
final class GoogleAuthService
{
    private const SESSION_KEY = 'oauth.google';
    private const STATE_BYTES = 32;
    private const VERIFIER_BYTES = 32;

    public function isConfigured(): bool
    {
        $config = $this->config();

        return $config['client_id'] !== '' && $config['client_secret'] !== '';
    }

    /**
     * Begin the flow and return Google's authorization URL.
     *
     * @param array{intent?: string, user_id?: int|null, timezone?: string} $options
     */
    public function authorizationUrl(array $options = []): string
    {
        $config = $this->config();

        if (!$this->isConfigured()) {
            throw new RuntimeException('Google sign-in is not configured.');
        }

        $state = bin2hex(random_bytes(self::STATE_BYTES));
        $verifier = $this->base64Url(random_bytes(self::VERIFIER_BYTES));
        $nonce = bin2hex(random_bytes(16));

        Session::put(self::SESSION_KEY, [
            'state' => $state,
            'verifier' => $verifier,
            'nonce' => $nonce,
            'intent' => (string) ($options['intent'] ?? 'login'),
            'user_id' => isset($options['user_id']) ? (int) $options['user_id'] : null,
            'timezone' => (string) ($options['timezone'] ?? 'UTC'),
            'created_at' => time(),
        ]);

        $params = [
            'client_id' => $config['client_id'],
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', $config['scopes']),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $this->base64Url(hash('sha256', $verifier, true)),
            'code_challenge_method' => 'S256',
            'access_type' => 'online',
            'prompt' => 'select_account',
        ];

        return $config['authorization_endpoint'] . '?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{ok: bool, status?: int, message?: string, redirect?: string, user_id?: int}
     */
    public function handleCallback(string $code, string $state, Request $request): array
    {
        $pending = Session::pull(self::SESSION_KEY);

        if (!is_array($pending)
            || !isset($pending['state'], $pending['verifier'], $pending['nonce'], $pending['created_at'])
            || time() - (int) $pending['created_at'] > (int) $this->config()['state_ttl']
        ) {
            return $this->fail('That sign-in attempt has expired. Please try again.');
        }

        if ($state === '' || !hash_equals((string) $pending['state'], $state)) {
            return $this->fail('That sign-in attempt could not be verified. Please try again.');
        }

        if ($code === '') {
            return $this->fail('Google did not return an authorization code. Please try again.');
        }

        try {
            $token = $this->exchangeCode($code, (string) $pending['verifier']);
            $claims = $this->validateIdToken(
                (string) ($token['id_token'] ?? ''),
                (string) $pending['nonce'],
            );
        } catch (Throwable $exception) {
            ErrorHandler::report($exception);

            return $this->fail('Google sign-in could not be completed. Please try again.');
        }

        if ((string) ($pending['intent'] ?? 'login') === 'link') {
            return $this->linkAccount((int) ($pending['user_id'] ?? 0), $claims, $request);
        }

        return $this->signIn($claims, (string) ($pending['timezone'] ?? 'UTC'), $request);
    }

    /**
     * Remove the Google link. Refused when the account has no usable password,
     * so disconnecting cannot lock the owner out.
     *
     * @return array{ok: bool, message: string}
     */
    public function disconnect(int $userId, ?string $ip = null): array
    {
        $user = User::find($userId);

        if ($user === null) {
            return ['ok' => false, 'message' => 'That account could not be found.'];
        }

        if (($user['google_id'] ?? null) === null) {
            return ['ok' => false, 'message' => 'Google is not connected to this account.'];
        }

        if (($user['password_set_at'] ?? null) === null) {
            return ['ok' => false, 'message' => 'Set a password before disconnecting Google so you can still sign in.'];
        }

        User::disconnectGoogle($userId);

        AuditLog::record([
            'actor_user_id' => $userId,
            'action' => 'auth.google.disconnect',
            'target_type' => 'user',
            'target_id' => $userId,
            'summary' => 'Disconnected Google sign-in.',
            'ip_address' => $ip,
        ]);

        return ['ok' => true, 'message' => 'Google sign-in has been disconnected from your account.'];
    }

    /**
     * Derive a valid, human-readable username from an email local part.
     */
    public static function usernameFromEmail(string $email): string
    {
        $local = strstr($email, '@', true);
        $local = strtolower($local === false ? $email : $local);
        $base = preg_replace('/[^a-z0-9_]+/', '_', $local) ?? '';
        $base = trim($base, '_');

        if (strlen($base) < 3) {
            $base = 'quest_' . $base;
        }

        $base = substr($base, 0, 24);
        $base = trim($base, '_');

        return $base === '' ? 'quest_user' : $base;
    }

    /**
     * Decode and validate an ID token payload.
     *
     * Public so the security rules can be exercised without a live Google
     * exchange. Never call this with a token from an untrusted transport.
     *
     * @return array<string, mixed>
     */
    public function validateIdToken(string $idToken, string $expectedNonce = ''): array
    {
        $parts = explode('.', $idToken);

        if (count($parts) !== 3) {
            throw new RuntimeException('Malformed ID token.');
        }

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        if (!is_array($payload)) {
            throw new RuntimeException('Malformed ID token payload.');
        }

        $config = $this->config();
        $now = time();

        if (!in_array((string) ($payload['iss'] ?? ''), $config['issuers'], true)) {
            throw new RuntimeException('Unexpected ID token issuer.');
        }

        if (!isset($payload['aud']) || (string) $payload['aud'] !== (string) $config['client_id']) {
            throw new RuntimeException('ID token audience mismatch.');
        }

        if ((int) ($payload['exp'] ?? 0) < $now - 60) {
            throw new RuntimeException('ID token has expired.');
        }

        if ((int) ($payload['iat'] ?? 0) > $now + 120) {
            throw new RuntimeException('ID token was issued in the future.');
        }

        if ($expectedNonce !== '' && !hash_equals($expectedNonce, (string) ($payload['nonce'] ?? ''))) {
            throw new RuntimeException('ID token nonce mismatch.');
        }

        $verified = $payload['email_verified'] ?? false;

        if ($verified !== true && $verified !== 'true' && $verified !== 1 && $verified !== '1') {
            throw new RuntimeException('Google has not verified this email address.');
        }

        $email = mb_strtolower(trim((string) ($payload['email'] ?? '')));

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new RuntimeException('ID token is missing a usable email address.');
        }

        $subject = trim((string) ($payload['sub'] ?? ''));

        if ($subject === '' || strlen($subject) > 64) {
            throw new RuntimeException('ID token is missing a usable subject.');
        }

        $payload['email'] = $email;
        $payload['sub'] = $subject;

        return $payload;
    }

    /**
     * @param array<string, mixed> $claims
     * @return array{ok: bool, status?: int, message: string, redirect?: string, user_id?: int}
     */
    private function signIn(array $claims, string $timezone, Request $request): array
    {
        $googleId = (string) $claims['sub'];
        $email = (string) $claims['email'];

        $user = User::findByGoogleId($googleId);
        $created = false;

        if ($user !== null) {
            if (($user['status'] ?? '') !== 'active') {
                return $this->fail('This account is suspended. Contact an administrator if you believe this is a mistake.', 403);
            }

            if (mb_strtolower((string) ($user['google_email'] ?? '')) !== $email) {
                User::updateGoogleEmail((int) $user['id'], $email);
            }
        } else {
            $user = User::findByEmail($email);

            if ($user !== null) {
                if (($user['google_id'] ?? null) !== null) {
                    return $this->fail('This email address is already linked to a different Google account.');
                }

                if (($user['status'] ?? '') !== 'active') {
                    return $this->fail('This account is suspended. Contact an administrator if you believe this is a mistake.', 403);
                }

                User::linkGoogle((int) $user['id'], $googleId, $email);
                $user = User::find((int) $user['id']) ?? $user;
            } else {
                $userId = $this->createAccount($claims, $timezone);
                $user = User::find($userId);
                $created = true;

                if ($user === null) {
                    return $this->fail('Your Questify account could not be created. Please try again.');
                }
            }
        }

        $userId = (int) $user['id'];
        User::touchLogin($userId);

        AuditLog::record([
            'actor_user_id' => $userId,
            'action' => $created ? 'auth.google.register' : 'auth.google.login',
            'target_type' => 'user',
            'target_id' => $userId,
            'summary' => $created ? 'Created an account with Google.' : 'Signed in with Google.',
            'ip_address' => $request->ip(),
        ]);

        $refreshed = User::find($userId) ?? $user;
        Auth::login($refreshed);

        $name = (string) ($refreshed['display_name'] ?? $refreshed['username'] ?? 'adventurer');

        return [
            'ok' => true,
            'redirect' => Auth::intended('/dashboard'),
            'message' => $created
                ? 'Welcome to Questify, ' . $name . '. Your quest log is ready.'
                : 'Welcome back, ' . $name . '.',
            'user_id' => $userId,
        ];
    }

    /**
     * @param array<string, mixed> $claims
     * @return array{ok: bool, status?: int, message: string, redirect?: string, user_id?: int}
     */
    private function linkAccount(int $userId, array $claims, Request $request): array
    {
        if ($userId <= 0) {
            return $this->fail('That sign-in attempt is no longer valid.');
        }

        $user = User::find($userId);

        if ($user === null || ($user['status'] ?? '') !== 'active') {
            return $this->fail('That account is no longer available.', 403);
        }

        $googleId = (string) $claims['sub'];
        $email = (string) $claims['email'];
        $existing = User::findByGoogleId($googleId);

        if ($existing !== null && (int) $existing['id'] !== $userId) {
            return $this->fail('That Google account is already linked to another Questify account.');
        }

        if ($email !== mb_strtolower((string) ($user['email'] ?? ''))) {
            return $this->fail(
                'Sign in with the Google account that uses ' . (string) ($user['email'] ?? 'your Questify email') . '.',
            );
        }

        if (($user['google_id'] ?? null) !== null && (string) $user['google_id'] !== $googleId) {
            return $this->fail('This account is already linked to a different Google account.');
        }

        User::linkGoogle($userId, $googleId, $email);

        AuditLog::record([
            'actor_user_id' => $userId,
            'action' => 'auth.google.link',
            'target_type' => 'user',
            'target_id' => $userId,
            'summary' => 'Connected Google sign-in.',
            'ip_address' => $request->ip(),
        ]);

        return [
            'ok' => true,
            'redirect' => '/settings#security',
            'message' => 'Google sign-in is now connected to your account.',
            'user_id' => $userId,
        ];
    }

    /**
     * @param array<string, mixed> $claims
     */
    private function createAccount(array $claims, string $timezone): int
    {
        $email = (string) $claims['email'];
        $displayName = trim((string) ($claims['name'] ?? ''));

        if ($displayName === '') {
            $displayName = ucfirst(str_replace('_', ' ', self::usernameFromEmail($email)));
        }

        $displayName = mb_substr($displayName, 0, 50);

        $hash = password_hash(bin2hex(random_bytes(32)), PASSWORD_DEFAULT);

        if ($hash === false) {
            throw new RuntimeException('Unable to create a placeholder password hash.');
        }

        return User::createFromGoogle([
            'username' => $this->uniqueUsername($email),
            'email' => $email,
            'display_name' => $displayName,
            'timezone' => $this->validTimezone($timezone),
            'google_id' => (string) $claims['sub'],
            'google_email' => $email,
            'password_hash' => $hash,
        ]);
    }

    private function uniqueUsername(string $email): string
    {
        $base = self::usernameFromEmail($email);
        $candidate = $base;

        for ($suffix = 1; $suffix <= 9999; $suffix++) {
            if (!User::usernameExists($candidate)) {
                return $candidate;
            }

            $tail = '_' . $suffix;
            $candidate = substr($base, 0, 30 - strlen($tail)) . $tail;
        }

        return 'quest_' . bin2hex(random_bytes(6));
    }

    private function validTimezone(string $timezone): string
    {
        return in_array($timezone, timezone_identifiers_list(), true) ? $timezone : 'UTC';
    }

    /**
     * @return array<string, mixed>
     */
    private function exchangeCode(string $code, string $verifier): array
    {
        $config = $this->config();

        $response = $this->postForm($config['token_endpoint'], [
            'code' => $code,
            'client_id' => $config['client_id'],
            'client_secret' => $config['client_secret'],
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
            'code_verifier' => $verifier,
        ]);

        if (!isset($response['id_token'])) {
            throw new RuntimeException('Google token response did not include an ID token.');
        }

        return $response;
    }

    /**
     * @param array<string, string> $fields
     * @return array<string, mixed>
     */
    private function postForm(string $url, array $fields): array
    {
        $body = http_build_query($fields, '', '&', PHP_QUERY_RFC3986);
        $timeout = max(1, (int) $this->config()['timeout']);

        if (function_exists('curl_init')) {
            $handle = curl_init($url);

            if ($handle === false) {
                throw new RuntimeException('Unable to start the Google token request.');
            }

            curl_setopt_array($handle, [
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $body,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CONNECTTIMEOUT => $timeout,
                CURLOPT_TIMEOUT => $timeout,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/x-www-form-urlencoded',
                    'Accept: application/json',
                ],
            ]);

            $raw = curl_exec($handle);
            $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
            $error = curl_error($handle);
            curl_close($handle);

            if ($raw === false) {
                throw new RuntimeException('Google token request failed: ' . $error);
            }
        } else {
            $context = stream_context_create([
                'http' => [
                    'method' => 'POST',
                    'header' => "Content-Type: application/x-www-form-urlencoded\r\nAccept: application/json\r\n",
                    'content' => $body,
                    'timeout' => $timeout,
                    'ignore_errors' => true,
                ],
            ]);

            $raw = @file_get_contents($url, false, $context);

            if ($raw === false) {
                throw new RuntimeException('Google token request failed.');
            }

            $status = 0;

            foreach ($http_response_header ?? [] as $header) {
                if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                    $status = (int) $matches[1];
                }
            }
        }

        $decoded = json_decode($raw, true);

        if ($status < 200 || $status >= 300 || !is_array($decoded)) {
            $detail = is_array($decoded)
                ? (string) ($decoded['error_description'] ?? $decoded['error'] ?? 'invalid response')
                : 'invalid response';

            throw new RuntimeException('Google token endpoint rejected the request: ' . $detail);
        }

        return $decoded;
    }

    private function redirectUri(): string
    {
        $configured = (string) $this->config()['redirect_uri'];

        return $configured !== '' ? $configured : url('/auth/google/callback');
    }

    private function base64Url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new RuntimeException('Malformed base64url value.');
        }

        return $decoded;
    }

    /**
     * @return array<string, mixed>
     */
    private function config(): array
    {
        /** @var array<string, mixed> $config */
        $config = config('oauth.google', []);

        return $config;
    }

    /**
     * @return array{ok: false, status: int, message: string}
     */
    private function fail(string $message, int $status = 422): array
    {
        return ['ok' => false, 'status' => $status, 'message' => $message];
    }
}

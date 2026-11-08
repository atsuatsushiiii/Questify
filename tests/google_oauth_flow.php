<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Google sign-in HTTP suite.
 *
 * Exercises the CSRF-protected start endpoint, the callback's failure paths,
 * the Settings surface, and the database columns without contacting Google.
 * A live Google exchange requires real credentials and is intentionally out of
 * scope for automated runs.
 *
 * Usage: php tests/google_oauth_flow.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Core\Request;
use App\Services\GoogleAuthService;

App::boot(BASE_PATH, false);

$baseUrl = rtrim((string) config('app.url', 'http://localhost:8000'), '/');
$configured = (string) config('oauth.google.client_id', '') !== ''
    && (string) config('oauth.google.client_secret', '') !== '';

final class HttpClient
{
    private string $cookieFile;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_google_');
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function request(string $method, string $path, array $data = []): array
    {
        $ch = curl_init($this->baseUrl . $path);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_CUSTOMREQUEST => $method,
        ]);

        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

        $raw = curl_exec($ch);

        if ($raw === false) {
            throw new RuntimeException('HTTP request failed: ' . curl_error($ch));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $headerSize = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $headerBlock = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        curl_close($ch);

        $headers = [];

        foreach (preg_split('/\r\n|\n|\r/', trim($headerBlock)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $headers[strtolower(trim($name))] = trim($value);
        }

        return ['status' => $status, 'headers' => $headers, 'body' => $body];
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

    /** @return array{status: int, headers: array<string, string>, body: string} */
    public function post(string $path, array $data = []): array
    {
        return $this->request('POST', $path, $data);
    }

    public function csrf(string $html): ?string
    {
        return preg_match('/name="_csrf_token" value="([a-f0-9]+)"/', $html, $match) === 1
            ? $match[1]
            : null;
    }
}

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS {$label}\n";

        return;
    }

    $failed++;
    echo "  FAIL {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
}

function contains(string $haystack, string $needle): bool
{
    return $needle !== '' && str_contains($haystack, $needle);
}

function endsWith(string $haystack, string $needle): bool
{
    return $needle !== '' && str_ends_with($haystack, $needle);
}

$client = new HttpClient($baseUrl);

echo "Google sign-in flow\n";
echo "  base URL: {$baseUrl}\n";
echo '  configured: ' . ($configured ? 'yes' : 'no (guarded paths only)') . "\n";

echo "\n1. Guest pages and route guards\n";

$login = $client->get('/login');
check('Login page renders', $login['status'] === 200);
check(
    'CSP allows the redirect from the Google start form',
    contains(
        (string) ($login['headers']['content-security-policy'] ?? ''),
        "form-action 'self' https://accounts.google.com",
    ),
);
check(
    $configured ? 'Login shows the Google button' : 'Login hides Google when unconfigured',
    $configured
        ? contains($login['body'], 'Continue with Google')
        : !contains($login['body'], 'Continue with Google'),
);

$register = $client->get('/register');
check('Registration page renders', $register['status'] === 200);
check(
    $configured ? 'Registration shows the Google button' : 'Registration hides Google when unconfigured',
    $configured
        ? contains($register['body'], 'Sign up with Google')
        : !contains($register['body'], 'Sign up with Google'),
);

$noCsrf = $client->post('/auth/google');
check('Starting Google sign-in without CSRF is rejected', $noCsrf['status'] === 419);

$linkGuard = $client->post('/settings/google', []);
check(
    'Connecting Google requires authentication',
    $linkGuard['status'] === 302 && endsWith($linkGuard['headers']['location'] ?? '', '/login'),
);

echo "\n2. Callback failure paths\n";

$badState = $client->get('/auth/google/callback?state=forged&code=forged');
check('A forged callback state is rejected without a server error', $badState['status'] === 302);
check(
    'A forged callback redirects a guest to sign-in',
    endsWith($badState['headers']['location'] ?? '', '/login'),
);

$denied = $client->get('/auth/google/callback?error=access_denied');
check('A cancelled Google consent redirects cleanly', $denied['status'] === 302);
check(
    'Cancelling Google consent returns to sign-in',
    endsWith($denied['headers']['location'] ?? '', '/login'),
);

echo "\n3. Start endpoint\n";

$csrf = $client->csrf($login['body']);
check('Login page exposes a CSRF token', $csrf !== null);

$start = $client->post('/auth/google', [
    '_csrf_token' => $csrf,
    'timezone' => 'Europe/London',
]);

if ($configured) {
    $location = $start['headers']['location'] ?? '';
    check('Configured start redirects to Google', $start['status'] === 302 && str_starts_with($location, 'https://accounts.google.com/o/oauth2/v2/auth'));
    check('Authorization URL uses PKCE S256', contains($location, 'code_challenge_method=S256'));
    check('Authorization URL carries a state value', contains($location, 'state='));
    check('Authorization URL requests a nonce', contains($location, 'nonce='));
} else {
    check(
        'Unconfigured start falls back to sign-in',
        $start['status'] === 302 && endsWith($start['headers']['location'] ?? '', '/login'),
    );
}

echo "\n4. Settings surface\n";

$suffix = bin2hex(random_bytes(4));
$displayName = 'Google QA ' . $suffix;
$username = 'gqa_' . $suffix;
$email = 'gqa_' . $suffix . '@example.test';
$password = 'Questify-' . $suffix . '!';

$registerCsrf = $client->csrf($register['body']);
$registered = $client->post('/register', [
    '_csrf_token' => $registerCsrf,
    'display_name' => $displayName,
    'username' => $username,
    'email' => $email,
    'timezone' => 'UTC',
    'password' => $password,
    'password_confirmation' => $password,
    'terms' => '1',
]);
check('A test account can be created', $registered['status'] === 302 && endsWith($registered['headers']['location'] ?? '', '/dashboard'));

$settings = $client->get('/settings');
check('Settings page renders', $settings['status'] === 200);
check('Settings shows the connected-accounts card', contains($settings['body'], 'Connected accounts'));
check('Settings shows Google as not connected', contains($settings['body'], 'Not connected'));
check(
    $configured ? 'Settings offers Connect Google' : 'Settings explains Google is unconfigured',
    $configured
        ? contains($settings['body'], 'Connect Google')
        : contains($settings['body'], 'not configured on this deployment'),
);

$settingsCsrf = $client->csrf($settings['body']);
check('Settings exposes a CSRF token', $settingsCsrf !== null);

$unlink = $client->post('/settings/google/unlink', ['_csrf_token' => $settingsCsrf]);
check(
    'Disconnecting an unlinked account redirects back to settings',
    $unlink['status'] === 302 && endsWith($unlink['headers']['location'] ?? '', '/settings#security'),
);

$afterUnlink = $client->get('/settings');
check(
    'Disconnecting an unlinked account reports the problem',
    contains($afterUnlink['body'], 'Google is not connected to this account'),
);

echo "\n5. Schema and account state\n";

$pdo = Database::connection();

$columns = $pdo->query('SHOW COLUMNS FROM users')->fetchAll(PDO::FETCH_COLUMN);
check('users.google_id exists', in_array('google_id', $columns, true));
check('users.google_email exists', in_array('google_email', $columns, true));
check('users.password_set_at exists', in_array('password_set_at', $columns, true));

$row = $pdo->prepare('SELECT google_id, google_email, password_set_at FROM users WHERE email = ?');
$row->execute([$email]);
$created = $row->fetch(PDO::FETCH_ASSOC);

check('Password registration records password_set_at', is_array($created) && $created['password_set_at'] !== null);
check('Password registration has no Google link', is_array($created) && $created['google_id'] === null);

echo "\n6. Account resolution and linking\n";

$service = new GoogleAuthService();
$signIn = new ReflectionMethod($service, 'signIn');
$signIn->setAccessible(true);
$linkAccount = new ReflectionMethod($service, 'linkAccount');
$linkAccount->setAccessible(true);

$claims = static fn (string $sub, string $claimEmail, string $name = '') => [
    'iss' => 'https://accounts.google.com',
    'aud' => 'test-client.apps.googleusercontent.com',
    'iat' => time(),
    'exp' => time() + 300,
    'email' => $claimEmail,
    'email_verified' => true,
    'sub' => $sub,
    'name' => $name,
];

$request = Request::capture();
$googleEmail = 'google_' . $suffix . '@example.test';
$googleSub = 'google-sub-' . $suffix;

$first = $signIn->invoke($service, $claims($googleSub, $googleEmail, 'Google Hero'), 'Europe/London', $request);
check('A verified Google identity creates an account', ($first['ok'] ?? false) === true);

$googleRow = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$googleRow->execute([$googleEmail]);
$googleUser = $googleRow->fetch(PDO::FETCH_ASSOC);

check('Google account stores the subject', is_array($googleUser) && $googleUser['google_id'] === $googleSub);
check('Google account stores the Google email', is_array($googleUser) && $googleUser['google_email'] === $googleEmail);
check('Google account is marked email verified', is_array($googleUser) && $googleUser['email_verified_at'] !== null);
check('Google account has no usable password', is_array($googleUser) && $googleUser['password_set_at'] === null);
check('Google account keeps the requested timezone', is_array($googleUser) && $googleUser['timezone'] === 'Europe/London');
check('Google account uses the profile name', is_array($googleUser) && $googleUser['display_name'] === 'Google Hero');
check(
    'Google username satisfies the validator rules',
    is_array($googleUser) && preg_match('/^[A-Za-z0-9_]{3,30}$/', (string) $googleUser['username']) === 1,
);

$again = $signIn->invoke($service, $claims($googleSub, $googleEmail, 'Google Hero'), 'UTC', $request);
check('Signing in again with the same subject succeeds', ($again['ok'] ?? false) === true);

$count = $pdo->prepare('SELECT COUNT(*) FROM users WHERE google_id = ?');
$count->execute([$googleSub]);
check('Signing in again does not duplicate the account', (int) $count->fetchColumn() === 1);

$linkExisting = $signIn->invoke($service, $claims('google-link-' . $suffix, $email), 'UTC', $request);
check('A verified Google email links to an existing account', ($linkExisting['ok'] ?? false) === true);

$linkedRow = $pdo->prepare('SELECT id, google_id, password_set_at FROM users WHERE email = ?');
$linkedRow->execute([$email]);
$linked = $linkedRow->fetch(PDO::FETCH_ASSOC);
check('The existing account gains the Google subject', is_array($linked) && $linked['google_id'] === 'google-link-' . $suffix);
check('Linking preserves the existing password', is_array($linked) && $linked['password_set_at'] !== null);

$existingUserId = (int) ($linked['id'] ?? 0);

$conflict = $signIn->invoke($service, $claims('google-other-' . $suffix, $email), 'UTC', $request);
check(
    'A second Google subject cannot take over a linked email',
    ($conflict['ok'] ?? false) === false && contains((string) ($conflict['message'] ?? ''), 'already linked'),
);

$wrongEmailLink = $linkAccount->invoke(
    $service,
    (int) $googleUser['id'],
    $claims('google-mismatch-' . $suffix, 'someone-else@example.test'),
    $request,
);
check(
    'Connecting a Google account with a different email is refused',
    ($wrongEmailLink['ok'] ?? false) === false && contains((string) ($wrongEmailLink['message'] ?? ''), 'Sign in with the Google account'),
);

$noPasswordDisconnect = $service->disconnect((int) $googleUser['id']);
check(
    'A passwordless Google account cannot be disconnected',
    $noPasswordDisconnect['ok'] === false && contains($noPasswordDisconnect['message'], 'Set a password'),
);

$disconnect = $service->disconnect($existingUserId);
check('A linked account can be disconnected', $disconnect['ok'] === true);

$cleared = $pdo->prepare('SELECT google_id FROM users WHERE email = ?');
$cleared->execute([$email]);
check('Disconnecting clears the Google subject', $cleared->fetchColumn() === null);

$repeat = $service->disconnect($existingUserId);
check('Disconnecting twice reports that Google is not connected', $repeat['ok'] === false);

echo "\n7. Cleanup\n";

// Remove the accounts this run created so repeated runs stay isolated.
// Audit rows are append-only in the app, but test actors must go first to
// satisfy the actor foreign key.
$pdo->exec(
    "DELETE FROM audit_logs WHERE actor_user_id IN (
        SELECT id FROM users WHERE email LIKE 'gqa\\_%@example.test' OR email LIKE 'google\\_%@example.test'
    )",
);
$pdo->exec(
    "DELETE FROM users WHERE email LIKE 'gqa\\_%@example.test' OR email LIKE 'google\\_%@example.test'",
);

$leftover = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email IN (?, ?)');
$leftover->execute([$email, $googleEmail]);
check('Test accounts are removed', (int) $leftover->fetchColumn() === 0);

echo "\n" . str_repeat('-', 46) . "\n";
echo "  {$passed} passed, {$failed} failed\n";

if ($failed > 0) {
    exit(1);
}

echo "Google sign-in suite passed.\n";

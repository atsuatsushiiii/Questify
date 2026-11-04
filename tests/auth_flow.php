<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * End-to-end authentication test.
 *
 * Requires the application to be served at APP_URL (default
 * http://localhost:8000) and the database to be migrated.
 *
 * Usage: php tests/auth_flow.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;

App::boot(BASE_PATH, false);

$baseUrl = rtrim((string) config('app.url', 'http://localhost:8000'), '/');

final class HttpClient
{
    private string $cookieFile;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_cookies_');
    }

    /** @return array{status: int, headers: array<string, string>, body: string, setCookie: list<string>} */
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
        $setCookie = [];

        foreach (preg_split('/\r\n|\n|\r/', trim($headerBlock)) ?: [] as $line) {
            if (!str_contains($line, ':')) {
                continue;
            }

            [$name, $value] = explode(':', $line, 2);
            $name = strtolower(trim($name));
            $value = trim($value);

            if ($name === 'set-cookie') {
                $setCookie[] = $value;
            }

            $headers[$name] = $value;
        }

        return [
            'status' => $status,
            'headers' => $headers,
            'body' => $body,
            'setCookie' => $setCookie,
        ];
    }

    public function get(string $path): array
    {
        return $this->request('GET', $path);
    }

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

    public function cookieJar(): string
    {
        return $this->cookieFile;
    }

    public function useJar(string $file): void
    {
        $this->cookieFile = $file;
    }
}

function jarLines(string $file): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_values(array_filter($lines, static fn (string $line): bool => !str_starts_with($line, '# ') && $line !== '#HttpOnly_'));
}

function jarValue(string $file, string $name): ?string
{
    foreach (jarLines($file) as $line) {
        $parts = explode("\t", $line);

        if (count($parts) >= 7 && $parts[5] === $name) {
            return urldecode($parts[6]);
        }
    }

    return null;
}

/**
 * Copy the jar keeping only cookies other than the excluded one.
 */
function jarWithout(string $file, string $exclude): string
{
    $target = (string) tempnam(sys_get_temp_dir(), 'questify_jar_');
    $kept = [];

    foreach (jarLines($file) as $line) {
        $parts = explode("\t", $line);

        if (count($parts) >= 7 && $parts[5] !== $exclude) {
            $kept[] = $line;
        }
    }

    file_put_contents($target, implode(PHP_EOL, $kept) . PHP_EOL);

    return $target;
}

$passed = 0;
$failed = 0;

function check(string $label, bool $condition, string $detail = ''): void
{
    global $passed, $failed;

    if ($condition) {
        $passed++;
        echo "  PASS  {$label}\n";

        return;
    }

    $failed++;
    echo "  FAIL  {$label}" . ($detail !== '' ? "  ({$detail})" : '') . "\n";
}

function contains(string $haystack, string $needle): bool
{
    return str_contains($haystack, $needle);
}

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$username = 'runner_' . $suffix;
$email = 'runner_' . $suffix . '@example.test';
$password = 'Questify-' . $suffix . '7';
$newPassword = 'NewKey-' . $suffix . '9';
$displayName = 'Test Runner ' . $suffix;

$client = new HttpClient($baseUrl);

echo "\n1. Public pages and route protection\n";

$home = $client->get('/');
check('Landing page still renders', $home['status'] === 200 && contains($home['body'], 'Turn your to-do list'));

$health = $client->get('/api/health');
check('Health endpoint still healthy', $health['status'] === 200 && contains($health['body'], '"database":"ok"'));

$protected = $client->get('/dashboard');
check('Dashboard requires authentication', $protected['status'] === 302 && str_ends_with($protected['headers']['location'] ?? '', '/login'));

$missing = $client->get('/not-a-real-page');
check('Unknown page is still a styled 404', $missing['status'] === 404 && contains($missing['body'], 'This quest'));

echo "\n2. Registration validation\n";

$registerPage = $client->get('/register');
$csrf = $client->csrf($registerPage['body']);
check('Registration page renders with CSRF token', $registerPage['status'] === 200 && $csrf !== null);
check('Timezone selector is populated', substr_count($registerPage['body'], '<option value=') > 400);

$invalid = $client->post('/register', [
    '_csrf_token' => $csrf,
    'display_name' => 'A',
    'username' => 'x',
    'email' => 'not-an-email',
    'timezone' => 'Mars/Olympus',
    'password' => 'short',
    'password_confirmation' => 'different',
]);
check('Invalid registration returns 422', $invalid['status'] === 422);
check('Invalid registration shows field errors', contains($invalid['body'], 'must be at least') && contains($invalid['body'], 'valid email'));

$noCsrf = $client->post('/register', [
    'display_name' => $displayName,
    'username' => $username,
    'email' => $email,
    'timezone' => 'UTC',
    'password' => $password,
    'password_confirmation' => $password,
    'terms' => '1',
]);
check('Registration without CSRF is rejected with 419', $noCsrf['status'] === 419);

echo "\n3. Successful registration\n";

$csrf = $client->csrf($invalid['body']) ?? $csrf;
$registered = $client->post('/register', [
    '_csrf_token' => $csrf,
    'display_name' => $displayName,
    'username' => $username,
    'email' => $email,
    'timezone' => 'Europe/London',
    'password' => $password,
    'password_confirmation' => $password,
    'terms' => '1',
]);
check('Valid registration redirects to dashboard', $registered['status'] === 302 && str_ends_with($registered['headers']['location'] ?? '', '/dashboard'));

$userRow = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$userRow->execute([$email]);
$user = $userRow->fetch(PDO::FETCH_ASSOC);
check('User row created', is_array($user) && $user['username'] === $username);
check('Password stored as a bcrypt hash', is_array($user) && str_starts_with((string) $user['password_hash'], '$2y$'));
check('Password verifies against the hash', is_array($user) && password_verify($password, (string) $user['password_hash']));
check('Plain-text password is not stored', is_array($user) && $user['password_hash'] !== $password);

$dashboard = $client->get('/dashboard');
check('Dashboard loads after registration', $dashboard['status'] === 200 && contains($dashboard['body'], $displayName));
check('Dashboard shows real starting level', contains($dashboard['body'], 'Level 1'));

echo "\n4. CSRF and logout\n";

$dashboardCsrf = $client->csrf($dashboard['body']);
check('Dashboard exposes a logout CSRF token', $dashboardCsrf !== null);

$logoutWithoutToken = $client->post('/logout');
check('Logout without CSRF is rejected', $logoutWithoutToken['status'] === 419);

$logout = $client->post('/logout', ['_csrf_token' => $dashboardCsrf]);
check('Logout redirects to sign-in', $logout['status'] === 302 && str_ends_with($logout['headers']['location'] ?? '', '/login'));

$afterLogout = $client->get('/dashboard');
check('Session no longer accesses dashboard', $afterLogout['status'] === 302);

echo "\n5. Login throttling\n";

$loginPage = $client->get('/login');
$csrf = $client->csrf($loginPage['body']);
check('Login page renders with CSRF token', $loginPage['status'] === 200 && $csrf !== null);

for ($attempt = 1; $attempt <= 5; $attempt++) {
    $failure = $client->post('/login', [
        '_csrf_token' => $csrf,
        'identifier' => $email,
        'password' => 'wrong-password-' . $attempt,
    ]);
    check("Failed login {$attempt} returns 401", $failure['status'] === 401);
    $csrf = $client->csrf($failure['body']) ?? $csrf;
}

$throttled = $client->post('/login', [
    '_csrf_token' => $csrf,
    'identifier' => $email,
    'password' => $password,
]);
check('Sixth attempt is throttled with 429', $throttled['status'] === 429);
check('Throttle response sets Retry-After', isset($throttled['headers']['retry-after']));
check('Throttle message is explicit', contains($throttled['body'], 'Too many failed attempts'));

// Simulate the lockout window expiring so the rest of the flow can run.
$pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($email)]);

$csrf = $client->csrf($throttled['body']) ?? $csrf;
$login = $client->post('/login', [
    '_csrf_token' => $csrf,
    'identifier' => $email,
    'password' => $password,
]);
check('Correct password signs in after throttle window', $login['status'] === 302 && str_ends_with($login['headers']['location'] ?? '', '/dashboard'));
check('Successful login rotates the session cookie', jarValue($client->cookieJar(), 'questify_session') !== null);

echo "\n6. Remember-me\n";

$dashboardCsrf = $client->csrf($client->get('/dashboard')['body']);
$client->post('/logout', ['_csrf_token' => $dashboardCsrf]);

$loginPage = $client->get('/login');
$csrf = $client->csrf($loginPage['body']);
$rememberLogin = $client->post('/login', [
    '_csrf_token' => $csrf,
    'identifier' => $email,
    'password' => $password,
    'remember' => '1',
]);
check('Login with remember-me succeeds', $rememberLogin['status'] === 302);

$rememberValue = jarValue($client->cookieJar(), 'questify_remember');
check('Remember-me cookie is issued', is_string($rememberValue) && substr_count((string) $rememberValue, ':') === 1);

$parts = is_string($rememberValue) ? explode(':', $rememberValue) : [];
$tokenRow = $pdo->prepare('SELECT * FROM remember_tokens WHERE selector = ?');
$tokenRow->execute([$parts[0] ?? '']);
$token = $tokenRow->fetch(PDO::FETCH_ASSOC);
check('Remember-me validator is stored hashed', is_array($token) && isset($parts[1]) && $token['token_hash'] === hash('sha256', $parts[1]));
check('Raw remember-me validator is not stored', is_array($token) && isset($parts[1]) && $token['token_hash'] !== $parts[1]);

$rememberOnlyJar = jarWithout($client->cookieJar(), 'questify_session');
$client->useJar($rememberOnlyJar);
$restored = $client->get('/dashboard');
check('Remember-me restores a dashboard session', $restored['status'] === 200 && contains($restored['body'], $displayName));

$rotatedValue = jarValue($client->cookieJar(), 'questify_remember');
$rotatedRow = $pdo->prepare('SELECT token_hash FROM remember_tokens WHERE selector = ?');
$rotatedRow->execute([$parts[0] ?? '']);
$rotatedHash = $rotatedRow->fetchColumn();
check('Remember-me validator rotates on use', is_string($rotatedValue) && $rotatedValue !== $rememberValue);
check('Rotated validator matches the new hash', is_string($rotatedHash) && hash('sha256', explode(':', (string) $rotatedValue)[1] ?? '') === $rotatedHash);

// Tamper with the validator and confirm the token is revoked.
$tamperedJar = jarWithout($client->cookieJar(), 'questify_session');
$tamperedLines = jarLines($tamperedJar);
foreach ($tamperedLines as $index => $line) {
    $fields = explode("\t", $line);
    if (($fields[5] ?? '') === 'questify_remember') {
        $fields[6] = preg_replace('/[a-f0-9]{64}$/', str_repeat('0', 64), $fields[6]);
        $tamperedLines[$index] = implode("\t", $fields);
    }
}
file_put_contents($tamperedJar, implode(PHP_EOL, $tamperedLines) . PHP_EOL);
$client->useJar($tamperedJar);
$tampered = $client->get('/dashboard');
check('Tampered remember-me cookie is rejected', $tampered['status'] === 302);
$revoked = $pdo->prepare('SELECT COUNT(*) FROM remember_tokens WHERE selector = ?');
$revoked->execute([$parts[0] ?? '']);
check('Tampered remember-me token is revoked', (int) $revoked->fetchColumn() === 0);

echo "\n7. Password reset\n";

$forgotPage = $client->get('/forgot-password');
$csrf = $client->csrf($forgotPage['body']);
check('Forgot-password page renders', $forgotPage['status'] === 200 && $csrf !== null);

$mailLog = (string) config('paths.storage') . '/logs/mail.log';
$beforeSize = is_file($mailLog) ? (int) filesize($mailLog) : 0;

$forgot = $client->post('/forgot-password', [
    '_csrf_token' => $csrf,
    'email' => $email,
]);
check('Reset request returns generic success', $forgot['status'] === 302 && str_ends_with($forgot['headers']['location'] ?? '', '/forgot-password'));

$mailContents = is_file($mailLog) ? (string) file_get_contents($mailLog) : '';
$newMail = substr($mailContents, $beforeSize);
preg_match('/token=([a-f0-9]{64})/', $newMail, $tokenMatch);
$resetToken = $tokenMatch[1] ?? '';
check('Reset link is written to the mail log', strlen($resetToken) === 64);

$storedReset = $pdo->prepare('SELECT * FROM password_resets WHERE user_id = ?');
$storedReset->execute([(int) $user['id']]);
$resetRow = $storedReset->fetch(PDO::FETCH_ASSOC);
check('Reset token is stored hashed', is_array($resetRow) && $resetRow['token_hash'] === hash('sha256', $resetToken));
check('Raw reset token is not stored', is_array($resetRow) && $resetRow['token_hash'] !== $resetToken);

$guest = new HttpClient($baseUrl);
$resetPage = $guest->get('/reset-password?token=' . $resetToken);
$csrf = $guest->csrf($resetPage['body']);
check('Reset page accepts a valid token', $resetPage['status'] === 200 && $csrf !== null && contains($resetPage['body'], 'Choose a new key'));

$reset = $guest->post('/reset-password', [
    '_csrf_token' => $csrf,
    'token' => $resetToken,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
]);
check('Password reset succeeds', $reset['status'] === 302 && str_ends_with($reset['headers']['location'] ?? '', '/login'));

$updated = $pdo->prepare('SELECT password_hash FROM users WHERE id = ?');
$updated->execute([(int) $user['id']]);
$updatedHash = (string) $updated->fetchColumn();
check('New password verifies', password_verify($newPassword, $updatedHash));
check('Old password no longer verifies', !password_verify($password, $updatedHash));

$reuse = $guest->get('/reset-password?token=' . $resetToken);
check('Used reset token is rejected on revisit', contains($reuse['body'], 'This link has expired'));

$loginPage = $guest->get('/login');
$csrf = $guest->csrf($loginPage['body']);
$oldLogin = $guest->post('/login', [
    '_csrf_token' => $csrf,
    'identifier' => $email,
    'password' => $password,
]);
check('Old password cannot sign in', $oldLogin['status'] === 401);

$csrf = $guest->csrf($oldLogin['body']) ?? $csrf;
$newLogin = $guest->post('/login', [
    '_csrf_token' => $csrf,
    'identifier' => $email,
    'password' => $newPassword,
]);
check('New password signs in', $newLogin['status'] === 302);

echo "\n8. Session expiration\n";

$sessionId = jarValue($guest->cookieJar(), 'questify_session');
check('Session cookie is present', is_string($sessionId) && $sessionId !== '');

if (is_string($sessionId) && $sessionId !== '') {
    $expire = $pdo->prepare('UPDATE sessions SET expires_at = ? WHERE id = ?');
    $expire->execute([gmdate('Y-m-d H:i:s', time() - 60), hash('sha256', $sessionId)]);
}

$expired = $guest->get('/dashboard');
check('Expired session is redirected to sign-in', $expired['status'] === 302 && str_ends_with($expired['headers']['location'] ?? '', '/login'));

echo "\n9. Cleanup\n";

$pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($email)]);
$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([(int) $user['id']]);
$deleted = $pdo->prepare('SELECT COUNT(*) FROM users WHERE id = ?');
$deleted->execute([(int) $user['id']]);
check('Test account cleaned up', (int) $deleted->fetchColumn() === 0);

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

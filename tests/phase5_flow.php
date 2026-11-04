<?php

declare(strict_types=1);

/**
 * Phase 5 end-to-end suite: password changes, verified email changes,
 * notification preferences, active sessions, data export, and account deletion.
 *
 * Requires the app to be served at APP_URL and the database to be migrated.
 * Usage: php tests/phase5_flow.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\NotificationService;

App::boot(BASE_PATH, false);

$baseUrl = rtrim((string) config('app.url', 'http://localhost:8000'), '/');

final class HttpClient
{
    private string $cookieFile;

    private ?string $lastSessionCookie = null;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_phase5_');
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

        return $this->finish($ch);
    }

    /**
     * @param array<string, string> $data
     * @param array<string, array{path: string, type: string, name: string}> $files
     * @return array{status: int, headers: array<string, string>, body: string, setCookie: list<string>}
     */
    public function postMultipart(string $path, array $data, array $files): array
    {
        $fields = $data;

        foreach ($files as $field => $file) {
            $fields[$field] = new CURLFile($file['path'], $file['type'], $file['name']);
        }

        $ch = curl_init($this->baseUrl . $path);

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEJAR => $this->cookieFile,
            CURLOPT_COOKIEFILE => $this->cookieFile,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $fields,
        ]);

        return $this->finish($ch);
    }

    /**
     * @param CurlHandle $ch
     * @return array{status: int, headers: array<string, string>, body: string, setCookie: list<string>}
     */
    private function finish($ch): array
    {
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

                if (preg_match('/(?:^|;\s*)questify_session=([^;]*)/', $value, $sessionMatch) === 1) {
                    $decoded = rawurldecode($sessionMatch[1]);
                    $this->lastSessionCookie = $decoded === '' ? null : $decoded;
                }
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

    public function cookie(string $name): ?string
    {
        if ($name === 'questify_session' && $this->lastSessionCookie !== null) {
            return $this->lastSessionCookie;
        }

        $lines = @file($this->cookieFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines ?: [] as $line) {
            $line = preg_replace('/^#HttpOnly_/', '', $line) ?? $line;

            if (str_starts_with($line, '#')) {
                continue;
            }

            $parts = explode("\t", $line);

            if (count($parts) >= 7 && $parts[5] === $name) {
                return $parts[6];
            }
        }

        return null;
    }
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

function registerAccount(HttpClient $client, string $displayName, string $username, string $email, string $password): array
{
    $page = $client->get('/register');
    $csrf = $client->csrf($page['body']);

    return $client->post('/register', [
        '_csrf_token' => $csrf,
        'display_name' => $displayName,
        'username' => $username,
        'email' => $email,
        'timezone' => 'Europe/London',
        'password' => $password,
        'password_confirmation' => $password,
        'terms' => '1',
    ]);
}

function loginAccount(HttpClient $client, string $identifier, string $password): array
{
    $page = $client->get('/login');

    return $client->post('/login', [
        '_csrf_token' => $client->csrf($page['body']),
        'identifier' => $identifier,
        'password' => $password,
    ]);
}

/** @return array<string, mixed>|null */
function row(PDO $pdo, string $sql, array $params = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);
    $result = $statement->fetch(PDO::FETCH_ASSOC);

    return $result === false ? null : $result;
}

function scalar(PDO $pdo, string $sql, array $params = []): int
{
    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return (int) $statement->fetchColumn();
}

function createQuest(HttpClient $client, string $title): int
{
    $page = $client->get('/quests/create');
    $response = $client->post('/quests', [
        '_csrf_token' => $client->csrf($page['body']),
        'title' => $title,
        'description' => 'Phase 5 export coverage quest for ' . $title . '.',
        'difficulty' => 'easy',
        'xp_reward' => '9999',
    ]);

    if ($response['status'] !== 302) {
        throw new RuntimeException('Quest creation failed: ' . $response['status']);
    }

    if (preg_match('#/quests/(\d+)$#', (string) ($response['headers']['location'] ?? ''), $match) !== 1) {
        throw new RuntimeException('Quest creation did not return an id.');
    }

    return (int) $match[1];
}

function completeQuest(HttpClient $client, int $questId): array
{
    $page = $client->get('/quests/' . $questId);

    return $client->post('/quests/' . $questId . '/complete', [
        '_csrf_token' => $client->csrf($page['body']),
    ]);
}

/**
 * Return the most recent confirmation token sent to an address.
 */
function latestEmailToken(string $email): ?string
{
    $log = @file_get_contents(BASE_PATH . '/storage/logs/mail.log');

    if ($log === false || $log === '') {
        return null;
    }

    $blocks = preg_split('/\n(?=\[\d{4}-)/', $log) ?: [];

    foreach (array_reverse($blocks) as $block) {
        if (!str_contains($block, '<' . $email . '>')) {
            continue;
        }

        if (preg_match('/Link: (\S+)/', $block, $match) !== 1) {
            return null;
        }

        parse_str((string) parse_url($match[1], PHP_URL_QUERY), $query);

        return isset($query['token']) && is_string($query['token']) ? $query['token'] : null;
    }

    return null;
}

/**
 * Build a valid 64x64 RGBA PNG without GD/Imagick.
 */
function makePng(int $width = 64, int $height = 64): string
{
    $raw = '';

    for ($y = 0; $y < $height; $y++) {
        $raw .= "\x00" . str_repeat("\xF8\xCC\x15\xFF", $width);
    }

    $chunk = static function (string $type, string $payload): string {
        return pack('N', strlen($payload)) . $type . $payload . pack('N', crc32($type . $payload));
    };

    $ihdr = pack('NNCCCCC', $width, $height, 8, 6, 0, 0, 0);

    return "\x89PNG\r\n\x1a\n"
        . $chunk('IHDR', $ihdr)
        . $chunk('IDAT', gzcompress($raw, 6))
        . $chunk('IEND', '');
}

/**
 * @param list<string> $identifiers
 */
function cleanupLoginAttempts(PDO $pdo, array $identifiers): void
{
    foreach ($identifiers as $identifier) {
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($identifier)]);
    }
}

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$createdUsers = [];
$identifiers = [];

$email = 'phase5_' . $suffix . '@example.test';
$newEmail = 'phase5_new_' . $suffix . '@example.test';
$otherEmail = 'phase5_other_' . $suffix . '@example.test';
$username = 'phase5_' . $suffix;
$displayName = 'Phase Five ' . $suffix;
$password = 'PhaseFive-' . $suffix . '9';
$newPassword = 'PhaseFiveNew-' . $suffix . '8';
$identifiers[] = $email;
$identifiers[] = $newEmail;
$identifiers[] = $otherEmail;

$client = new HttpClient($baseUrl);
$otherClient = new HttpClient($baseUrl);

echo "\n1. Settings shell, owner session and notification preferences\n";

$registered = registerAccount($client, $displayName, $username, $email, $password);
check(
    'Phase 5 account registered and signed in',
    $registered['status'] === 302 && str_ends_with($registered['headers']['location'] ?? '', '/dashboard'),
);

$user = row($pdo, 'SELECT * FROM users WHERE email = ?', [$email]);
$userId = (int) ($user['id'] ?? 0);
$createdUsers[] = $userId;
check('Phase 5 account exists', $userId > 0);
check(
    'Session rows now record their owner',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$userId]) >= 1,
);

$settings = $client->get('/settings');
check(
    'Settings page renders every Phase 5 section',
    $settings['status'] === 200
        && contains($settings['body'], 'data-settings-page')
        && contains($settings['body'], 'Change password')
        && contains($settings['body'], 'Email address')
        && contains($settings['body'], 'Notification preferences')
        && contains($settings['body'], 'Active sessions')
        && contains($settings['body'], 'Export account')
        && contains($settings['body'], 'Delete account'),
);
check(
    'Settings replaces the Phase 5 placeholder',
    !contains($settings['body'], 'Planned for Phase 5')
        && !contains($settings['body'], 'This module is not active yet'),
);
check(
    'Settings keeps the application shell and active navigation',
    contains($settings['body'], 'data-app-shell')
        && contains($settings['body'], 'aria-current="page"')
        && contains($settings['body'], 'Phase 5 · account controls live'),
);
check(
    'Settings labels the current device',
    contains($settings['body'], 'This device') && contains($settings['body'], 'This device is the only active session.'),
);
check(
    'Settings exposes CSRF for every mutation',
    substr_count($settings['body'], 'name="_csrf_token"') >= 5,
);

$settingsCsrf = $client->csrf($settings['body']);
check('Settings CSRF token is available', $settingsCsrf !== null);

$preferences = $client->post('/settings/notifications', [
    '_csrf_token' => $settingsCsrf,
    'notify_achievements' => '1',
]);
check('Notification preferences save redirects', $preferences['status'] === 302);

$user = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
check(
    'Unchecked preferences are stored as disabled',
    (int) $user['notify_achievements'] === 1
        && (int) $user['notify_level_ups'] === 0
        && (int) $user['notify_streaks'] === 0
        && (int) $user['notify_leaderboard'] === 0,
);

$mutedKey = 'phase5:muted-streak:' . $suffix;
$muted = NotificationService::create($pdo, [
    'user_id' => $userId,
    'type' => 'streak',
    'title' => 'Muted streak',
    'message' => 'This should not be stored.',
    'icon' => 'flame',
    'event_key' => $mutedKey,
]);
check(
    'Disabled notification types are suppressed',
    $muted === false && scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND event_key = ?', [$userId, $mutedKey]) === 0,
);

$achievementKey = 'phase5:achievement:' . $suffix;
check(
    'Enabled notification types still deliver',
    NotificationService::create($pdo, [
        'user_id' => $userId,
        'type' => 'achievement',
        'title' => 'Enabled achievement',
        'message' => 'Stored.',
        'icon' => 'trophy',
        'event_key' => $achievementKey,
    ]) === true
        && scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND event_key = ?', [$userId, $achievementKey]) === 1,
);

$securityKey = 'phase5:security:' . $suffix;
check(
    'Security notifications ignore preference mutes',
    NotificationService::create($pdo, [
        'user_id' => $userId,
        'type' => 'security',
        'title' => 'Security event',
        'message' => 'Always delivered.',
        'icon' => 'shield-check',
        'event_key' => $securityKey,
    ]) === true
        && scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND event_key = ?', [$userId, $securityKey]) === 1,
);

$settingsCsrf = $client->csrf($client->get('/settings')['body']);
$client->post('/settings/notifications', [
    '_csrf_token' => $settingsCsrf,
    'notify_achievements' => '1',
    'notify_level_ups' => '1',
    'notify_streaks' => '1',
    'notify_leaderboard' => '1',
]);
$user = row($pdo, 'SELECT notify_achievements, notify_level_ups, notify_streaks, notify_leaderboard FROM users WHERE id = ?', [$userId]);
check(
    'All notification preferences can be restored',
    (int) $user['notify_achievements'] === 1
        && (int) $user['notify_level_ups'] === 1
        && (int) $user['notify_streaks'] === 1
        && (int) $user['notify_leaderboard'] === 1,
);

echo "\n2. Verified email change\n";

$otherRegistered = registerAccount($otherClient, 'Other ' . $suffix, 'phase5_other_' . $suffix, $otherEmail, $password);
$otherUser = row($pdo, 'SELECT * FROM users WHERE email = ?', [$otherEmail]);
$otherId = (int) ($otherUser['id'] ?? 0);
$createdUsers[] = $otherId;
check('Second account is available for conflict tests', $otherRegistered['status'] === 302 && $otherId > 0);

$settingsCsrf = $client->csrf($client->get('/settings')['body']);

$badEmail = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => 'not-an-email',
    'current_password' => $password,
]);
check(
    'Malformed email is rejected inline',
    $badEmail['status'] === 422 && contains($badEmail['body'], 'email_new-error'),
);

$sameEmail = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $email,
    'current_password' => $password,
]);
check(
    'Changing to the current email is rejected',
    $sameEmail['status'] === 422 && contains($sameEmail['body'], 'already your email address'),
);

$duplicate = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $otherEmail,
    'current_password' => $password,
]);
check(
    'Changing to a registered email is rejected',
    $duplicate['status'] === 422 && contains($duplicate['body'], 'already registered'),
);

$wrongPassword = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $newEmail,
    'current_password' => 'definitely-wrong',
]);
check(
    'Email change requires the current password',
    $wrongPassword['status'] === 422 && contains($wrongPassword['body'], 'not your current password'),
);

$requested = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $newEmail,
    'current_password' => $password,
]);
check(
    'Valid email change request redirects',
    $requested['status'] === 302 && str_ends_with($requested['headers']['location'] ?? '', '/settings'),
);

$pending = row($pdo, 'SELECT * FROM email_change_requests WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
check(
    'Email change request is persisted with a hashed token',
    is_array($pending)
        && $pending['new_email'] === $newEmail
        && strlen((string) $pending['token_hash']) === 64
        && strtotime((string) $pending['expires_at']) > time(),
);
check(
    'Email change token is not stored in plain text',
    is_array($pending) && !contains(json_encode($pending, JSON_THROW_ON_ERROR), 'Link:'),
);

$token = latestEmailToken($newEmail);
check('Confirmation email is written to the local mail log', $token !== null && strlen($token) === 64);
check(
    'Pending email change is shown in Settings',
    contains($client->get('/settings')['body'], $newEmail),
);

$confirmPage = $client->get('/settings/email/confirm?token=' . $token);
check(
    'Confirmation page renders the address transition',
    $confirmPage['status'] === 200
        && contains($confirmPage['body'], $newEmail)
        && contains($confirmPage['body'], 'Confirm email change')
        && contains($confirmPage['body'], 'value="' . $token . '"'),
);

$confirmCsrf = $client->csrf($confirmPage['body']);
$invalidConfirm = $client->post('/settings/email/confirm', [
    '_csrf_token' => $confirmCsrf,
    'token' => str_repeat('0', 64),
]);
check('Invalid confirmation token is rejected', $invalidConfirm['status'] === 302 && str_ends_with($invalidConfirm['headers']['location'] ?? '', '/settings'));

$confirmed = $client->post('/settings/email/confirm', [
    '_csrf_token' => $confirmCsrf,
    'token' => $token,
]);
check('Valid confirmation token completes the change', $confirmed['status'] === 302);

$user = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
check(
    'New email is stored and verified',
    $user['email'] === $newEmail && $user['email_verified_at'] !== null,
);
check(
    'Confirmed requests are purged and a security notification is created',
    scalar($pdo, 'SELECT COUNT(*) FROM email_change_requests WHERE user_id = ?', [$userId]) === 0
        && scalar($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'security'", [$userId]) >= 1,
);

$replay = $client->post('/settings/email/confirm', [
    '_csrf_token' => $confirmCsrf,
    'token' => $token,
]);
$replayPage = $client->get('/settings');
check(
    'Confirmation tokens are single-use',
    $replay['status'] === 302
        && contains($replayPage['body'], 'invalid or has expired')
        && row($pdo, 'SELECT email FROM users WHERE id = ?', [$userId])['email'] === $newEmail,
);

$oldLoginClient = new HttpClient($baseUrl);
$oldLogin = loginAccount($oldLoginClient, $email, $password);
check('Old email can no longer sign in', $oldLogin['status'] === 401);

$newLoginClient = new HttpClient($baseUrl);
$newLogin = loginAccount($newLoginClient, $newEmail, $password);
check(
    'New email signs in successfully',
    $newLogin['status'] === 302 && str_ends_with($newLogin['headers']['location'] ?? '', '/dashboard'),
);

echo "\n3. Password change and credential revocation\n";

$secondClient = new HttpClient($baseUrl);
$secondLogin = loginAccount($secondClient, $newEmail, $password);
check('A second session signs in for revocation coverage', $secondLogin['status'] === 302);
$secondDashboard = $secondClient->get('/dashboard');
check('Second session is authenticated', $secondDashboard['status'] === 200);
check(
    'Two owned sessions are listed',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$userId]) >= 2,
);

$pdo->prepare(
    "INSERT INTO remember_tokens (user_id, selector, token_hash, ip_address, user_agent, expires_at, created_at)
     VALUES (?, ?, ?, '127.0.0.1', 'Phase 5 test', ?, UTC_TIMESTAMP())",
)->execute([
    $userId,
    bin2hex(random_bytes(16)),
    hash('sha256', bin2hex(random_bytes(32))),
    gmdate('Y-m-d H:i:s', time() + 3600),
]);
check('A remember-me token exists before the password change', scalar($pdo, 'SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?', [$userId]) >= 1);

$settingsCsrf = $client->csrf($client->get('/settings')['body']);
$oldHash = (string) row($pdo, 'SELECT password_hash FROM users WHERE id = ?', [$userId])['password_hash'];

$wrongCurrent = $client->post('/settings/password', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => 'wrong-password',
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
]);
check(
    'Password change requires the current password',
    $wrongCurrent['status'] === 422 && contains($wrongCurrent['body'], 'password_current-error'),
);

$weak = $client->post('/settings/password', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => $password,
    'password' => 'short',
    'password_confirmation' => 'short',
]);
check('Password change enforces the minimum length', $weak['status'] === 422);

$reused = $client->post('/settings/password', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => $password,
    'password' => $password,
    'password_confirmation' => $password,
]);
check(
    'Password change refuses the current password',
    $reused['status'] === 422 && contains($reused['body'], 'different from your current one'),
);

$beforeChange = (string) row($pdo, 'SELECT password_hash FROM users WHERE id = ?', [$userId])['password_hash'];
check('Failed password attempts leave the hash unchanged', hash_equals($oldHash, $beforeChange));

$changed = $client->post('/settings/password', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => $password,
    'password' => $newPassword,
    'password_confirmation' => $newPassword,
]);
check('Valid password change redirects', $changed['status'] === 302);

$user = row($pdo, 'SELECT password_hash FROM users WHERE id = ?', [$userId]);
check(
    'New password is stored and the old one no longer verifies',
    password_verify($newPassword, (string) $user['password_hash']) && !password_verify($password, (string) $user['password_hash']),
);
check(
    'Password change revokes remember-me tokens',
    scalar($pdo, 'SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?', [$userId]) === 0,
);
check(
    'Password change revokes other sessions but keeps the current one',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$userId]) === 1,
);

$secondAfterChange = $secondClient->get('/dashboard');
check(
    'Revoked session is signed out',
    $secondAfterChange['status'] === 302 && str_ends_with($secondAfterChange['headers']['location'] ?? '', '/login'),
);
check('Current session survives the password change', $client->get('/settings')['status'] === 200);
check(
    'Password change creates a security notification',
    scalar($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'security'", [$userId]) >= 2,
);

$wrongLoginClient = new HttpClient($baseUrl);
$wrongLogin = loginAccount($wrongLoginClient, $newEmail, $password);
check('Old password can no longer sign in', $wrongLogin['status'] === 401);

$newPasswordClient = new HttpClient($baseUrl);
$newPasswordLogin = loginAccount($newPasswordClient, $newEmail, $newPassword);
check('New password signs in successfully', $newPasswordLogin['status'] === 302);

echo "\n4. Active session ownership and revocation\n";

$thirdClient = new HttpClient($baseUrl);
$thirdLogin = loginAccount($thirdClient, $newEmail, $newPassword);
check('A third session signs in', $thirdLogin['status'] === 302 && $thirdClient->get('/dashboard')['status'] === 200);

$currentSessionCookie = $client->cookie('questify_session');
$currentSessionId = hash('sha256', (string) $currentSessionCookie);
check('Current session cookie is readable', is_string($currentSessionCookie) && $currentSessionCookie !== '');
$otherSession = row(
    $pdo,
    'SELECT id FROM sessions WHERE user_id = ? AND id <> ? ORDER BY last_activity DESC LIMIT 1',
    [$userId, $currentSessionId],
);
check('Non-current session is discoverable', is_array($otherSession));

$settingsCsrf = $client->csrf($client->get('/settings')['body']);
$revoked = $client->post('/settings/sessions/' . (string) ($otherSession['id'] ?? '') . '/revoke', [
    '_csrf_token' => $settingsCsrf,
]);
check('Revoking another owned session redirects', $revoked['status'] === 302);
check(
    'Revoked session row is deleted',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE id = ?', [(string) ($otherSession['id'] ?? '')]) === 0,
);

$otherUserSession = row($pdo, 'SELECT id FROM sessions WHERE user_id = ? ORDER BY last_activity DESC LIMIT 1', [$otherId]);
$crossUser = $client->post('/settings/sessions/' . (string) ($otherUserSession['id'] ?? '') . '/revoke', [
    '_csrf_token' => $settingsCsrf,
]);
check(
    'Sessions cannot be revoked across accounts',
    $crossUser['status'] === 302
        && scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE id = ?', [(string) ($otherUserSession['id'] ?? '')]) === 1,
);

$selfRevoke = $client->post('/settings/sessions/' . $currentSessionId . '/revoke', [
    '_csrf_token' => $settingsCsrf,
]);
$selfPage = $client->get('/settings');
check(
    'The current session cannot revoke itself',
    $selfRevoke['status'] === 302
        && contains($selfPage['body'], 'cannot revoke the session you are currently using')
        && $client->get('/settings')['status'] === 200,
    'status=' . $selfRevoke['status']
        . ' msg=' . (contains($selfPage['body'], 'cannot revoke the session you are currently using') ? 'yes' : 'no')
        . ' expired=' . (contains($selfPage['body'], 'no longer active') ? 'yes' : 'no')
        . ' session=' . substr($currentSessionId, 0, 12),
);

$fourthClient = new HttpClient($baseUrl);
$fourthLogin = loginAccount($fourthClient, $newEmail, $newPassword);
check('A fourth session signs in for revoke-others coverage', $fourthLogin['status'] === 302 && $fourthClient->get('/dashboard')['status'] === 200);

$settingsCsrf = $client->csrf($client->get('/settings')['body']);
$revokeOthers = $client->post('/settings/sessions/revoke-others', ['_csrf_token' => $settingsCsrf]);
check('Revoke-all-others redirects', $revokeOthers['status'] === 302);
check(
    'Revoke-all-others keeps exactly the current session',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$userId]) === 1,
);
$fourthAfter = $fourthClient->get('/dashboard');
check(
    'Other sessions are signed out after revoke-all',
    $fourthAfter['status'] === 302 && str_ends_with($fourthAfter['headers']['location'] ?? '', '/login'),
);
check('Current session still works after revoke-all', $client->get('/settings')['status'] === 200);

echo "\n5. Email change rate limiting and superseded links\n";

$firstRateEmail = 'phase5_rate1_' . $suffix . '@example.test';
$secondRateEmail = 'phase5_rate2_' . $suffix . '@example.test';
$thirdRateEmail = 'phase5_rate3_' . $suffix . '@example.test';
$fourthRateEmail = 'phase5_rate4_' . $suffix . '@example.test';
$identifiers[] = $firstRateEmail;
$identifiers[] = $secondRateEmail;
$identifiers[] = $thirdRateEmail;
$identifiers[] = $fourthRateEmail;

$settingsCsrf = $client->csrf($client->get('/settings')['body']);
$firstRate = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $firstRateEmail,
    'current_password' => $newPassword,
]);
$firstRateToken = latestEmailToken($firstRateEmail);
check('First rate-limit email request is accepted', $firstRate['status'] === 302 && $firstRateToken !== null);

$secondRate = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $secondRateEmail,
    'current_password' => $newPassword,
]);
$secondRateToken = latestEmailToken($secondRateEmail);
check('Second rate-limit email request is accepted', $secondRate['status'] === 302 && $secondRateToken !== null);

$supersededPage = $client->get('/settings/email/confirm?token=' . $firstRateToken);
check(
    'Superseded confirmation links are not honoured',
    $supersededPage['status'] === 302
        && str_ends_with($supersededPage['headers']['location'] ?? '', '/settings'),
);
check(
    'Superseded link does not change the email address',
    row($pdo, 'SELECT email FROM users WHERE id = ?', [$userId])['email'] === $newEmail,
);

$thirdRate = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $thirdRateEmail,
    'current_password' => $newPassword,
]);
check('Third rate-limit email request is accepted', $thirdRate['status'] === 302);

$fourthRate = $client->post('/settings/email', [
    '_csrf_token' => $settingsCsrf,
    'new_email' => $fourthRateEmail,
    'current_password' => $newPassword,
]);
check(
    'Fourth request within the hour is rate limited',
    $fourthRate['status'] === 422 && contains($fourthRate['body'], 'Too many email change requests'),
);
check(
    'Rate limiting counts pending requests',
    scalar($pdo, 'SELECT COUNT(*) FROM email_change_requests WHERE user_id = ?', [$userId]) === 3,
);

echo "\n6. Account export\n";

$questId = createQuest($client, 'Phase 5 export quest');
check('Export fixture quest is created', $questId > 0);
check('Export fixture quest completes', completeQuest($client, $questId)['status'] === 302);

$settingsCsrf = $client->csrf($client->get('/settings')['body']);
$noCsrf = $client->post('/settings/export');
check('Export requires a valid CSRF token', $noCsrf['status'] === 419);

$getExport = $client->get('/settings/export');
check('Export only accepts POST', $getExport['status'] === 405);

$export = $client->post('/settings/export', ['_csrf_token' => $settingsCsrf]);
$exportData = json_decode($export['body'], true);
check(
    'Export returns a downloadable JSON attachment',
    $export['status'] === 200
        && str_contains(strtolower((string) ($export['headers']['content-type'] ?? '')), 'application/json')
        && str_contains(strtolower((string) ($export['headers']['content-disposition'] ?? '')), 'attachment')
        && is_array($exportData),
);
check(
    'Export is scoped to the owner',
    is_array($exportData)
        && ($exportData['account']['email'] ?? null) === $newEmail
        && ($exportData['account']['username'] ?? null) === $username,
);
check(
    'Export includes quests, XP, achievements, streaks, notifications and sessions',
    is_array($exportData)
        && isset($exportData['quests'], $exportData['xp_transactions'], $exportData['achievements'], $exportData['streak_activity'], $exportData['notifications'], $exportData['sessions'])
        && count($exportData['quests']) >= 1
        && count($exportData['xp_transactions']) >= 1,
);
$exportJson = (string) $export['body'];
check(
    'Export never includes credentials or secrets',
    !contains($exportJson, 'password_hash')
        && !contains($exportJson, 'token_hash')
        && !contains($exportJson, 'selector')
        && !contains($exportJson, '"payload"')
        && !contains($exportJson, 'reset_token'),
);
check(
    'Export session metadata excludes the hashed session id',
    is_array($exportData)
        && !contains((string) json_encode($exportData['sessions'] ?? [], JSON_THROW_ON_ERROR), $currentSessionId),
);

echo "\n7. Account deletion\n";

$png = makePng();
$pngFile = (string) tempnam(sys_get_temp_dir(), 'questify_phase5_avatar_') . '.png';
file_put_contents($pngFile, $png);

$profilePage = $client->get('/profile');
$avatar = $client->postMultipart('/profile/avatar', [
    '_csrf_token' => $client->csrf($profilePage['body']),
], [
    'avatar' => ['path' => $pngFile, 'type' => 'image/png', 'name' => 'phase5.png'],
]);
$avatarPath = (string) (row($pdo, 'SELECT avatar_path FROM users WHERE id = ?', [$userId])['avatar_path'] ?? '');
$avatarDiskPath = (string) config('paths.public', '') . '/' . ltrim($avatarPath, '/');
check(
    'Deletion fixture avatar is uploaded',
    $avatar['status'] === 302 && $avatarPath !== '' && is_file($avatarDiskPath),
);

$settingsCsrf = $client->csrf($client->get('/settings')['body']);

$wrongDeletePassword = $client->post('/settings/delete', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => 'wrong-password',
    'confirmation' => 'DELETE MY ACCOUNT',
]);
check(
    'Deletion requires the current password',
    $wrongDeletePassword['status'] === 422
        && contains($wrongDeletePassword['body'], 'delete_current-error')
        && scalar($pdo, 'SELECT COUNT(*) FROM users WHERE id = ?', [$userId]) === 1,
);

$wrongPhrase = $client->post('/settings/delete', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => $newPassword,
    'confirmation' => 'delete my account',
]);
check(
    'Deletion requires the exact confirmation phrase',
    $wrongPhrase['status'] === 422
        && contains($wrongPhrase['body'], 'Type DELETE MY ACCOUNT exactly')
        && scalar($pdo, 'SELECT COUNT(*) FROM users WHERE id = ?', [$userId]) === 1,
);

$noDeleteCsrf = $client->post('/settings/delete', [
    'current_password' => $newPassword,
    'confirmation' => 'DELETE MY ACCOUNT',
]);
check('Deletion requires a valid CSRF token', $noDeleteCsrf['status'] === 419);

$deleted = $client->post('/settings/delete', [
    '_csrf_token' => $settingsCsrf,
    'current_password' => $newPassword,
    'confirmation' => 'DELETE MY ACCOUNT',
]);
check(
    'Valid deletion redirects to sign-in',
    $deleted['status'] === 302 && str_ends_with($deleted['headers']['location'] ?? '', '/login'),
);
check('Account row is deleted', scalar($pdo, 'SELECT COUNT(*) FROM users WHERE id = ?', [$userId]) === 0);

foreach (['quests', 'xp_transactions', 'user_achievements', 'streak_activity', 'notifications', 'sessions', 'remember_tokens', 'email_change_requests'] as $table) {
    check(
        'Account deletion cascades ' . $table,
        scalar($pdo, 'SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = ?', [$userId]) === 0,
    );
}

clearstatcache(true, $avatarDiskPath);
check('Account deletion removes the uploaded avatar', !is_file($avatarDiskPath), $avatarDiskPath);

$afterDelete = $client->get('/settings');
check(
    'Deleted account is signed out',
    $afterDelete['status'] === 302 && str_ends_with($afterDelete['headers']['location'] ?? '', '/login'),
);
check(
    'Deleted account cannot sign in',
    loginAccount(new HttpClient($baseUrl), $newEmail, $newPassword)['status'] === 401,
);

echo "\n8. Cleanup\n";

$placeholders = implode(', ', array_fill(0, count($createdUsers), '?'));
$pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($createdUsers);
cleanupLoginAttempts($pdo, $identifiers);

check(
    'Phase 5 accounts and dependent rows are cleaned up',
    scalar($pdo, "SELECT COUNT(*) FROM users WHERE id IN ({$placeholders})", $createdUsers) === 0,
);

@unlink($pngFile);

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

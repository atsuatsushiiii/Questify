<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Public profile regression suite.
 *
 * Requires the app to be served at APP_URL and the database to be migrated.
 * Usage: php tests/public_profile_flow.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Support\Progression;

App::boot(BASE_PATH, false);

$baseUrl = rtrim((string) config('app.url', 'http://localhost:8000'), '/');

final class HttpClient
{
    private string $cookieFile;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_profile_');
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

/** @return array<string, mixed>|null */
function row(PDO $pdo, string $sql, array $parameters = []): ?array
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function scalar(PDO $pdo, string $sql, array $parameters = []): mixed
{
    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);

    return $statement->fetchColumn();
}

function registerAccount(
    HttpClient $client,
    string $displayName,
    string $username,
    string $email,
    string $password,
): array {
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

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$password = 'Questify!Profile1';
$ownerClient = new HttpClient($baseUrl);
$viewerClient = new HttpClient($baseUrl);
$guestClient = new HttpClient($baseUrl);
$createdUsers = [];

$ownerName = 'Aria the Brave ' . $suffix;
$ownerUsername = 'aria_' . $suffix;
$ownerEmail = 'aria_' . $suffix . '@example.test';
$viewerName = 'Borin the Quiet ' . $suffix;
$viewerUsername = 'borin_' . $suffix;
$viewerEmail = 'borin_' . $suffix . '@example.test';

try {
    echo "1. Public profile rendering\n";

    $registration = registerAccount($ownerClient, $ownerName, $ownerUsername, $ownerEmail, $password);
    check('Owner account registers', $registration['status'] === 302);
    $ownerId = (int) scalar($pdo, 'SELECT id FROM users WHERE username = ?', [$ownerUsername]);
    $createdUsers[] = $ownerId;
    check('Owner account exists', $ownerId > 0);

    $viewerRegistration = registerAccount($viewerClient, $viewerName, $viewerUsername, $viewerEmail, $password);
    check('Viewer account registers', $viewerRegistration['status'] === 302);
    $viewerId = (int) scalar($pdo, 'SELECT id FROM users WHERE username = ?', [$viewerUsername]);
    $createdUsers[] = $viewerId;

    $xp = 600;
    $level = Progression::levelForXp($xp);
    $pdo->prepare(
        'UPDATE users
         SET xp_total = ?, level = ?, current_streak = 4, longest_streak = 9,
             last_streak_date = UTC_DATE(), show_profile = 1, show_on_leaderboard = 1
         WHERE id = ?',
    )->execute([$xp, $level, $ownerId]);

    $quest = $pdo->prepare(
        "INSERT INTO quests (user_id, title, description, difficulty, xp_reward, status, completed_at, created_at, updated_at)
         VALUES (?, ?, '', 'easy', 25, 'completed', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
    );

    for ($index = 0; $index < 3; $index++) {
        $quest->execute([$ownerId, 'Completed quest ' . ($index + 1)]);
    }

    $achievement = row($pdo, 'SELECT id, name FROM achievements ORDER BY id ASC LIMIT 1');
    check('Achievement catalogue is seeded', $achievement !== null);

    if ($achievement !== null) {
        $pdo->prepare(
            'INSERT INTO user_achievements (user_id, achievement_id, unlocked_at)
             VALUES (?, ?, UTC_TIMESTAMP())',
        )->execute([$ownerId, (int) $achievement['id']]);
    }

    $profileUrl = '/u/' . rawurlencode($ownerUsername);
    $public = $guestClient->get($profileUrl);
    check('Guest can open a public profile', $public['status'] === 200);

    $body = $public['body'];
    check('Public profile shows the display name', contains($body, $ownerName));
    check('Public profile shows the username', contains($body, '@' . $ownerUsername));
    check('Public profile shows the level', contains($body, 'Level ' . $level));
    check('Public profile shows lifetime XP', contains($body, number_format($xp)));
    check('Public profile shows completed quests', contains($body, 'Quests completed') && contains($body, '>3<'));
    check('Public profile shows the current streak', contains($body, '4d'));
    check('Public profile shows the longest streak', contains($body, '9d'));
    check('Public profile shows member since', contains($body, 'Member since ' . gmdate('F Y')));
    check(
        'Public profile shows unlocked achievements',
        $achievement !== null && contains($body, (string) $achievement['name']),
    );
    check('Public profile never contains the email', !contains($body, $ownerEmail));
    check('Public profile never contains the timezone', !contains($body, 'Europe/London'));
    check('Public profile marks itself as public', contains($body, 'Public profile'));
    check('Public profile uses the public layout', contains($body, 'Start your own quest log'));

    $unknown = $guestClient->get('/u/definitely_missing_' . $suffix);
    check('Unknown usernames return 404', $unknown['status'] === 404);
    check('Unknown usernames render the error page', contains($unknown['body'], 'Page not found'));
    check('Extra path segments return 404', $guestClient->get($profileUrl . '/extra')['status'] === 404);
    check('An empty username returns 404', $guestClient->get('/u/')['status'] === 404);

    echo "\n2. Leaderboard links\n";

    $leaderboard = $viewerClient->get('/leaderboard');
    check('Leaderboard renders for an authenticated viewer', $leaderboard['status'] === 200);
    check('Public profiles are linked from the leaderboard', contains($leaderboard['body'], $profileUrl));

    $pdo->prepare('UPDATE users SET show_profile = 0 WHERE id = ?')->execute([$ownerId]);
    $hiddenBoard = $viewerClient->get('/leaderboard');
    check('Hidden profiles are not linked from the leaderboard', !contains($hiddenBoard['body'], $profileUrl));
    check('Hidden profiles can still appear on the leaderboard', contains($hiddenBoard['body'], $ownerName));

    $pdo->prepare('UPDATE users SET show_profile = 1 WHERE id = ?')->execute([$ownerId]);

    echo "\n3. Privacy controls\n";

    $profilePage = $ownerClient->get('/profile');
    check('Owner profile page renders the public toggle', $profilePage['status'] === 200 && contains($profilePage['body'], 'Publish my public profile'));
    check('Owner profile page links to the public page', contains($profilePage['body'], $profileUrl));

    $csrf = $ownerClient->csrf($profilePage['body']);
    $hidden = $ownerClient->post('/profile', [
        '_csrf_token' => $csrf,
        'display_name' => $ownerName,
        'timezone' => 'Europe/London',
        'show_on_leaderboard' => '1',
    ]);
    check('Removing the public toggle redirects', $hidden['status'] === 302);
    check(
        'Removing the public toggle persists the hidden flag',
        (int) scalar($pdo, 'SELECT show_profile FROM users WHERE id = ?', [$ownerId]) === 0,
    );

    $hiddenPage = $guestClient->get($profileUrl);
    check('Hidden profiles return 404 to guests', $hiddenPage['status'] === 404);
    check('Hidden profile responses do not leak the display name', !contains($hiddenPage['body'], $ownerName));

    $hiddenOwnerPage = $ownerClient->get('/profile');
    check('Owner profile page reflects the hidden state', contains($hiddenOwnerPage['body'], 'Profile hidden'));
    check('Hidden owner page stops linking to the public page', !contains($hiddenOwnerPage['body'], 'View my public profile'));

    $csrf = $ownerClient->csrf($hiddenOwnerPage['body']);
    $shown = $ownerClient->post('/profile', [
        '_csrf_token' => $csrf,
        'display_name' => $ownerName,
        'timezone' => 'Europe/London',
        'show_on_leaderboard' => '1',
        'show_profile' => '1',
    ]);
    check('Re-enabling the public toggle redirects', $shown['status'] === 302);
    check('Re-enabled profiles resolve again', $guestClient->get($profileUrl)['status'] === 200);

    $pdo->prepare("UPDATE users SET status = 'suspended' WHERE id = ?")->execute([$ownerId]);
    check('Suspended profiles return 404', $guestClient->get($profileUrl)['status'] === 404);
    check(
        'Suspension is enforced even when the profile flag is on',
        (int) scalar($pdo, 'SELECT show_profile FROM users WHERE id = ?', [$ownerId]) === 1,
    );
    $pdo->prepare("UPDATE users SET status = 'active' WHERE id = ?")->execute([$ownerId]);

    echo "\n4. Cleanup\n";

    $placeholders = implode(', ', array_fill(0, count($createdUsers), '?'));
    $pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($createdUsers);

    foreach ([$ownerEmail, $viewerEmail] as $email) {
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($email)]);
    }

    check(
        'Public profile accounts and dependent rows are cleaned up',
        (int) scalar($pdo, "SELECT COUNT(*) FROM users WHERE id IN ({$placeholders})", $createdUsers) === 0,
    );
} catch (Throwable $exception) {
    if ($createdUsers !== []) {
        $placeholders = implode(', ', array_fill(0, count($createdUsers), '?'));
        $pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($createdUsers);
    }

    throw $exception;
}

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

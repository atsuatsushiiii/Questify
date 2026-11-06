<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Phase 4 end-to-end suite: achievements, streaks, profiles, notifications and
 * leaderboards.
 *
 * Requires the app to be served at APP_URL and the database to be migrated.
 * Usage: php tests/phase4_flow.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Services\AchievementService;
use App\Services\StreakService;

App::boot(BASE_PATH, false);

$baseUrl = rtrim((string) config('app.url', 'http://localhost:8000'), '/');

final class HttpClient
{
    private string $cookieFile;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_phase4_');
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

function createQuest(HttpClient $client, string $title, string $difficulty = 'easy'): int
{
    $page = $client->get('/quests/create');
    $response = $client->post('/quests', [
        '_csrf_token' => $client->csrf($page['body']),
        'title' => $title,
        'description' => 'Phase 4 coverage quest for ' . $title . '.',
        'difficulty' => $difficulty,
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

function questAction(HttpClient $client, int $questId, string $action): array
{
    $page = $client->get('/quests/' . $questId);

    return $client->post('/quests/' . $questId . '/' . $action, [
        '_csrf_token' => $client->csrf($page['body']),
    ]);
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

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$createdUsers = [];
$avatarFiles = [];

$email = 'phase4_' . $suffix . '@example.test';
$username = 'phase4_' . $suffix;
$password = 'PhaseFour-' . $suffix . '7';
$displayName = 'Phase Four ' . $suffix;

$client = new HttpClient($baseUrl);

echo "\n1. Account, achievements, streak and notification side effects\n";

$registered = registerAccount($client, $displayName, $username, $email, $password);
check(
    'Phase 4 account registered and signed in',
    $registered['status'] === 302 && str_ends_with($registered['headers']['location'] ?? '', '/dashboard'),
);

$user = row($pdo, 'SELECT * FROM users WHERE email = ?', [$email]);
$userId = (int) ($user['id'] ?? 0);
$createdUsers[] = $userId;
check('Phase 4 account exists', $userId > 0);

$dashboard = $client->get('/dashboard');
check('Dashboard renders the live trophy hall', contains($dashboard['body'], 'Achievements') && contains($dashboard['body'], 'of 10 unlocked'));
check('Dashboard renders the live leaderboard panel', contains($dashboard['body'], 'Leaderboard') && contains($dashboard['body'], 'public'));

$questId = createQuest($client, 'First Phase 4 quest');
$completed = questAction($client, $questId, 'complete');
check('Quest completion still succeeds', $completed['status'] === 302);

$user = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
$localDate = StreakService::localDate(null, (string) $user['timezone']);
check('Completion starts the local-day streak', (int) $user['current_streak'] === 1 && (int) $user['longest_streak'] === 1, json_encode($user));
check('Local streak date is stored', (string) $user['last_streak_date'] === $localDate, (string) $user['last_streak_date'] . ' vs ' . $localDate);

$activityCount = scalar($pdo, 'SELECT COUNT(*) FROM streak_activity WHERE user_id = ?', [$userId]);
check('Exactly one streak day is recorded', $activityCount === 1);
check(
    'Streak day is the user local date',
    scalar($pdo, 'SELECT COUNT(*) FROM streak_activity WHERE user_id = ? AND activity_date = ?', [$userId, $localDate]) === 1,
);

$unlocked = row(
    $pdo,
    'SELECT a.achievement_key FROM user_achievements ua INNER JOIN achievements a ON a.id = ua.achievement_id WHERE ua.user_id = ?',
    [$userId],
);
check('First quest unlocks First Steps', ($unlocked['achievement_key'] ?? null) === 'first_steps');

$achievementNotifications = scalar(
    $pdo,
    "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'achievement'",
    [$userId],
);
check('Achievement unlock creates one notification', $achievementNotifications === 1);
check(
    'Achievement notification is deduplicated by event key',
    scalar($pdo, "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND event_key = 'achievement:first_steps'", [$userId]) === 1,
);

echo "\n2. Idempotency, duplicate completions and archived quests\n";

$duplicate = questAction($client, $questId, 'complete');
check('Duplicate completion is refused', $duplicate['status'] === 302);
check('Duplicate completion does not add streak days', scalar($pdo, 'SELECT COUNT(*) FROM streak_activity WHERE user_id = ?', [$userId]) === 1);
check('Duplicate completion does not duplicate achievements', scalar($pdo, 'SELECT COUNT(*) FROM user_achievements WHERE user_id = ?', [$userId]) === 1);
check('Duplicate completion does not duplicate notifications', scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ?', [$userId]) === $achievementNotifications);

$reevaluated = AchievementService::evaluate($pdo, $userId, [
    'xp_total' => (int) $user['xp_total'],
    'level' => (int) $user['level'],
    'longest_streak' => (int) $user['longest_streak'],
]);
check('Re-evaluating achievements is idempotent', $reevaluated === []);

$secondQuest = createQuest($client, 'Second Phase 4 quest');
$secondComplete = questAction($client, $secondQuest, 'complete');
check('A second quest completes on the same day', $secondComplete['status'] === 302);
check('Same-day completions still record one streak day', scalar($pdo, 'SELECT COUNT(*) FROM streak_activity WHERE user_id = ?', [$userId]) === 1);

$thirdQuest = createQuest($client, 'Archived Phase 4 quest');
check('Quest can be archived', questAction($client, $thirdQuest, 'archive')['status'] === 302);
check('Quest can be restored', questAction($client, $thirdQuest, 'restore')['status'] === 302);
check('Archive and restore do not create streak activity', scalar($pdo, 'SELECT COUNT(*) FROM streak_activity WHERE user_id = ?', [$userId]) === 1);

$restoredComplete = questAction($client, $thirdQuest, 'complete');
check('Restored quest can then be completed', $restoredComplete['status'] === 302);
check('Restored quest completion keeps one streak day', scalar($pdo, 'SELECT COUNT(*) FROM streak_activity WHERE user_id = ?', [$userId]) === 1);
check('Quest count rewards were paid exactly once', scalar($pdo, 'SELECT COUNT(*) FROM xp_transactions WHERE user_id = ?', [$userId]) === 3);

echo "\n3. Profile editing, validation and avatar handling\n";

$profile = $client->get('/profile');
check('Profile page renders the owner editor', $profile['status'] === 200 && contains($profile['body'], 'My profile'));

$csrf = $client->csrf($profile['body']);
$newDisplayName = 'Renamed ' . $suffix;
$updated = $client->post('/profile', [
    '_csrf_token' => $csrf,
    'display_name' => $newDisplayName,
    'timezone' => 'America/New_York',
    'show_on_leaderboard' => '1',
    'show_profile' => '1',
]);
check('Valid profile update redirects', $updated['status'] === 302 && str_ends_with($updated['headers']['location'] ?? '', '/profile'));

$user = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
check('Display name is saved', (string) $user['display_name'] === $newDisplayName);
check('Timezone is saved', (string) $user['timezone'] === 'America/New_York');
check('Visibility is saved', (int) $user['show_on_leaderboard'] === 1);

$invalidProfile = $client->post('/profile', [
    '_csrf_token' => $csrf,
    'display_name' => 'A',
    'timezone' => 'Not/AZone',
]);
check('Invalid profile input returns 422', $invalidProfile['status'] === 422);
check(
    'Invalid profile input shows field errors',
    contains($invalidProfile['body'], 'at least 2 characters')
        && contains($invalidProfile['body'], 'Choose a recognised timezone'),
);

$hiddenProfile = $client->post('/profile', [
    '_csrf_token' => $csrf,
    'display_name' => $newDisplayName,
    'timezone' => 'America/New_York',
    'show_profile' => '1',
]);
check('Profile can be hidden from leaderboards', $hiddenProfile['status'] === 302);
check('Hidden visibility is persisted', (int) row($pdo, 'SELECT show_on_leaderboard FROM users WHERE id = ?', [$userId])['show_on_leaderboard'] === 0);
check('Hidden state renders on profile', contains($client->get('/profile')['body'], 'eye-off') && contains($client->get('/profile')['body'], 'Leaderboard hidden'));

$visibleProfile = $client->post('/profile', [
    '_csrf_token' => $csrf,
    'display_name' => $newDisplayName,
    'timezone' => 'America/New_York',
    'show_on_leaderboard' => '1',
    'show_profile' => '1',
]);
check('Profile can be public again', $visibleProfile['status'] === 302);

$pngPath = tempnam(sys_get_temp_dir(), 'questify_avatar_');
file_put_contents($pngPath, makePng());
$upload = $client->postMultipart('/profile/avatar', ['_csrf_token' => $csrf], [
    'avatar' => ['path' => $pngPath, 'type' => 'image/png', 'name' => 'shell.php'],
]);
check('Valid avatar upload redirects', $upload['status'] === 302);

$user = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
$avatarPath = (string) ($user['avatar_path'] ?? '');
check(
    'Avatar filename is server-generated with the real extension',
    preg_match('#^uploads/avatars/user-' . $userId . '-[a-f0-9]{16}\.png$#', $avatarPath) === 1,
    $avatarPath,
);
$absoluteAvatar = (string) config('paths.public', '') . '/' . $avatarPath;
check('Avatar file exists on disk', $avatarPath !== '' && is_file($absoluteAvatar));
$avatarFiles[] = $absoluteAvatar;
check('Profile page shows the uploaded avatar', contains($client->get('/profile')['body'], '/uploads/avatars/'));

$beforeRejected = $avatarPath;
$evilPath = tempnam(sys_get_temp_dir(), 'questify_evil_');
file_put_contents($evilPath, "<?php echo 'pwned'; ?>\n");
$rejected = $client->postMultipart('/profile/avatar', ['_csrf_token' => $csrf], [
    'avatar' => ['path' => $evilPath, 'type' => 'application/x-php', 'name' => 'evil.png'],
]);
check('Executable upload is rejected', $rejected['status'] === 302);
$user = row($pdo, 'SELECT avatar_path FROM users WHERE id = ?', [$userId]);
check('Rejected upload leaves the avatar unchanged', (string) $user['avatar_path'] === $beforeRejected);

$removed = $client->post('/profile/avatar/remove', ['_csrf_token' => $csrf]);
check('Avatar removal redirects', $removed['status'] === 302);
check('Avatar path is cleared', row($pdo, 'SELECT avatar_path FROM users WHERE id = ?', [$userId])['avatar_path'] === null);
clearstatcache(true, $absoluteAvatar);
check('Avatar file is deleted from disk', !is_file($absoluteAvatar), $absoluteAvatar);

@unlink($pngPath);
@unlink($evilPath);

echo "\n4. Notifications inbox and ownership scoping\n";

$inbox = $client->get('/notifications');
check('Notification inbox renders', $inbox['status'] === 200);
check('Inbox shows the achievement event', contains($inbox['body'], 'Achievement unlocked'));
check('Inbox shows the unread filter', contains($inbox['body'], 'Unread') && contains($inbox['body'], 'data-unread-count'));

$notification = row(
    $pdo,
    "SELECT * FROM notifications WHERE user_id = ? AND type = 'achievement' ORDER BY id ASC LIMIT 1",
    [$userId],
);
$notificationId = (int) ($notification['id'] ?? 0);
check('Achievement notification row exists', $notificationId > 0);

$read = $client->post('/notifications/' . $notificationId . '/read', ['_csrf_token' => $csrf]);
check('Mark-read redirects', $read['status'] === 302);
check(
    'Notification is marked read',
    row($pdo, 'SELECT read_at FROM notifications WHERE id = ?', [$notificationId])['read_at'] !== null,
);

$otherEmail = 'phase4_other_' . $suffix . '@example.test';
$otherUsername = 'phase4_other_' . $suffix;
$otherClient = new HttpClient($baseUrl);
registerAccount($otherClient, 'Other ' . $suffix, $otherUsername, $otherEmail, $password);
$otherUser = row($pdo, 'SELECT id FROM users WHERE email = ?', [$otherEmail]);
$otherId = (int) ($otherUser['id'] ?? 0);
$createdUsers[] = $otherId;

$stmt = $pdo->prepare(
    "INSERT INTO notifications (user_id, type, title, message, icon, event_key, created_at)
     VALUES (?, 'system', 'Private other alert', 'Should never be visible to the first user.', 'bell', ?, UTC_TIMESTAMP())",
);
$stmt->execute([$otherId, 'test:other:' . $suffix]);
$otherNotificationId = (int) $pdo->lastInsertId();

check(
    'Ownership scoping hides another user notification',
    !contains($client->get('/notifications')['body'], 'Private other alert'),
);
$forbidden = $client->post('/notifications/' . $otherNotificationId . '/read', ['_csrf_token' => $csrf]);
check('Cannot mark another user notification read', $forbidden['status'] === 404);

$markAll = $client->post('/notifications/read-all', ['_csrf_token' => $csrf]);
check('Mark-all-read redirects', $markAll['status'] === 302);
check('No unread notifications remain', scalar($pdo, 'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND read_at IS NULL', [$userId]) === 0);

echo "\n5. Leaderboards, visibility, periods and pagination\n";

$rivalId = 0;
$rivalDisplay = 'Rival ' . $suffix;
$hiddenId = 0;
$hiddenDisplay = 'Hidden ' . $suffix;
$suspendedDisplay = 'Suspended ' . $suffix;

$seed = $pdo->prepare(
    'INSERT INTO users (username, email, password_hash, display_name, timezone, xp_total, level, show_on_leaderboard, status)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
);

$seed->execute([
    'phase4_rival_' . $suffix,
    'phase4_rival_' . $suffix . '@example.test',
    password_hash($password, PASSWORD_DEFAULT),
    $rivalDisplay,
    'UTC',
    500,
    5,
    1,
    'active',
]);
$rivalId = (int) $pdo->lastInsertId();
$createdUsers[] = $rivalId;

$seed->execute([
    'phase4_hidden_' . $suffix,
    'phase4_hidden_' . $suffix . '@example.test',
    password_hash($password, PASSWORD_DEFAULT),
    $hiddenDisplay,
    'UTC',
    900,
    7,
    0,
    'active',
]);
$hiddenId = (int) $pdo->lastInsertId();
$createdUsers[] = $hiddenId;

$seed->execute([
    'phase4_susp_' . $suffix,
    'phase4_susp_' . $suffix . '@example.test',
    password_hash($password, PASSWORD_DEFAULT),
    $suspendedDisplay,
    'UTC',
    1000,
    8,
    1,
    'suspended',
]);
$createdUsers[] = (int) $pdo->lastInsertId();

$pdo->prepare(
    "INSERT INTO xp_transactions (user_id, quest_id, quest_title, source, amount, level_before, level_after, created_at)
     VALUES (?, NULL, 'Seeded weekly XP', 'quest_completion', 500, 1, 5, UTC_TIMESTAMP())",
)->execute([$rivalId]);

$extraDisplays = [];
$extraIds = [];

for ($i = 1; $i <= 22; $i++) {
    $xp = $i <= 4 ? 100 + $i : (($i === 5 || $i === 6) ? 105 : 100 + $i);
    $display = 'Extra ' . str_pad((string) $i, 2, '0', STR_PAD_LEFT) . ' ' . $suffix;
    $seed->execute([
        'phase4_extra_' . $i . '_' . $suffix,
        'phase4_extra_' . $i . '_' . $suffix . '@example.test',
        password_hash($password, PASSWORD_DEFAULT),
        $display,
        'UTC',
        $xp,
        1,
        1,
        'active',
    ]);
    $extraIds[$i] = (int) $pdo->lastInsertId();
    $extraDisplays[$i] = $display;
    $createdUsers[] = $extraIds[$i];
}

$board = $client->get('/leaderboard');
check('Leaderboard renders for the owner', $board['status'] === 200 && contains($board['body'], 'Hall of adventurers'));
check('All-time board includes a public rival', contains($board['body'], $rivalDisplay));
check('Public board excludes a hidden account', !contains($board['body'], $hiddenDisplay));
check('Public board excludes a suspended account', !contains($board['body'], $suspendedDisplay));
check('Leaderboard shows the viewer row', contains($board['body'], 'Your standing'));

$leaderboardBody = substr($board['body'], (int) strpos($board['body'], 'Hall of adventurers'));
$podiumPosition = strpos($leaderboardBody, $rivalDisplay);
$viewerPosition = strpos($leaderboardBody, $newDisplayName);
check(
    'Higher XP ranks above the viewer',
    $podiumPosition !== false && $viewerPosition !== false && $podiumPosition < $viewerPosition,
    'rival=' . var_export($podiumPosition, true) . ' viewer=' . var_export($viewerPosition, true),
);
check('Pagination reports a second page', contains($board['body'], 'Page 1 of 2') && contains($board['body'], 'page=2'));

// Seeded accounts may push one of the tied rows onto a later page, so rank the
// rendered rows across every page rather than assuming both fit on page one.
$tieBodies = $board['body'];
$boardPages = preg_match('/Page \d+ of (\d+)/', $board['body'], $pageMatch) === 1
    ? (int) $pageMatch[1]
    : 1;

for ($page = 2; $page <= $boardPages; $page++) {
    $tieBodies .= $client->get('/leaderboard?period=all&page=' . $page)['body'];
}

$tiedFirst = strpos($tieBodies, $extraDisplays[5]);
$tiedSecond = strpos($tieBodies, $extraDisplays[6]);
check(
    'Equal XP is tie-broken deterministically by account id',
    $tiedFirst !== false && $tiedSecond !== false && $tiedFirst < $tiedSecond,
);

$pageTwo = $client->get('/leaderboard?period=all&page=2');
check('Second leaderboard page renders', $pageTwo['status'] === 200 && contains($pageTwo['body'], 'Page 2 of 2'));
$pageTwoBody = substr($pageTwo['body'], (int) strpos($pageTwo['body'], 'Hall of adventurers'));
check('Second page shows the viewer', contains($pageTwoBody, $newDisplayName));
check('Second page offers previous navigation', contains($pageTwo['body'], 'Previous'));

$weekly = $client->get('/leaderboard?period=weekly');
check('Weekly board renders', $weekly['status'] === 200 && contains($weekly['body'], 'This week'));
check('Weekly board includes this week activity', contains($weekly['body'], $rivalDisplay));
check('Weekly board excludes lifetime-only accounts', !contains($weekly['body'], $extraDisplays[10]));

$monthly = $client->get('/leaderboard?period=monthly');
check('Monthly board renders', $monthly['status'] === 200 && contains($monthly['body'], 'This month'));

$user = row($pdo, 'SELECT last_leaderboard_rank FROM users WHERE id = ?', [$userId]);
$baselineRank = (int) ($user['last_leaderboard_rank'] ?? 0);
check('Leaderboard view records the viewer baseline rank', $baselineRank > 0);

$seed->execute([
    'phase4_rank_' . $suffix,
    'phase4_rank_' . $suffix . '@example.test',
    password_hash($password, PASSWORD_DEFAULT),
    'Rank Shifter ' . $suffix,
    'UTC',
    400,
    4,
    1,
    'active',
]);
$createdUsers[] = (int) $pdo->lastInsertId();

$client->get('/leaderboard');
$currentRank = (int) (row($pdo, 'SELECT last_leaderboard_rank FROM users WHERE id = ?', [$userId])['last_leaderboard_rank'] ?? 0);
check('Leaderboard view refreshes the stored rank', $currentRank === $baselineRank + 1);
check(
    'Rank change creates a leaderboard notification',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'leaderboard'",
        [$userId],
    ) >= 1,
);

echo "\n6. Timezone and streak edge cases\n";

$utc = new DateTimeZone('UTC');
$tokyo = StreakService::localDate(new DateTimeImmutable('2026-01-01 23:30:00', $utc), 'Asia/Tokyo');
check('Streak local date follows the user timezone', $tokyo === '2026-01-02');

$streakUser = ['current_streak' => 4, 'last_streak_date' => '2026-01-01', 'timezone' => 'UTC'];
check(
    'A streak stays current on the following day',
    StreakService::effectiveCurrent($streakUser, new DateTimeImmutable('2026-01-02 12:00:00', $utc)) === 4,
);
check(
    'A streak decays after a missed day',
    StreakService::effectiveCurrent($streakUser, new DateTimeImmutable('2026-01-04 12:00:00', $utc)) === 0,
);
check(
    'No recorded day means no streak',
    StreakService::effectiveCurrent(['current_streak' => 9, 'last_streak_date' => null, 'timezone' => 'UTC']) === 0,
);

echo "\n7. Cleanup\n";

$placeholders = implode(', ', array_fill(0, count($createdUsers), '?'));
$pdo->prepare("DELETE FROM users WHERE id IN ({$placeholders})")->execute($createdUsers);
foreach ([$email, $otherEmail] as $address) {
    $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($address)]);
}

check(
    'Phase 4 accounts and dependent rows are cleaned up',
    scalar($pdo, "SELECT COUNT(*) FROM users WHERE id IN ({$placeholders})", $createdUsers) === 0,
);

foreach ($avatarFiles as $file) {
    @unlink($file);
}

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

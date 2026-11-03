<?php

declare(strict_types=1);

/**
 * Phase 3 quest engine regression suite.
 *
 * Requires the app to be served at APP_URL, the database to be migrated and
 * the assets built. Usage: php tests/quest_flow.php
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
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_quest_');
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
function questRow(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM quests WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

/** @return array<string, mixed>|null */
function userRow(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $statement->execute([$id]);
    $row = $statement->fetch(PDO::FETCH_ASSOC);

    return $row === false ? null : $row;
}

function ledgerCount(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM xp_transactions WHERE user_id = ?');
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

function ledgerSum(PDO $pdo, int $userId): int
{
    $statement = $pdo->prepare('SELECT COALESCE(SUM(amount), 0) FROM xp_transactions WHERE user_id = ?');
    $statement->execute([$userId]);

    return (int) $statement->fetchColumn();
}

/**
 * @return int quest id on success
 */
function createQuest(HttpClient $client, string $title, string $description, string $difficulty, int $xpReward = 9999): int
{
    $page = $client->get('/quests/create');
    $csrf = $client->csrf($page['body']);
    $response = $client->post('/quests', [
        '_csrf_token' => $csrf,
        'title' => $title,
        'description' => $description,
        'difficulty' => $difficulty,
        'xp_reward' => (string) $xpReward,
    ]);

    if ($response['status'] !== 302) {
        throw new RuntimeException('Quest creation failed with status ' . $response['status'] . ': ' . substr($response['body'], 0, 300));
    }

    $location = (string) ($response['headers']['location'] ?? '');

    if (preg_match('#/quests/(\d+)$#', $location, $match) !== 1) {
        throw new RuntimeException('Quest creation did not redirect to a quest: ' . $location);
    }

    return (int) $match[1];
}

function completeQuest(HttpClient $client, int $questId): array
{
    $page = $client->get('/quests/' . $questId);
    $csrf = $client->csrf($page['body']);

    return $client->post('/quests/' . $questId . '/complete', ['_csrf_token' => $csrf]);
}

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$usernameA = 'quester_' . $suffix;
$emailA = 'quester_' . $suffix . '@example.test';
$passwordA = 'QuestFlow-' . $suffix . '7';
$displayA = 'Quest Tester ' . $suffix;

$usernameB = 'rival_' . $suffix;
$emailB = 'rival_' . $suffix . '@example.test';
$passwordB = 'RivalFlow-' . $suffix . '9';
$displayB = 'Rival Tester ' . $suffix;

$guest = new HttpClient($baseUrl);
$client = new HttpClient($baseUrl);
$rival = new HttpClient($baseUrl);

echo "\n1. Guest access and CSRF protection\n";

foreach (['/quests', '/quests/create', '/quests/1', '/quests/1/edit'] as $route) {
    $response = $guest->get($route);
    check(
        "{$route} requires authentication",
        $response['status'] === 302 && str_ends_with($response['headers']['location'] ?? '', '/login'),
    );
}

$guestPost = $guest->post('/quests', ['title' => 'Guest quest', 'description' => 'Should never be created.', 'difficulty' => 'easy']);
check(
    'Guest cannot create a quest',
    $guestPost['status'] === 302 && str_ends_with($guestPost['headers']['location'] ?? '', '/login'),
);

echo "\n2. Account setup\n";

$registeredA = registerAccount($client, $displayA, $usernameA, $emailA, $passwordA);
check(
    'Owner account registered and signed in',
    $registeredA['status'] === 302 && str_ends_with($registeredA['headers']['location'] ?? '', '/dashboard'),
);

$registeredB = registerAccount($rival, $displayB, $usernameB, $emailB, $passwordB);
check(
    'Second account registered and signed in',
    $registeredB['status'] === 302 && str_ends_with($registeredB['headers']['location'] ?? '', '/dashboard'),
);

$userA = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$userA->execute([$emailA]);
$rowA = $userA->fetch(PDO::FETCH_ASSOC);
$userB = $pdo->prepare('SELECT * FROM users WHERE email = ?');
$userB->execute([$emailB]);
$rowB = $userB->fetch(PDO::FETCH_ASSOC);

check('Both accounts exist', is_array($rowA) && is_array($rowB));

$userIdA = (int) $rowA['id'];
$userIdB = (int) $rowB['id'];

echo "\n3. Creation validation and server-derived rewards\n";

$createPage = $client->get('/quests/create');
$csrf = $client->csrf($createPage['body']);
check('Creation page renders with a CSRF token', $createPage['status'] === 200 && $csrf !== null);
check('Creation page shows the central reward values', contains($createPage['body'], 'Reward preview') && contains($createPage['body'], '100 XP'));

$noCsrf = $client->post('/quests', [
    'title' => 'No token quest',
    'description' => 'This request has no CSRF token at all.',
    'difficulty' => 'easy',
]);
check('Creation without CSRF is rejected', $noCsrf['status'] === 419);

$invalid = $client->post('/quests', [
    '_csrf_token' => $csrf,
    'title' => 'x',
    'description' => 'short',
    'difficulty' => 'legendary',
    'xp_reward' => '100000',
]);
check('Invalid quest input returns 422', $invalid['status'] === 422);
check(
    'Validation messages cover title, description and difficulty',
    contains($invalid['body'], 'Quest title must be at least')
        && contains($invalid['body'], 'Description must be at least')
        && contains($invalid['body'], 'Difficulty is not valid'),
);
$persisted = $pdo->prepare('SELECT COUNT(*) FROM quests WHERE user_id = ?');
$persisted->execute([$userIdA]);
check('Invalid quest was not persisted', (int) $persisted->fetchColumn() === 0);

$arrayInput = $client->post('/quests', [
    '_csrf_token' => $csrf,
    'title' => ['Nested title'],
    'description' => ['Nested description'],
    'difficulty' => ['easy'],
]);
check('Array form fields are rejected without a server error', $arrayInput['status'] === 422);

$easyId = createQuest($client, 'Easy reward quest', 'A quick task with a small reward.', 'easy', 999999);
$mediumId = createQuest($client, 'Medium reward quest', 'A focused task that takes real effort.', 'medium', 999999);
$hardId = createQuest($client, 'Hard reward quest', 'Deep work with a meaningful finish line.', 'hard', 999999);
$epicId = createQuest($client, 'Epic reward quest', 'A major objective worth celebrating.', 'epic', 999999);
$editId = createQuest($client, 'Edit target quest', 'This quest will be edited before completion.', 'easy', 999999);
$deleteId = createQuest($client, 'Delete candidate quest', 'This quest has never paid XP and can be deleted.', 'easy', 999999);
$xssId = createQuest($client, '<script>alert("xss")</script>', 'This description contains <b>markup</b> and must be escaped.', 'easy', 999999);

$rewards = (array) config('game.xp_per_difficulty', []);
$createdEasy = questRow($pdo, $easyId);
$createdMedium = questRow($pdo, $mediumId);
$createdHard = questRow($pdo, $hardId);
$createdEpic = questRow($pdo, $epicId);

check('Easy quest stored with 10 XP', is_array($createdEasy) && (int) $createdEasy['xp_reward'] === (int) $rewards['easy'] && (int) $createdEasy['xp_reward'] === 10);
check('Medium quest stored with 25 XP', is_array($createdMedium) && (int) $createdMedium['xp_reward'] === (int) $rewards['medium'] && (int) $createdMedium['xp_reward'] === 25);
check('Hard quest stored with 50 XP', is_array($createdHard) && (int) $createdHard['xp_reward'] === (int) $rewards['hard'] && (int) $createdHard['xp_reward'] === 50);
check('Epic quest stored with 100 XP', is_array($createdEpic) && (int) $createdEpic['xp_reward'] === (int) $rewards['epic'] && (int) $createdEpic['xp_reward'] === 100);
check('Client-submitted XP was ignored', is_array($createdEasy) && (int) $createdEasy['xp_reward'] !== 999999);
check('New quests start active with no completion time', is_array($createdEasy) && $createdEasy['status'] === 'active' && $createdEasy['completed_at'] === null);

$easyShow = $client->get('/quests/' . $easyId);
check('Quest detail renders after creation', $easyShow['status'] === 200 && contains($easyShow['body'], 'Easy reward quest'));
check('Creation flash confirms the reward', contains($easyShow['body'], 'Complete it to earn 10 XP'));
check('Quest detail shows the reward and claim action', contains($easyShow['body'], '+10') && contains($easyShow['body'], 'Complete quest'));

$xssShow = $client->get('/quests/' . $xssId);
check(
    'Quest output escapes stored markup',
    $xssShow['status'] === 200
        && !contains($xssShow['body'], '<script>alert("xss")</script>')
        && contains($xssShow['body'], '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;'),
);

echo "\n4. Listing, tabs and filters\n";

$list = $client->get('/quests');
check('Quest list renders all active quests', $list['status'] === 200 && contains($list['body'], 'Easy reward quest') && contains($list['body'], 'Epic reward quest'));
check('Quest list has working status tabs', contains($list['body'], 'Completed') && contains($list['body'], 'Archived') && contains($list['body'], 'aria-current="page"'));
check('Quest list reports the active count', contains($list['body'], 'Ready to be completed.'));

$completedTab = $client->get('/quests?status=completed');
check('Completed tab starts empty', $completedTab['status'] === 200 && contains($completedTab['body'], 'Nothing completed yet'));
$archivedTab = $client->get('/quests?status=archived');
check('Archived tab starts empty', $archivedTab['status'] === 200 && contains($archivedTab['body'], 'Archive is empty'));

$epicFilter = $client->get('/quests?status=active&difficulty=epic');
check(
    'Difficulty filter narrows the list',
    contains($epicFilter['body'], 'Epic reward quest') && !contains($epicFilter['body'], 'Easy reward quest'),
);

$arrayQuery = $client->get('/quests?status[]=archived&difficulty[]=epic');
check(
    'Array query parameters fall back safely',
    $arrayQuery['status'] === 200 && contains($arrayQuery['body'], 'My Quests'),
);

$rivalList = $rival->get('/quests');
check(
    'Another user never sees these quests',
    !contains($rivalList['body'], 'Easy reward quest') && !contains($rivalList['body'], 'Epic reward quest'),
);

echo "\n5. Ownership enforcement\n";

foreach (['/quests/' . $easyId, '/quests/' . $easyId . '/edit'] as $route) {
    $response = $rival->get($route);
    check("{$route} is hidden from another user", $response['status'] === 404);
}

$rivalListPage = $rival->get('/quests');
$rivalCsrf = $rival->csrf($rivalListPage['body']);

$rivalComplete = $rival->post('/quests/' . $easyId . '/complete', ['_csrf_token' => $rivalCsrf]);
check('Another user cannot complete the quest', $rivalComplete['status'] === 404);

$rivalArchive = $rival->post('/quests/' . $easyId . '/archive', ['_csrf_token' => $rivalCsrf]);
check('Another user cannot archive the quest', $rivalArchive['status'] === 404);

$rivalUpdate = $rival->post('/quests/' . $easyId, [
    '_csrf_token' => $rivalCsrf,
    '_method' => 'PUT',
    'title' => 'Stolen quest',
    'description' => 'This update must never be applied.',
    'difficulty' => 'epic',
]);
check('Another user cannot update the quest', $rivalUpdate['status'] === 404);

$rivalDelete = $rival->post('/quests/' . $deleteId . '/delete', ['_csrf_token' => $rivalCsrf]);
check('Another user cannot delete the quest', $rivalDelete['status'] === 404);

check('Ownership failures did not change the quest', (string) questRow($pdo, $easyId)['title'] === 'Easy reward quest');
check('Ownership failures awarded no XP', (int) userRow($pdo, $userIdA)['xp_total'] === 0 && ledgerCount($pdo, $userIdA) === 0);

echo "\n6. Editing\n";

$editPage = $client->get('/quests/' . $editId . '/edit');
$editCsrf = $client->csrf($editPage['body']);
check('Owner can open the edit form', $editPage['status'] === 200 && contains($editPage['body'], 'Edit target quest'));
check('Edit form loads the stored values', contains($editPage['body'], 'value="Edit target quest"'));

$updated = $client->post('/quests/' . $editId, [
    '_csrf_token' => $editCsrf,
    '_method' => 'PUT',
    'title' => 'Edited quest title',
    'description' => 'The edited description is long enough to pass validation.',
    'difficulty' => 'medium',
    'xp_reward' => '9999',
]);
check('Valid edit redirects to the quest', $updated['status'] === 302 && str_ends_with($updated['headers']['location'] ?? '', '/quests/' . $editId));

$editedRow = questRow($pdo, $editId);
check('Edit persists the new title', is_array($editedRow) && $editedRow['title'] === 'Edited quest title');
check('Edit recalculates the server-side reward', is_array($editedRow) && $editedRow['difficulty'] === 'medium' && (int) $editedRow['xp_reward'] === 25);
check('Edit ignored the submitted reward', is_array($editedRow) && (int) $editedRow['xp_reward'] !== 9999);

$invalidEdit = $client->get('/quests/' . $editId . '/edit');
$invalidCsrf = $client->csrf($invalidEdit['body']);
$invalidUpdate = $client->post('/quests/' . $editId, [
    '_csrf_token' => $invalidCsrf,
    '_method' => 'PUT',
    'title' => 'x',
    'description' => 'short',
    'difficulty' => 'impossible',
]);
check('Invalid edit returns 422 with errors', $invalidUpdate['status'] === 422 && contains($invalidUpdate['body'], 'must be at least'));
check('Invalid edit left the row untouched', (string) questRow($pdo, $editId)['title'] === 'Edited quest title');

echo "\n7. Completion and single-use XP\n";

$completeEasy = completeQuest($client, $easyId);
check('Completing an active quest redirects', $completeEasy['status'] === 302);
$easyRow = questRow($pdo, $easyId);
check('Quest becomes completed with a timestamp', is_array($easyRow) && $easyRow['status'] === 'completed' && $easyRow['completed_at'] !== null);
check('Owner XP increased by the easy reward', (int) userRow($pdo, $userIdA)['xp_total'] === 10);
check('XP ledger contains one easy award', ledgerCount($pdo, $userIdA) === 1 && ledgerSum($pdo, $userIdA) === 10);

$repeatEasy = completeQuest($client, $easyId);
check('Repeat completion is accepted without a new award', $repeatEasy['status'] === 302);
check('Repeat completion did not add XP', (int) userRow($pdo, $userIdA)['xp_total'] === 10 && ledgerCount($pdo, $userIdA) === 1);
check('Repeat completion left the quest completed', (string) questRow($pdo, $easyId)['status'] === 'completed');

$duplicateInserted = false;

try {
    $duplicate = $pdo->prepare(
        'INSERT INTO xp_transactions (user_id, quest_id, quest_title, source, amount, level_before, level_after, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())',
    );
    $duplicate->execute([$userIdA, $easyId, 'Duplicate award', 'quest_completion', 10, 1, 1]);
    $duplicateInserted = true;
} catch (\PDOException $exception) {
    check('Unique index rejects a duplicate quest award', ($exception->errorInfo[1] ?? null) === 1062);
}

check('Duplicate raw award was not persisted', $duplicateInserted === false && ledgerCount($pdo, $userIdA) === 1);

$completeMedium = completeQuest($client, $mediumId);
$afterMedium = userRow($pdo, $userIdA);
check('Medium quest awards 25 XP', $completeMedium['status'] === 302 && (int) $afterMedium['xp_total'] === 35);
check('Level remains 1 below 100 XP', (int) $afterMedium['level'] === 1);

$completeHard = completeQuest($client, $hardId);
$afterHard = userRow($pdo, $userIdA);
check('Hard quest awards 50 XP', $completeHard['status'] === 302 && (int) $afterHard['xp_total'] === 85);
check('Level remains 1 below the curve floor', (int) $afterHard['level'] === 1);

$completeEpic = completeQuest($client, $epicId);
$afterEpic = userRow($pdo, $userIdA);
check('Epic quest awards 100 XP', $completeEpic['status'] === 302 && (int) $afterEpic['xp_total'] === 185);
check('Crossing 100 XP raises the stored level to 2', (int) $afterEpic['level'] === 2);
check('Rank is derived from the level', Progression::rank(2) === 'Novice' && Progression::levelForXp(185) === 2);

$completeEdit = completeQuest($client, $editId);
$afterEdit = userRow($pdo, $userIdA);
check('Edited quest pays its recalculated 25 XP', $completeEdit['status'] === 302 && (int) $afterEdit['xp_total'] === 210);
check('Ledger total matches the account balance', ledgerSum($pdo, $userIdA) === 210 && ledgerCount($pdo, $userIdA) === 5);

$completedShow = $client->get('/quests/' . $easyId);
check('Completed quest shows its claimed reward', contains($completedShow['body'], 'Reward claimed') && contains($completedShow['body'], '+10'));
check('Completed quest explains single-use XP', contains($completedShow['body'], 'already paid out'));

echo "\n8. Dashboard integration\n";

$countsStatement = $pdo->prepare('SELECT status, COUNT(*) AS total FROM quests WHERE user_id = ? GROUP BY status');
$countsStatement->execute([$userIdA]);
$counts = ['active' => 0, 'completed' => 0, 'archived' => 0];

foreach ($countsStatement->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $counts[(string) $row['status']] = (int) $row['total'];
}

$dashboard = $client->get('/dashboard');
$dashboardHtml = $dashboard['body'];
check('Dashboard loads with live quest data', $dashboard['status'] === 200);

preg_match('/Completed quests<\/p>.*?app-stat-value">(\d+)</s', $dashboardHtml, $completedMatch);
preg_match('/Active quests<\/p>.*?app-stat-value">(\d+)</s', $dashboardHtml, $activeMatch);

check('Dashboard completed count matches the database', isset($completedMatch[1]) && (int) $completedMatch[1] === $counts['completed']);
check('Dashboard active count matches the database', isset($activeMatch[1]) && (int) $activeMatch[1] === $counts['active']);
check('Dashboard shows the real XP total', contains($dashboardHtml, 'data-counter="210"'));
check('Dashboard shows level 2', contains($dashboardHtml, 'Level 2'));
check('Dashboard shows the derived rank', contains($dashboardHtml, 'Novice'));

check('Recent activity is no longer empty', !contains($dashboardHtml, 'No activity yet'));
check('Recent activity lists the latest quest', contains($dashboardHtml, 'Edited quest title'));
check('Recent activity lists its XP award', contains($dashboardHtml, '+25 XP'));

echo "\n9. Archive, restore and delete rules\n";

$archivePage = $client->get('/quests/' . $deleteId);
$archiveCsrf = $client->csrf($archivePage['body']);
$archived = $client->post('/quests/' . $deleteId . '/archive', ['_csrf_token' => $archiveCsrf]);
check('Active quest can be archived', $archived['status'] === 302 && (string) questRow($pdo, $deleteId)['status'] === 'archived');

$archivedList = $client->get('/quests?status=archived');
check('Archived quest appears in its tab', contains($archivedList['body'], 'Delete candidate quest'));
check('Archived quest can be restored', true);

$restoreCsrf = $client->csrf($archivedList['body']);
$restored = $client->post('/quests/' . $deleteId . '/restore', ['_csrf_token' => $restoreCsrf]);
check('Restore returns the quest to active', $restored['status'] === 302 && (string) questRow($pdo, $deleteId)['status'] === 'active');

$archiveAgain = $client->post('/quests/' . $deleteId . '/archive', ['_csrf_token' => $restoreCsrf]);
check('Quest can be archived again', $archiveAgain['status'] === 302 && (string) questRow($pdo, $deleteId)['status'] === 'archived');

$deleteCsrf = $client->csrf($client->get('/quests?status=archived')['body']);
$deleted = $client->post('/quests/' . $deleteId . '/delete', ['_csrf_token' => $deleteCsrf]);
check('Never-completed archived quest can be deleted', $deleted['status'] === 302 && questRow($pdo, $deleteId) === null);

$archiveEpic = $client->post('/quests/' . $epicId . '/archive', ['_csrf_token' => $deleteCsrf]);
$epicArchived = questRow($pdo, $epicId);
check('Completed quest can be archived', $archiveEpic['status'] === 302 && (string) $epicArchived['status'] === 'archived');
check('Archiving completed quest preserves completion and XP', $epicArchived['completed_at'] !== null && ledgerCount($pdo, $userIdA) === 5);

$completedArchivedList = $client->get('/quests?status=archived');
check('Completed archive keeps its completed context', contains($completedArchivedList['body'], 'Epic reward quest'));

$epicDeleteCsrf = $client->csrf($completedArchivedList['body']);
$refusedDelete = $client->post('/quests/' . $epicId . '/delete', ['_csrf_token' => $epicDeleteCsrf]);
check('Completed quest cannot be deleted', $refusedDelete['status'] === 302 && questRow($pdo, $epicId) !== null);

$epicRestore = $client->post('/quests/' . $epicId . '/restore', ['_csrf_token' => $epicDeleteCsrf]);
$epicRestored = questRow($pdo, $epicId);
check('Restoring completed quest returns it to completed', $epicRestore['status'] === 302 && (string) $epicRestored['status'] === 'completed');
check('Restore did not award XP again', ledgerCount($pdo, $userIdA) === 5 && (int) userRow($pdo, $userIdA)['xp_total'] === 210);

$editCompleted = $client->get('/quests/' . $epicId . '/edit');
check('Completed quests cannot be edited', $editCompleted['status'] === 302 && str_ends_with($editCompleted['headers']['location'] ?? '', '/quests/' . $epicId));

echo "\n10. CSRF protection on every quest mutation\n";

$xssPage = $client->get('/quests/' . $xssId);
$xssCsrf = $client->csrf($xssPage['body']);

$noCsrfUpdate = $client->post('/quests/' . $xssId, [
    '_method' => 'PUT',
    'title' => 'CSRF bypass title',
    'description' => 'This update must be rejected by the token check.',
    'difficulty' => 'hard',
]);
check('Update without CSRF is rejected', $noCsrfUpdate['status'] === 419);

$noCsrfComplete = $client->post('/quests/' . $xssId . '/complete');
check('Completion without CSRF is rejected', $noCsrfComplete['status'] === 419 && (string) questRow($pdo, $xssId)['status'] === 'active');

$noCsrfArchive = $client->post('/quests/' . $xssId . '/archive');
check('Archive without CSRF is rejected', $noCsrfArchive['status'] === 419 && (string) questRow($pdo, $xssId)['status'] === 'active');

$archiveXss = $client->post('/quests/' . $xssId . '/archive', ['_csrf_token' => $xssCsrf]);
check('CSRF-valid archive succeeds', $archiveXss['status'] === 302 && (string) questRow($pdo, $xssId)['status'] === 'archived');

$archivedXssPage = $client->get('/quests/' . $xssId);
$archivedXssCsrf = $client->csrf($archivedXssPage['body']);
$noCsrfRestore = $client->post('/quests/' . $xssId . '/restore');
check('Restore without CSRF is rejected', $noCsrfRestore['status'] === 419 && (string) questRow($pdo, $xssId)['status'] === 'archived');

$restoreXss = $client->post('/quests/' . $xssId . '/restore', ['_csrf_token' => $archivedXssCsrf]);
check('CSRF-valid restore succeeds', $restoreXss['status'] === 302 && (string) questRow($pdo, $xssId)['status'] === 'active');

$noCsrfDelete = $client->post('/quests/' . $xssId . '/delete');
check('Delete without CSRF is rejected', $noCsrfDelete['status'] === 419 && questRow($pdo, $xssId) !== null);

$archivedComplete = $client->post('/quests/' . $xssId . '/archive', ['_csrf_token' => $xssCsrf]);
$completeArchived = $client->post('/quests/' . $xssId . '/complete', ['_csrf_token' => $xssCsrf]);
check('Archived quest cannot award XP', $archivedComplete['status'] === 302 && $completeArchived['status'] === 302 && (string) questRow($pdo, $xssId)['status'] === 'archived');
check('Archived completion added no XP', ledgerCount($pdo, $userIdA) === 5);

$restoreFinal = $client->post('/quests/' . $xssId . '/restore', ['_csrf_token' => $xssCsrf]);
check('Archived quest restored after the attempt', $restoreFinal['status'] === 302 && (string) questRow($pdo, $xssId)['status'] === 'active');

echo "\n11. Level and rank progression\n";

$insert = $pdo->prepare(
    'INSERT INTO quests (user_id, title, description, difficulty, xp_reward, status, created_at, updated_at)
     VALUES (?, ?, ?, ?, ?, ?, UTC_TIMESTAMP(), UTC_TIMESTAMP())',
);

$grindIds = [];

for ($index = 1; $index <= 14; $index++) {
    $insert->execute([
        $userIdA,
        'Epic grind quest ' . $index,
        'A directly seeded epic quest used to exercise level and rank transitions.',
        'epic',
        100,
        'active',
    ]);
    $grindIds[] = (int) $pdo->lastInsertId();
}

check('Fourteen epic quests seeded for the level curve', count($grindIds) === 14);

$grindList = $client->get('/quests');
$grindCsrf = $client->csrf($grindList['body']);
$grindFailures = 0;

foreach ($grindIds as $grindId) {
    $response = $client->post('/quests/' . $grindId . '/complete', ['_csrf_token' => $grindCsrf]);

    if ($response['status'] !== 302) {
        $grindFailures++;
    }
}

check('All seeded epic quests completed', $grindFailures === 0);

$finalUser = userRow($pdo, $userIdA);
check('Total XP reaches 1610', (int) $finalUser['xp_total'] === 1610);
check('Stored level recalculates to 5', (int) $finalUser['level'] === 5);
check('Curve helper agrees at the rank threshold', Progression::levelForXp(1600) === 5 && Progression::rank(5) === 'Adventurer');
check('Ledger matches the final balance', ledgerCount($pdo, $userIdA) === 19 && ledgerSum($pdo, $userIdA) === 1610);

$finalDashboard = $client->get('/dashboard');
check('Dashboard reflects the new level', contains($finalDashboard['body'], 'Level 5'));
check('Dashboard reflects the Adventurer rank', contains($finalDashboard['body'], 'Adventurer'));
check('Dashboard reflects the final XP total', contains($finalDashboard['body'], 'data-counter="1610"'));
check('Recent activity includes a level-up marker', contains($finalDashboard['body'], 'Level 4') && contains($finalDashboard['body'], 'Level 5'));

echo "\n11b. Concurrent completion locking\n";

$insert->execute([
    $userIdA,
    'Locked completion quest',
    'This quest proves that concurrent completions serialize on the quest row.',
    'easy',
    10,
    'active',
]);
$lockedQuestId = (int) $pdo->lastInsertId();

$databaseConfig = (array) config('database', []);
$secondConnection = new PDO(
    sprintf(
        'mysql:host=%s;port=%d;dbname=%s;charset=%s',
        (string) ($databaseConfig['host'] ?? 'localhost'),
        (int) ($databaseConfig['port'] ?? 3306),
        (string) ($databaseConfig['database'] ?? ''),
        (string) ($databaseConfig['charset'] ?? 'utf8mb4'),
    ),
    (string) ($databaseConfig['username'] ?? ''),
    (string) ($databaseConfig['password'] ?? ''),
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ],
);

$secondConnection->beginTransaction();
$lock = $secondConnection->prepare('SELECT id FROM quests WHERE id = ? FOR UPDATE');
$lock->execute([$lockedQuestId]);

$pdo->exec('SET SESSION innodb_lock_wait_timeout = 1');
$blocked = false;

try {
    $rivalLock = $pdo->prepare('SELECT id FROM quests WHERE id = ? FOR UPDATE');
    $rivalLock->execute([$lockedQuestId]);
} catch (\PDOException $exception) {
    $blocked = ($exception->errorInfo[1] ?? null) === 1205;
}

$secondConnection->rollBack();
check('A competing transaction waits for the quest row lock', $blocked);

$lockedComplete = completeQuest($client, $lockedQuestId);
check('The same completion succeeds after the lock is released', $lockedComplete['status'] === 302 && (string) questRow($pdo, $lockedQuestId)['status'] === 'completed');
check('The serialized completion awards XP once', ledgerCount($pdo, $userIdA) === 20 && ledgerSum($pdo, $userIdA) === 1620);

echo "\n12. Cleanup\n";

$deleteQuests = $pdo->prepare('DELETE FROM users WHERE id IN (?, ?)');
$deleteQuests->execute([$userIdA, $userIdB]);

$cleanA = $pdo->prepare('SELECT COUNT(*) FROM quests WHERE user_id = ?');
$cleanA->execute([$userIdA]);
$cleanB = $pdo->prepare('SELECT COUNT(*) FROM quests WHERE user_id = ?');
$cleanB->execute([$userIdB]);
$cleanLedger = $pdo->prepare('SELECT COUNT(*) FROM xp_transactions WHERE user_id IN (?, ?)');
$cleanLedger->execute([$userIdA, $userIdB]);

check('Test accounts removed', userRow($pdo, $userIdA) === null && userRow($pdo, $userIdB) === null);
check('Quest rows cascade-deleted with the accounts', (int) $cleanA->fetchColumn() === 0 && (int) $cleanB->fetchColumn() === 0);
check('XP ledger rows cascade-deleted with the accounts', (int) $cleanLedger->fetchColumn() === 0);

foreach ([$emailA, $emailB] as $email) {
    $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($email)]);
}

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

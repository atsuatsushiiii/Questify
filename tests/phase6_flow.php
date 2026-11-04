<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

/**
 * Phase 6 end-to-end suite: admin authorization, dashboard KPIs, user
 * moderation, category and achievement CRUD, and the audit trail.
 *
 * Requires the app to be served at APP_URL and the database to be migrated.
 * Usage: php tests/phase6_flow.php
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("CLI only.\n");
}

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

use App\Core\App;
use App\Core\Database;
use App\Models\Achievement;
use App\Models\AuditLog;
use App\Services\AdminService;

App::boot(BASE_PATH, false);

$baseUrl = rtrim((string) config('app.url', 'http://localhost:8000'), '/');

final class HttpClient
{
    private string $cookieFile;

    private ?string $lastSessionCookie = null;

    public function __construct(private readonly string $baseUrl)
    {
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_phase6_');
    }

    /**
     * @param array<string, mixed> $data
     * @param list<string> $headers
     * @return array{status: int, headers: array<string, string>, body: string, setCookie: list<string>}
     */
    public function request(string $method, string $path, array $data = [], array $headers = []): array
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
            CURLOPT_HTTPHEADER => $headers,
        ]);

        if ($method === 'POST' || $method === 'PUT' || $method === 'PATCH' || $method === 'DELETE') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        }

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

    public function getJson(string $path): array
    {
        return $this->request('GET', $path, [], ['Accept: application/json']);
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

    return $client->post('/register', [
        '_csrf_token' => $client->csrf($page['body']),
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

function csrfFor(HttpClient $client, string $path): ?string
{
    return $client->csrf($client->get($path)['body']);
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

/** @return array<string, mixed> */
function decodeJson(string $body): array
{
    $decoded = json_decode($body, true);

    return is_array($decoded) ? $decoded : [];
}

function cleanupLoginAttempts(PDO $pdo, array $identifiers): void
{
    foreach ($identifiers as $identifier) {
        $pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($identifier)]);
    }
}

function createQuest(HttpClient $client, string $title, int $categoryId): int
{
    $page = $client->get('/quests/create');
    $response = $client->post('/quests', [
        '_csrf_token' => $client->csrf($page['body']),
        'title' => $title,
        'description' => 'Phase 6 category integration coverage quest for ' . $title . '.',
        'difficulty' => 'easy',
        'category_id' => (string) $categoryId,
        'xp_reward' => '9999',
    ]);

    if ($response['status'] !== 302) {
        throw new RuntimeException('Quest creation failed with status ' . $response['status']);
    }

    if (preg_match('#/quests/(\d+)$#', (string) ($response['headers']['location'] ?? ''), $match) !== 1) {
        throw new RuntimeException('Quest creation did not return an id.');
    }

    return (int) $match[1];
}

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$createdUsers = [];
$createdCategoryIds = [];
$createdAchievementIds = [];
$createdQuestIds = [];
$identifiers = [];

$adminUsername = 'phase6_admin_' . $suffix;
$adminEmail = 'phase6_admin_' . $suffix . '@example.test';
$userUsername = 'phase6_user_' . $suffix;
$userEmail = 'phase6_user_' . $suffix . '@example.test';
$password = 'PhaseSix-' . $suffix . '9';
$identifiers[] = $adminEmail;
$identifiers[] = $userEmail;

$adminClient = new HttpClient($baseUrl);
$userClient = new HttpClient($baseUrl);
$guestClient = new HttpClient($baseUrl);

$work = row($pdo, 'SELECT * FROM categories WHERE slug = ?', ['work']);
$firstSteps = row($pdo, 'SELECT * FROM achievements WHERE achievement_key = ?', ['first_steps']);
$workId = (int) ($work['id'] ?? 0);
$firstStepsId = (int) ($firstSteps['id'] ?? 0);

echo "\n1. Fixtures and authorization gates\n";

check('Seeded Work category is available', $workId > 0);
check('Seeded First Steps achievement is available', $firstStepsId > 0);

$adminRegistered = registerAccount($adminClient, 'Phase Six Admin ' . $suffix, $adminUsername, $adminEmail, $password);
$userRegistered = registerAccount($userClient, 'Phase Six User ' . $suffix, $userUsername, $userEmail, $password);

$admin = row($pdo, 'SELECT * FROM users WHERE email = ?', [$adminEmail]);
$normal = row($pdo, 'SELECT * FROM users WHERE email = ?', [$userEmail]);
$adminId = (int) ($admin['id'] ?? 0);
$userId = (int) ($normal['id'] ?? 0);
$createdUsers[] = $adminId;
$createdUsers[] = $userId;

check(
    'Admin fixture registers and signs in',
    $adminRegistered['status'] === 302 && $adminId > 0,
);
check(
    'Normal fixture registers and signs in',
    $userRegistered['status'] === 302 && $userId > 0,
);

$pdo->prepare("UPDATE users SET role = 'admin' WHERE id = ?")->execute([$adminId]);

$guestAdmin = $guestClient->get('/admin');
check(
    'Guest is redirected from /admin to sign in',
    $guestAdmin['status'] === 302 && str_ends_with((string) ($guestAdmin['headers']['location'] ?? ''), '/login'),
);
$guestUsers = $guestClient->get('/admin/users');
check(
    'Guest is redirected from /admin/users to sign in',
    $guestUsers['status'] === 302 && str_ends_with((string) ($guestUsers['headers']['location'] ?? ''), '/login'),
);
$guestAudit = $guestClient->get('/admin/audit');
check(
    'Guest is redirected from /admin/audit to sign in',
    $guestAudit['status'] === 302 && str_ends_with((string) ($guestAudit['headers']['location'] ?? ''), '/login'),
);
$guestStats = $guestClient->getJson('/api/admin/stats');
$guestStatsJson = decodeJson($guestStats['body']);
check(
    'Guest receives 401 JSON from /api/admin/stats',
    $guestStats['status'] === 401
        && ($guestStatsJson['success'] ?? true) === false
        && ($guestStatsJson['error']['code'] ?? '') === 'unauthorized',
);
$guestMutation = $guestClient->post('/admin/categories', []);
check(
    'Guest admin mutation redirects to sign in before CSRF runs',
    $guestMutation['status'] === 302
        && str_ends_with((string) ($guestMutation['headers']['location'] ?? ''), '/login'),
);

$normalDashboard = $userClient->get('/dashboard');
check(
    'Normal user dashboard renders without an Administration section',
    $normalDashboard['status'] === 200
        && !contains($normalDashboard['body'], 'Administration')
        && !contains($normalDashboard['body'], 'href="' . $baseUrl . '/admin"'),
);

$normalGetRoutes = [
    '/admin',
    '/admin/users',
    '/admin/users/' . $adminId,
    '/admin/categories',
    '/admin/categories/create',
    '/admin/categories/' . $workId . '/edit',
    '/admin/achievements',
    '/admin/achievements/create',
    '/admin/achievements/' . $firstStepsId . '/edit',
    '/admin/audit',
];

foreach ($normalGetRoutes as $route) {
    $response = $userClient->get($route);
    check(
        'Normal user receives 403 on GET ' . $route,
        $response['status'] === 403,
        'status ' . $response['status'],
    );
}

$normalMutationRoutes = [
    ['POST', '/admin/users/' . $adminId . '/suspend', []],
    ['POST', '/admin/users/' . $userId . '/promote', []],
    ['POST', '/admin/categories', []],
    ['POST', '/admin/categories/' . $workId, ['_method' => 'PUT']],
    ['POST', '/admin/categories/' . $workId . '/delete', []],
    ['POST', '/admin/achievements', []],
    ['POST', '/admin/achievements/' . $firstStepsId, ['_method' => 'PUT']],
    ['POST', '/admin/achievements/' . $firstStepsId . '/delete', []],
];

foreach ($normalMutationRoutes as [$method, $route, $payload]) {
    $response = $userClient->post($route, $payload);
    check(
        'Normal user receives 403 on ' . $method . ' ' . $route,
        $response['status'] === 403,
        'status ' . $response['status'],
    );
}

$normalStats = $userClient->getJson('/api/admin/stats');
$normalStatsJson = decodeJson($normalStats['body']);
check(
    'Normal user receives 403 JSON from /api/admin/stats',
    $normalStats['status'] === 403
        && ($normalStatsJson['success'] ?? true) === false
        && ($normalStatsJson['error']['code'] ?? '') === 'forbidden',
);

echo "\n2. Admin dashboard and stats API\n";

$dashboard = $adminClient->get('/admin');
check(
    'Admin dashboard renders its KPI shell and chart',
    $dashboard['status'] === 200
        && contains($dashboard['body'], 'Platform overview')
        && contains($dashboard['body'], 'data-admin-growth-chart')
        && contains($dashboard['body'], '/api/admin/stats')
        && contains($dashboard['body'], 'Administration'),
);
check(
    'Admin dashboard shows the live account total',
    contains($dashboard['body'], number_format(scalar($pdo, 'SELECT COUNT(*) FROM users'))),
);
check(
    'Admin dashboard shows the active category total',
    contains($dashboard['body'], number_format(scalar($pdo, 'SELECT COUNT(*) FROM categories WHERE is_active = 1'))),
);

$stats = $adminClient->getJson('/api/admin/stats');
$statsJson = decodeJson($stats['body']);
check(
    'Admin stats API returns success and KPI data',
    $stats['status'] === 200
        && ($statsJson['success'] ?? false) === true
        && isset($statsJson['data']['kpis']['users'])
        && isset($statsJson['data']['signups'])
        && isset($statsJson['data']['completions']),
);
check(
    'Admin stats API defaults to a 14-day window',
    count($statsJson['data']['signups'] ?? []) === 14
        && count($statsJson['data']['completions'] ?? []) === 14,
);
check(
    'Admin stats API reports the real account count',
    (int) ($statsJson['data']['kpis']['users'] ?? -1) === scalar($pdo, 'SELECT COUNT(*) FROM users'),
);

$weekStats = $adminClient->getJson('/api/admin/stats?days=7');
$weekJson = decodeJson($weekStats['body']);
check(
    'Admin stats API accepts a 7-day window',
    $weekStats['status'] === 200 && count($weekJson['data']['signups'] ?? []) === 7,
);

$invalidWindow = $adminClient->getJson('/api/admin/stats?days=999');
$invalidWindowJson = decodeJson($invalidWindow['body']);
check(
    'Admin stats API rejects an out-of-range window by falling back to 14 days',
    $invalidWindow['status'] === 200 && count($invalidWindowJson['data']['signups'] ?? []) === 14,
);

echo "\n3. User directory and moderation\n";

$usersPage = $adminClient->get('/admin/users');
check(
    'User directory lists both fixture accounts',
    $usersPage['status'] === 200
        && contains($usersPage['body'], '@' . $adminUsername)
        && contains($usersPage['body'], '@' . $userUsername),
);
check(
    'User directory exposes search, role, and status filters',
    contains($usersPage['body'], 'name="search"')
        && contains($usersPage['body'], 'name="role"')
        && contains($usersPage['body'], 'name="status"'),
);

$searchPage = $adminClient->get('/admin/users?search=' . rawurlencode($userUsername));
check(
    'User search narrows the directory to the matching account',
    $searchPage['status'] === 200
        && contains($searchPage['body'], '@' . $userUsername)
        && !contains($searchPage['body'], '/admin/users/' . $adminId),
);

$rolePage = $adminClient->get('/admin/users?role=admin');
check(
    'Role filter returns administrators only',
    $rolePage['status'] === 200
        && contains($rolePage['body'], '/admin/users/' . $adminId)
        && !contains($rolePage['body'], '/admin/users/' . $userId),
);

$statusPage = $adminClient->get('/admin/users?status=suspended');
check(
    'Status filter starts with an empty suspended list',
    $statusPage['status'] === 200 && contains($statusPage['body'], 'No accounts match these filters'),
);

$clampedPage = $adminClient->get('/admin/users?page=99');
check(
    'Out-of-range user page clamps to the final page',
    $clampedPage['status'] === 200 && contains($clampedPage['body'], 'Page 1 / 1'),
);

$userDetail = $adminClient->get('/admin/users/' . $userId);
check(
    'User detail shows account statistics, quests, sessions, and audit history',
    $userDetail['status'] === 200
        && contains($userDetail['body'], $userEmail)
        && contains($userDetail['body'], 'Recent quests')
        && contains($userDetail['body'], 'Active sessions')
        && contains($userDetail['body'], 'Audit history'),
);
check(
    'User detail offers suspend and promote actions',
    contains($userDetail['body'], '/admin/users/' . $userId . '/suspend')
        && contains($userDetail['body'], '/admin/users/' . $userId . '/promote'),
);

$selfDetail = $adminClient->get('/admin/users/' . $adminId);
check(
    'Administrator detail disables self-moderation',
    $selfDetail['status'] === 200
        && contains($selfDetail['body'], 'Self-moderation is disabled')
        && !contains($selfDetail['body'], '/admin/users/' . $adminId . '/suspend'),
);

$suspendSelf = $adminClient->post('/admin/users/' . $adminId . '/suspend', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
$selfAfter = $adminClient->get('/admin/users/' . $adminId);
check(
    'Administrator cannot suspend themselves',
    $suspendSelf['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM users WHERE id = ?', [$adminId])['status'] ?? '') === 'active'
        && contains($selfAfter['body'], 'cannot change your own account status'),
);

$suspend = $adminClient->post('/admin/users/' . $userId . '/suspend', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
check(
    'Administrator can suspend a normal account',
    $suspend['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM users WHERE id = ?', [$userId])['status'] ?? '') === 'suspended',
);
check(
    'Suspension revokes the target sessions',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$userId]) === 0,
);
check(
    'Suspension revokes remember-me tokens',
    scalar($pdo, 'SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?', [$userId]) === 0,
);

$suspendedDashboard = $userClient->get('/dashboard');
check(
    'A suspended user session is rejected immediately',
    $suspendedDashboard['status'] === 302
        && str_ends_with((string) ($suspendedDashboard['headers']['location'] ?? ''), '/login'),
);
$suspendedLogin = loginAccount(new HttpClient($baseUrl), $userEmail, $password);
check(
    'A suspended account cannot sign in',
    $suspendedLogin['status'] === 403 && contains($suspendedLogin['body'], 'suspended'),
);

$activate = $adminClient->post('/admin/users/' . $userId . '/activate', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
check(
    'Administrator can reactivate an account',
    $activate['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM users WHERE id = ?', [$userId])['status'] ?? '') === 'active',
);
check(
    'A reactivated account can sign in again',
    loginAccount($userClient, $userEmail, $password)['status'] === 302,
);

$promote = $adminClient->post('/admin/users/' . $userId . '/promote', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
check(
    'Administrator can promote an account',
    $promote['status'] === 302
        && (string) (row($pdo, 'SELECT role FROM users WHERE id = ?', [$userId])['role'] ?? '') === 'admin',
);
check(
    'A promoted account can open the admin dashboard',
    $userClient->get('/admin')['status'] === 200,
);

$demoteSelf = $adminClient->post('/admin/users/' . $adminId . '/demote', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
$selfAfterDemote = $adminClient->get('/admin/users/' . $adminId);
check(
    'Administrator cannot demote themselves',
    $demoteSelf['status'] === 302
        && (string) (row($pdo, 'SELECT role FROM users WHERE id = ?', [$adminId])['role'] ?? '') === 'admin'
        && contains($selfAfterDemote['body'], 'cannot change your own administrator role'),
);

$demote = $adminClient->post('/admin/users/' . $userId . '/demote', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
check(
    'Administrator can demote another administrator',
    $demote['status'] === 302
        && (string) (row($pdo, 'SELECT role FROM users WHERE id = ?', [$userId])['role'] ?? '') === 'user',
);
check(
    'A demoted account loses admin access',
    $userClient->get('/admin')['status'] === 403,
);

$lastAdmin = (new AdminService())->setRole(
    ['id' => 999999, 'role' => 'admin'],
    $adminId,
    'user',
    '127.0.0.1',
);
check(
    'The last administrator cannot be demoted (service invariant)',
    ($lastAdmin['ok'] ?? true) === false
        && ($lastAdmin['status'] ?? 0) === 409
        && contains((string) ($lastAdmin['message'] ?? ''), 'last administrator'),
);

check(
    'Moderation actions are recorded in the audit trail',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE target_type = 'user' AND target_id = ? AND action IN ('user.suspended','user.activated','user.promoted','user.demoted')",
        [$userId],
    ) === 4,
);

echo "\n4. Category CRUD and quest integration\n";

$categoryList = $adminClient->get('/admin/categories');
check(
    'Category catalogue lists the seeded categories',
    $categoryList['status'] === 200
        && contains($categoryList['body'], '>Work<')
        && contains($categoryList['body'], '>Study<')
        && contains($categoryList['body'], '8 categories'),
);
check(
    'Category form page renders',
    $adminClient->get('/admin/categories/create')['status'] === 200,
);

$invalidCategory = $adminClient->post('/admin/categories', [
    '_csrf_token' => csrfFor($adminClient, '/admin/categories/create'),
    'name' => 'A',
    'slug' => 'INVALID SLUG',
    'color' => 'red',
    'icon' => 'Bad Icon',
    'sort_order' => '-2',
    'is_active' => '1',
]);
check(
    'Invalid category input returns 422 with field errors',
    $invalidCategory['status'] === 422
        && contains($invalidCategory['body'], 'name-error')
        && contains($invalidCategory['body'], 'slug-error')
        && contains($invalidCategory['body'], 'color-error')
        && contains($invalidCategory['body'], 'icon-error')
        && contains($invalidCategory['body'], 'sort_order-error'),
);
check(
    'Invalid category input creates no row',
    scalar($pdo, 'SELECT COUNT(*) FROM categories WHERE slug = ?', ['invalid-slug']) === 0,
);

$duplicateCategory = $adminClient->post('/admin/categories', [
    '_csrf_token' => csrfFor($adminClient, '/admin/categories/create'),
    'name' => 'Work Duplicate',
    'slug' => 'work',
    'color' => '#FACC15',
    'icon' => 'briefcase',
    'sort_order' => '5',
    'is_active' => '1',
]);
check(
    'Duplicate category slug is rejected',
    $duplicateCategory['status'] === 422 && contains($duplicateCategory['body'], 'slug-error'),
);

$categoryName = 'Deep Work ' . $suffix;
$createCategory = $adminClient->post('/admin/categories', [
    '_csrf_token' => csrfFor($adminClient, '/admin/categories/create'),
    'name' => $categoryName,
    'slug' => '',
    'color' => '#F59E0B',
    'icon' => 'briefcase',
    'sort_order' => '25',
    'is_active' => '1',
]);
$category = row($pdo, 'SELECT * FROM categories WHERE name = ?', [$categoryName]);
$categoryId = (int) ($category['id'] ?? 0);

if ($categoryId > 0) {
    $createdCategoryIds[] = $categoryId;
}

check(
    'Valid category creation redirects to the catalogue',
    $createCategory['status'] === 302
        && str_ends_with((string) ($createCategory['headers']['location'] ?? ''), '/admin/categories'),
);
check(
    'Category row is persisted with a generated slug',
    $categoryId > 0 && (string) ($category['slug'] ?? '') === 'deep-work-' . $suffix,
);
check(
    'Category creation is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'category.created' AND target_id = ?",
        [$categoryId],
    ) === 1,
);

$questForm = $userClient->get('/quests/create');
check(
    'Quest form offers active categories',
    $questForm['status'] === 200
        && contains($questForm['body'], $categoryName)
        && contains($questForm['body'], 'value="' . $categoryId . '"'),
);

$questTitle = 'Categorised quest ' . $suffix;
$questId = createQuest($userClient, $questTitle, $categoryId);
$questRow = row($pdo, 'SELECT * FROM quests WHERE id = ?', [$questId]);
$questId > 0 && $createdQuestIds[] = $questId;
check(
    'Quest is created with the selected category',
    $questId > 0 && (int) ($questRow['category_id'] ?? 0) === $categoryId,
);

$filteredQuests = $userClient->get('/quests?status=active&category=' . rawurlencode('deep-work-' . $suffix));
check(
    'Quest board filters by category slug',
    $filteredQuests['status'] === 200 && contains($filteredQuests['body'], $questTitle),
);
$otherCategory = $userClient->get('/quests?status=active&category=work');
check(
    'Quest board excludes quests from other categories',
    $otherCategory['status'] === 200 && !contains($otherCategory['body'], $questTitle),
);

$questDetail = $userClient->get('/quests/' . $questId);
check(
    'Quest detail shows its category metadata',
    $questDetail['status'] === 200
        && contains($questDetail['body'], $categoryName)
        && contains($questDetail['body'], '#F59E0B'),
);

$updatedName = 'Deep Work Updated ' . $suffix;
$updateCategory = $adminClient->post('/admin/categories/' . $categoryId, [
    '_method' => 'PUT',
    '_csrf_token' => csrfFor($adminClient, '/admin/categories/' . $categoryId . '/edit'),
    'name' => $updatedName,
    'slug' => 'deep-work-' . $suffix,
    'color' => '#DC2626',
    'icon' => 'briefcase',
    'sort_order' => '26',
    'is_active' => '0',
]);
$updatedCategory = row($pdo, 'SELECT * FROM categories WHERE id = ?', [$categoryId]);
check(
    'Category update persists the new values',
    $updateCategory['status'] === 302
        && (string) ($updatedCategory['name'] ?? '') === $updatedName
        && (string) ($updatedCategory['color'] ?? '') === '#DC2626'
        && (int) ($updatedCategory['is_active'] ?? 1) === 0,
);
check(
    'Category update is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'category.updated' AND target_id = ?",
        [$categoryId],
    ) === 1,
);

$inactiveForm = $userClient->get('/quests/create');
check(
    'Inactive categories disappear from the quest form',
    !contains($inactiveForm['body'], $updatedName),
);
$inactiveDetail = $userClient->get('/quests/' . $questId);
check(
    'Existing quests keep their category after deactivation',
    $inactiveDetail['status'] === 200 && contains($inactiveDetail['body'], $updatedName),
);

$deleteCategory = $adminClient->post('/admin/categories/' . $categoryId . '/delete', [
    '_csrf_token' => csrfFor($adminClient, '/admin/categories'),
]);
$deletedCategory = row($pdo, 'SELECT * FROM categories WHERE id = ?', [$categoryId]);
$questAfterDelete = row($pdo, 'SELECT * FROM quests WHERE id = ?', [$questId]);
check(
    'Category deletion removes the category',
    $deleteCategory['status'] === 302 && $deletedCategory === null,
);
check(
    'Category deletion keeps quests and clears their category',
    $questAfterDelete !== null && $questAfterDelete['category_id'] === null,
);
check(
    'Category deletion is audited with the uncategorised quest count',
    (static function () use ($pdo, $categoryId): bool {
        $entry = row(
            $pdo,
            "SELECT metadata FROM audit_logs WHERE action = 'category.deleted' AND target_id = ?",
            [$categoryId],
        );

        if ($entry === null) {
            return false;
        }

        $metadata = json_decode((string) $entry['metadata'], true);

        return is_array($metadata) && ($metadata['quests_uncategorised'] ?? null) === 1;
    })(),
);

echo "\n5. Achievement CRUD and deletion protection\n";

$achievementList = $adminClient->get('/admin/achievements');
check(
    'Achievement catalogue lists seeded definitions and criteria types',
    $achievementList['status'] === 200
        && contains($achievementList['body'], 'First Steps')
        && contains($achievementList['body'], 'Achievement definitions')
        && contains($achievementList['body'], 'criteria types'),
);

$invalidAchievement = $adminClient->post('/admin/achievements', [
    '_csrf_token' => csrfFor($adminClient, '/admin/achievements/create'),
    'achievement_key' => 'BAD KEY',
    'name' => 'X',
    'description' => 'short',
    'icon' => 'Bad Icon',
    'category' => 'unknown',
    'criteria_type' => 'made_up',
    'threshold' => '0',
    'sort_order' => '-1',
    'is_active' => '1',
]);
check(
    'Invalid achievement input returns 422 with field errors',
    $invalidAchievement['status'] === 422
        && contains($invalidAchievement['body'], 'achievement_key-error')
        && contains($invalidAchievement['body'], 'name-error')
        && contains($invalidAchievement['body'], 'description-error')
        && contains($invalidAchievement['body'], 'category-error')
        && contains($invalidAchievement['body'], 'criteria_type-error')
        && contains($invalidAchievement['body'], 'threshold-error'),
);
check(
    'Unsupported criteria types create no definition',
    scalar($pdo, "SELECT COUNT(*) FROM achievements WHERE achievement_key = 'bad-key'") === 0,
);

$achievementKey = 'phase6_badge_' . $suffix;
$achievementName = 'Phase Six Badge ' . $suffix;
$createAchievement = $adminClient->post('/admin/achievements', [
    '_csrf_token' => csrfFor($adminClient, '/admin/achievements/create'),
    'achievement_key' => $achievementKey,
    'name' => $achievementName,
    'description' => 'A Phase 6 achievement definition created by the test suite.',
    'icon' => 'star',
    'category' => 'milestone',
    'criteria_type' => 'xp_total',
    'threshold' => '100',
    'sort_order' => '99',
    'is_active' => '1',
]);
$achievement = row($pdo, 'SELECT * FROM achievements WHERE achievement_key = ?', [$achievementKey]);
$achievementId = (int) ($achievement['id'] ?? 0);

if ($achievementId > 0) {
    $createdAchievementIds[] = $achievementId;
}

check(
    'Valid achievement creation redirects to its catalogue',
    $createAchievement['status'] === 302
        && str_ends_with((string) ($createAchievement['headers']['location'] ?? ''), '/admin/achievements'),
);
check(
    'Achievement definition is persisted',
    $achievementId > 0
        && (string) ($achievement['criteria_type'] ?? '') === 'xp_total'
        && (int) ($achievement['threshold'] ?? 0) === 100,
);
check(
    'Achievement creation is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'achievement.created' AND target_id = ?",
        [$achievementId],
    ) === 1,
);

$updateAchievement = $adminClient->post('/admin/achievements/' . $achievementId, [
    '_method' => 'PUT',
    '_csrf_token' => csrfFor($adminClient, '/admin/achievements/' . $achievementId . '/edit'),
    'achievement_key' => $achievementKey,
    'name' => $achievementName . ' Updated',
    'description' => 'An updated Phase 6 achievement definition used by the test suite.',
    'icon' => 'star',
    'category' => 'xp',
    'criteria_type' => 'xp_total',
    'threshold' => '250',
    'sort_order' => '99',
    'is_active' => '0',
]);
$updatedAchievement = row($pdo, 'SELECT * FROM achievements WHERE id = ?', [$achievementId]);
check(
    'Achievement update persists its threshold and active state',
    $updateAchievement['status'] === 302
        && (int) ($updatedAchievement['threshold'] ?? 0) === 250
        && (int) ($updatedAchievement['is_active'] ?? 1) === 0
        && (string) ($updatedAchievement['category'] ?? '') === 'xp',
);
check(
    'Achievement update is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'achievement.updated' AND target_id = ?",
        [$achievementId],
    ) === 1,
);

$userAchievements = $userClient->get('/achievements');
check(
    'Deactivated achievements are hidden from adventurers',
    $userAchievements['status'] === 200 && !contains($userAchievements['body'], $achievementName . ' Updated'),
);

$pdo->prepare(
    'INSERT INTO user_achievements (user_id, achievement_id, unlocked_at)
     VALUES (?, ?, UTC_TIMESTAMP())
     ON DUPLICATE KEY UPDATE unlocked_at = unlocked_at',
)->execute([$userId, $firstStepsId]);

$deleteUnlocked = $adminClient->post('/admin/achievements/' . $firstStepsId . '/delete', [
    '_csrf_token' => csrfFor($adminClient, '/admin/achievements'),
]);
$achievementAfterBlockedDelete = $adminClient->get('/admin/achievements');
check(
    'An achievement with unlocks cannot be deleted',
    $deleteUnlocked['status'] === 302
        && row($pdo, 'SELECT * FROM achievements WHERE id = ?', [$firstStepsId]) !== null
        && contains($achievementAfterBlockedDelete['body'], 'Deactivate it instead of deleting it'),
);
check(
    'A blocked achievement deletion writes no audit entry',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'achievement.deleted' AND target_id = ?",
        [$firstStepsId],
    ) === 0,
);

$deleteAchievement = $adminClient->post('/admin/achievements/' . $achievementId . '/delete', [
    '_csrf_token' => csrfFor($adminClient, '/admin/achievements'),
]);
check(
    'An unlocked-free achievement can be deleted',
    $deleteAchievement['status'] === 302
        && row($pdo, 'SELECT * FROM achievements WHERE id = ?', [$achievementId]) === null,
);
check(
    'Achievement deletion is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'achievement.deleted' AND target_id = ?",
        [$achievementId],
    ) === 1,
);

echo "\n6. Audit trail\n";

$auditPage = $adminClient->get('/admin/audit');
check(
    'Audit log renders privileged actions',
    $auditPage['status'] === 200
        && contains($auditPage['body'], 'Audit log')
        && contains($auditPage['body'], 'user.suspended')
        && contains($auditPage['body'], 'category.created')
        && contains($auditPage['body'], 'achievement.created'),
);

$auditByAction = $adminClient->get('/admin/audit?action=user.suspended');
check(
    'Audit log filters by action',
    $auditByAction['status'] === 200
        && contains($auditByAction['body'], 'Suspended Phase Six User')
        && !contains($auditByAction['body'], 'Created category'),
);

$auditByTarget = $adminClient->get('/admin/audit?target_type=category');
check(
    'Audit log filters by target type',
    $auditByTarget['status'] === 200
        && contains($auditByTarget['body'], 'Created category')
        && !contains($auditByTarget['body'], 'Suspended Phase Six User'),
);

$auditByActor = $adminClient->get('/admin/audit?actor=' . rawurlencode($adminUsername));
check(
    'Audit log filters by actor',
    $auditByActor['status'] === 200 && contains($auditByActor['body'], 'Created category'),
);

$auditUnknownActor = $adminClient->get('/admin/audit?actor=missing-' . $suffix);
check(
    'Audit log returns an empty state for an unknown actor',
    $auditUnknownActor['status'] === 200
        && contains($auditUnknownActor['body'], 'No audit entries match'),
);

$auditInvalidTarget = $adminClient->get('/admin/audit?target_type=not-a-target');
check(
    'Audit log ignores unknown target types and still lists entries',
    $auditInvalidTarget['status'] === 200 && contains($auditInvalidTarget['body'], 'Created category'),
);

check(
    'Audit log exposes decoded JSON metadata',
    contains($auditPage['body'], 'quests_uncategorised'),
);
check(
    'Audit log page shows pagination controls',
    contains($auditPage['body'], 'Page 1 /') && contains($auditPage['body'], 'aria-label="Pagination"'),
);
check(
    'AuditLog model is append-only',
    !method_exists(AuditLog::class, 'update') && !method_exists(AuditLog::class, 'delete'),
);
check(
    'Achievement definitions are read back through the admin model',
    Achievement::findById($firstStepsId) !== null,
);

echo "\n7. CSRF protection on admin mutations\n";

$categoryCountBefore = scalar($pdo, 'SELECT COUNT(*) FROM categories');
$noCsrfCategory = $adminClient->post('/admin/categories', [
    'name' => 'No CSRF ' . $suffix,
    'slug' => 'no-csrf-' . $suffix,
    'color' => '#FACC15',
    'icon' => 'folder',
    'sort_order' => '1',
    'is_active' => '1',
]);
check(
    'Category creation without a CSRF token returns 419 and writes nothing',
    $noCsrfCategory['status'] === 419
        && scalar($pdo, 'SELECT COUNT(*) FROM categories') === $categoryCountBefore,
);

$noCsrfSuspend = $adminClient->post('/admin/users/' . $userId . '/suspend', []);
check(
    'Suspension without a CSRF token returns 419 and changes nothing',
    $noCsrfSuspend['status'] === 419
        && (string) (row($pdo, 'SELECT status FROM users WHERE id = ?', [$userId])['status'] ?? '') === 'active',
);

$noCsrfAchievement = $adminClient->post('/admin/achievements', [
    'achievement_key' => 'no_csrf_' . $suffix,
    'name' => 'No CSRF',
    'description' => 'This definition must never be created.',
    'icon' => 'star',
    'category' => 'xp',
    'criteria_type' => 'xp_total',
    'threshold' => '1',
    'sort_order' => '1',
    'is_active' => '1',
]);
check(
    'Achievement creation without a CSRF token returns 419',
    $noCsrfAchievement['status'] === 419
        && scalar($pdo, 'SELECT COUNT(*) FROM achievements WHERE achievement_key = ?', ['no_csrf_' . $suffix]) === 0,
);

$noCsrfUpdate = $adminClient->post('/admin/categories/' . $workId, [
    '_method' => 'PUT',
    'name' => 'Compromised',
    'slug' => 'work',
    'color' => '#DC2626',
    'icon' => 'briefcase',
    'sort_order' => '10',
    'is_active' => '1',
]);
check(
    'Category update without a CSRF token returns 419 and preserves the row',
    $noCsrfUpdate['status'] === 419
        && (string) (row($pdo, 'SELECT name FROM categories WHERE id = ?', [$workId])['name'] ?? '') === 'Work',
);

echo "\n8. Cleanup\n";

$pdo->prepare('DELETE FROM user_achievements WHERE user_id = ? AND achievement_id = ?')->execute([$userId, $firstStepsId]);
$pdo->prepare('DELETE FROM audit_logs WHERE actor_user_id IN (?, ?)')->execute([$adminId, $userId]);
$pdo->prepare('DELETE FROM users WHERE id IN (?, ?)')->execute([$adminId, $userId]);

$categoryCleanup = array_values(array_filter($createdCategoryIds, static fn (int $id): bool => $id > 0));
$achievementCleanup = array_values(array_filter($createdAchievementIds, static fn (int $id): bool => $id > 0));

if ($categoryCleanup !== []) {
    $placeholders = implode(', ', array_fill(0, count($categoryCleanup), '?'));
    $pdo->prepare("DELETE FROM categories WHERE id IN ({$placeholders})")->execute($categoryCleanup);
}

if ($achievementCleanup !== []) {
    $placeholders = implode(', ', array_fill(0, count($achievementCleanup), '?'));
    $pdo->prepare("DELETE FROM achievements WHERE id IN ({$placeholders})")->execute($achievementCleanup);
}

cleanupLoginAttempts($pdo, $identifiers);

check(
    'Phase 6 accounts are removed',
    scalar($pdo, 'SELECT COUNT(*) FROM users WHERE id IN (?, ?)', [$adminId, $userId]) === 0,
);
check(
    'Phase 6 audit entries are removed',
    scalar($pdo, 'SELECT COUNT(*) FROM audit_logs WHERE actor_user_id IN (?, ?)', [$adminId, $userId]) === 0,
);
check(
    'Seeded categories survive cleanup',
    scalar($pdo, 'SELECT COUNT(*) FROM categories WHERE slug = ?', ['work']) === 1,
);

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

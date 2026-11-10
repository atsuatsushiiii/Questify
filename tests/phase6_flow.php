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

function reportCsrf(HttpClient $client, string $targetType, int $targetId): ?string
{
    $page = $client->get('/reports/new?target_type=' . rawurlencode($targetType) . '&target_id=' . $targetId);

    return $client->csrf($page['body']);
}

function submitReport(HttpClient $client, string $targetType, int $targetId, string $reason, string $details): array
{
    return $client->post('/reports', [
        '_csrf_token' => reportCsrf($client, $targetType, $targetId),
        'target_type' => $targetType,
        'target_id' => (string) $targetId,
        'reason' => $reason,
        'details' => $details,
    ]);
}

function reportQueueLink(string $baseUrl, int $reportId): string
{
    return 'href="' . $baseUrl . '/admin/reports/' . $reportId . '"';
}

$pdo = Database::connection();
$suffix = bin2hex(random_bytes(4));
$createdUsers = [];
$createdCategoryIds = [];
$createdAchievementIds = [];
$createdQuestIds = [];
$createdReportIds = [];
$identifiers = [];

$adminUsername = 'phase6_admin_' . $suffix;
$adminEmail = 'phase6_admin_' . $suffix . '@example.test';
$userUsername = 'phase6_user_' . $suffix;
$userEmail = 'phase6_user_' . $suffix . '@example.test';
$reportTargetUsername = 'phase6_report_' . $suffix;
$reportTargetEmail = 'phase6_report_' . $suffix . '@example.test';
$password = 'PhaseSix-' . $suffix . '9';
$identifiers[] = $adminEmail;
$identifiers[] = $userEmail;
$identifiers[] = $reportTargetEmail;

$adminClient = new HttpClient($baseUrl);
$userClient = new HttpClient($baseUrl);
$reportTargetClient = new HttpClient($baseUrl);
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
$guestQuests = $guestClient->get('/admin/quests');
check(
    'Guest is redirected from /admin/quests to sign in',
    $guestQuests['status'] === 302 && str_ends_with((string) ($guestQuests['headers']['location'] ?? ''), '/login'),
);
$guestReports = $guestClient->get('/admin/reports');
check(
    'Guest is redirected from /admin/reports to sign in',
    $guestReports['status'] === 302 && str_ends_with((string) ($guestReports['headers']['location'] ?? ''), '/login'),
);
$guestReportForm = $guestClient->get('/reports/new?target_type=profile&target_id=' . $userId);
check(
    'Guest is redirected from the report form to sign in',
    $guestReportForm['status'] === 302
        && str_ends_with((string) ($guestReportForm['headers']['location'] ?? ''), '/login'),
);
$guestMyReports = $guestClient->get('/reports');
check(
    'Guest is redirected from My Reports to sign in',
    $guestMyReports['status'] === 302
        && str_ends_with((string) ($guestMyReports['headers']['location'] ?? ''), '/login'),
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
    '/admin/quests',
    '/admin/quests/999999',
    '/admin/reports',
    '/admin/reports/999999',
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
    ['POST', '/admin/quests/999999/archive', []],
    ['POST', '/admin/quests/999999/restore', []],
    ['POST', '/admin/quests/999999/delete', []],
    ['POST', '/admin/reports/999999/resolve', []],
    ['POST', '/admin/reports/999999/dismiss', []],
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
    'Administrator can suspend a normal account permanently by default',
    $suspend['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM users WHERE id = ?', [$userId])['status'] ?? '') === 'suspended'
        && (row($pdo, 'SELECT suspended_until FROM users WHERE id = ?', [$userId])['suspended_until'] ?? null) === null,
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

// Demo/seed data may include other administrators, so make the fixture the
// only one inside a rolled-back transaction. The invariant stays isolated from
// whatever accounts already exist on the machine.
$pdo->beginTransaction();

try {
    $pdo->prepare("UPDATE users SET role = 'user' WHERE role = 'admin' AND id <> ?")->execute([$adminId]);

    $lastAdmin = (new AdminService())->setRole(
        ['id' => 999999, 'role' => 'admin'],
        $adminId,
        'user',
        '127.0.0.1',
    );
} finally {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}
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

echo "\n5. Quest moderation\n";

$moderationTitle = 'Moderation quest ' . $suffix;
$moderationId = createQuest($userClient, $moderationTitle, $workId);

if ($moderationId > 0) {
    $createdQuestIds[] = $moderationId;
}

$questDirectory = $adminClient->get('/admin/quests');
check(
    'Quest directory lists platform quests with their owner and filters',
    $questDirectory['status'] === 200
        && contains($questDirectory['body'], $moderationTitle)
        && contains($questDirectory['body'], '@' . $userUsername)
        && contains($questDirectory['body'], 'name="difficulty"')
        && contains($questDirectory['body'], 'name="category"')
        && contains($questDirectory['body'], 'Quest moderation'),
);

$questSearch = $adminClient->get('/admin/quests?search=' . rawurlencode($moderationTitle));
check(
    'Quest search narrows the directory to the matching quest',
    $questSearch['status'] === 200
        && contains($questSearch['body'], $moderationTitle)
        && !contains($questSearch['body'], $questTitle),
);

$easyQuests = $adminClient->get('/admin/quests?difficulty=easy&search=' . rawurlencode($suffix));
check(
    'Difficulty filter keeps matching easy quests',
    $easyQuests['status'] === 200 && contains($easyQuests['body'], $moderationTitle),
);
$hardQuests = $adminClient->get('/admin/quests?difficulty=hard&search=' . rawurlencode($suffix));
check(
    'Difficulty filter excludes quests of another difficulty',
    $hardQuests['status'] === 200 && !contains($hardQuests['body'], $moderationTitle),
);

$questDetailPage = $adminClient->get('/admin/quests/' . $moderationId);
check(
    'Quest detail shows the definition, owner, XP ledger, and moderation history',
    $questDetailPage['status'] === 200
        && contains($questDetailPage['body'], $moderationTitle)
        && contains($questDetailPage['body'], '@' . $userUsername)
        && contains($questDetailPage['body'], 'Quest definition')
        && contains($questDetailPage['body'], 'XP ledger')
        && contains($questDetailPage['body'], 'Moderation history'),
);
check(
    'Quest detail offers archive and delete actions',
    contains($questDetailPage['body'], '/admin/quests/' . $moderationId . '/archive')
        && contains($questDetailPage['body'], '/admin/quests/' . $moderationId . '/delete'),
);

$completeModerated = $userClient->post('/quests/' . $moderationId . '/complete', [
    '_csrf_token' => csrfFor($userClient, '/quests/' . $moderationId),
]);
check(
    'The owner can complete a quest before it is moderated',
    $completeModerated['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$moderationId])['status'] ?? '') === 'completed',
);
check(
    'Completing the quest writes one XP ledger entry',
    scalar($pdo, 'SELECT COUNT(*) FROM xp_transactions WHERE quest_id = ?', [$moderationId]) === 1,
);

$archiveQuest = $adminClient->post('/admin/quests/' . $moderationId . '/archive', [
    '_csrf_token' => csrfFor($adminClient, '/admin/quests/' . $moderationId),
]);
check(
    'Administrator can archive a quest',
    $archiveQuest['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$moderationId])['status'] ?? '') === 'archived',
);

$activeBoard = $userClient->get('/quests?status=active');
check(
    'An archived quest disappears from the owner active board',
    $activeBoard['status'] === 200 && !contains($activeBoard['body'], $moderationTitle),
);
check(
    'Quest archive is recorded in the audit trail',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'quest.archived' AND target_type = 'quest' AND target_id = ?",
        [$moderationId],
    ) === 1,
);

$archivedDirectory = $adminClient->get('/admin/quests?status=archived&search=' . rawurlencode($moderationTitle));
check(
    'Status filter surfaces the archived quest',
    $archivedDirectory['status'] === 200 && contains($archivedDirectory['body'], $moderationTitle),
);

$restoreQuest = $adminClient->post('/admin/quests/' . $moderationId . '/restore', [
    '_csrf_token' => csrfFor($adminClient, '/admin/quests/' . $moderationId),
]);
check(
    'Administrator can restore an archived quest',
    $restoreQuest['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$moderationId])['status'] ?? '') === 'completed',
);
check(
    'A restored completed quest returns to completed, not active',
    (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$moderationId])['status'] ?? '') === 'completed',
);
$completedBoard = $userClient->get('/quests?status=completed');
check(
    'A restored completed quest reappears on the completed board',
    $completedBoard['status'] === 200 && contains($completedBoard['body'], $moderationTitle),
);
check(
    'Quest restore is recorded in the audit trail',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'quest.restored' AND target_type = 'quest' AND target_id = ?",
        [$moderationId],
    ) === 1,
);

$recurringTitle = 'Moderation recurring quest ' . $suffix;
$pdo->prepare(
    "INSERT INTO quests (user_id, category_id, title, description, difficulty, xp_reward, recurrence, status, completed_at, created_at, updated_at)
     VALUES (?, ?, ?, 'Recurring moderation coverage quest.', 'easy', 10, 'daily', 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())",
)->execute([$userId, $workId, $recurringTitle]);
$recurringId = (int) $pdo->lastInsertId();

$archiveRecurring = $adminClient->post('/admin/quests/' . $recurringId . '/archive', [
    '_csrf_token' => csrfFor($adminClient, '/admin/quests/' . $recurringId),
]);
$restoreRecurring = $adminClient->post('/admin/quests/' . $recurringId . '/restore', [
    '_csrf_token' => csrfFor($adminClient, '/admin/quests/' . $recurringId),
]);
check(
    'Restoring an archived recurring quest returns it to active',
    $archiveRecurring['status'] === 302
        && $restoreRecurring['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$recurringId])['status'] ?? '') === 'active',
);

$deleteQuest = $adminClient->post('/admin/quests/' . $moderationId . '/delete', [
    '_csrf_token' => csrfFor($adminClient, '/admin/quests/' . $moderationId),
]);
check(
    'Administrator can delete a quest',
    $deleteQuest['status'] === 302
        && str_ends_with((string) ($deleteQuest['headers']['location'] ?? ''), '/admin/quests')
        && row($pdo, 'SELECT * FROM quests WHERE id = ?', [$moderationId]) === null,
);

$preservedLedger = row(
    $pdo,
    'SELECT quest_id, quest_title, amount FROM xp_transactions WHERE quest_title = ? ORDER BY id DESC LIMIT 1',
    [$moderationTitle],
);
check(
    'Deleting a quest preserves its XP ledger history',
    $preservedLedger !== null
        && $preservedLedger['quest_id'] === null
        && (string) ($preservedLedger['quest_title'] ?? '') === $moderationTitle
        && (int) ($preservedLedger['amount'] ?? 0) === 10,
);

check(
    'Quest deletion is audited with the ledger snapshot',
    (static function () use ($pdo, $moderationId, $userId): bool {
        $entry = row(
            $pdo,
            "SELECT metadata FROM audit_logs WHERE action = 'quest.deleted' AND target_type = 'quest' AND target_id = ?",
            [$moderationId],
        );

        if ($entry === null) {
            return false;
        }

        $metadata = json_decode((string) $entry['metadata'], true);

        return is_array($metadata)
            && ($metadata['ledger_entries'] ?? null) === 1
            && ($metadata['ledger_xp'] ?? null) === 10
            && ($metadata['owner_id'] ?? null) === $userId;
    })(),
);

$missingQuest = $adminClient->get('/admin/quests/999999999');
check(
    'Unknown quest returns 404',
    $missingQuest['status'] === 404,
);

$csrfQuestTitle = 'Moderation CSRF quest ' . $suffix;
$csrfQuestId = createQuest($userClient, $csrfQuestTitle, $workId);
$noCsrfArchive = $adminClient->post('/admin/quests/' . $csrfQuestId . '/archive', []);
check(
    'Quest archive without a CSRF token returns 419 and changes nothing',
    $noCsrfArchive['status'] === 419
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$csrfQuestId])['status'] ?? '') === 'active',
);

echo "\n6. Achievement CRUD and deletion protection\n";

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

echo "\n7. Reports and flag queue\n";

$reportTargetRegistered = registerAccount(
    $reportTargetClient,
    'Phase Six Report Target ' . $suffix,
    $reportTargetUsername,
    $reportTargetEmail,
    $password,
);
$reportTarget = row($pdo, 'SELECT * FROM users WHERE email = ?', [$reportTargetEmail]);
$reportTargetId = (int) ($reportTarget['id'] ?? 0);

if ($reportTargetId > 0) {
    $createdUsers[] = $reportTargetId;
}

check(
    'Report target fixture registers',
    $reportTargetRegistered['status'] === 302 && $reportTargetId > 0,
);

$pdo->prepare("UPDATE users SET status = 'active', show_profile = 1 WHERE id IN (?, ?, ?)")
    ->execute([$adminId, $userId, $reportTargetId]);

$adminPublicProfile = $userClient->get('/u/' . rawurlencode($adminUsername));
check(
    'Signed-in visitors see a report link on public profiles',
    $adminPublicProfile['status'] === 200
        && contains($adminPublicProfile['body'], 'Report profile')
        && contains($adminPublicProfile['body'], '/reports/new?target_type=profile&amp;target_id=' . $adminId),
);

$guestPublicProfile = $guestClient->get('/u/' . rawurlencode($adminUsername));
check(
    'Guests do not see the report link',
    $guestPublicProfile['status'] === 200
        && !contains($guestPublicProfile['body'], 'Report profile'),
);

$selfReportPage = $adminClient->get('/reports/new?target_type=profile&target_id=' . $adminId);
check(
    'Administrators cannot open a report form for their own profile',
    $selfReportPage['status'] === 302
        && str_ends_with((string) ($selfReportPage['headers']['location'] ?? ''), '/u/' . $adminUsername),
);

$selfReport = $adminClient->post('/reports', [
    '_csrf_token' => csrfFor($adminClient, '/admin'),
    'target_type' => 'profile',
    'target_id' => (string) $adminId,
    'reason' => 'harassment',
    'details' => 'This self-report must be rejected.',
]);
check(
    'Self-reports are rejected',
    $selfReport['status'] === 302
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM reports WHERE target_type = 'profile' AND target_id = ?",
            [$adminId],
        ) === 0,
);

$missingReportTarget = $adminClient->get('/reports/new?target_type=profile&target_id=999999999');
check(
    'An unknown report target returns 404',
    $missingReportTarget['status'] === 404,
);

$pdo->prepare('UPDATE users SET show_profile = 0 WHERE id = ?')->execute([$reportTargetId]);
$hiddenReportTarget = $adminClient->get('/reports/new?target_type=profile&target_id=' . $reportTargetId);
check(
    'A hidden profile cannot be reported',
    $hiddenReportTarget['status'] === 404,
);
$pdo->prepare('UPDATE users SET show_profile = 1 WHERE id = ?')->execute([$reportTargetId]);

$reportForm = $userClient->get('/reports/new?target_type=profile&target_id=' . $adminId);
check(
    'The report form renders for a signed-in non-owner',
    $reportForm['status'] === 200
        && contains($reportForm['body'], 'Flag a profile')
        && contains($reportForm['body'], 'name="reason"')
        && contains($reportForm['body'], 'Harassment or bullying')
        && contains($reportForm['body'], 'name="target_id" value="' . $adminId . '"'),
);

$invalidReason = $userClient->post('/reports', [
    '_csrf_token' => csrfFor($userClient, '/reports/new?target_type=profile&target_id=' . $adminId),
    'target_type' => 'profile',
    'target_id' => (string) $adminId,
    'reason' => 'not-a-reason',
    'details' => '',
]);
check(
    'An unknown report reason returns 422 with a field error',
    $invalidReason['status'] === 422
        && contains($invalidReason['body'], 'reason-error')
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM reports WHERE reporter_user_id = ? AND target_id = ?",
            [$userId, $adminId],
        ) === 0,
);

$shortOther = $userClient->post('/reports', [
    '_csrf_token' => csrfFor($userClient, '/reports/new?target_type=profile&target_id=' . $adminId),
    'target_type' => 'profile',
    'target_id' => (string) $adminId,
    'reason' => 'other',
    'details' => 'short',
]);
check(
    'The “Something else” reason requires a useful description',
    $shortOther['status'] === 422 && contains($shortOther['body'], 'details-error'),
);

$userReport = submitReport(
    $userClient,
    'profile',
    $adminId,
    'harassment',
    'The profile bio contains repeated personal attacks against another adventurer.',
);
$userReportRow = row(
    $pdo,
    'SELECT * FROM reports WHERE reporter_user_id = ? AND target_type = ? AND target_id = ?',
    [$userId, 'profile', $adminId],
);
$userReportId = (int) ($userReportRow['id'] ?? 0);

if ($userReportId > 0) {
    $createdReportIds[] = $userReportId;
}

check(
    'A signed-in user can report a public profile',
    $userReport['status'] === 302
        && str_ends_with((string) ($userReport['headers']['location'] ?? ''), '/u/' . $adminUsername)
        && $userReportId > 0
        && (string) ($userReportRow['status'] ?? '') === 'open'
        && contains((string) ($userReportRow['target_label'] ?? ''), '@' . $adminUsername),
);

$duplicateReport = $userClient->post('/reports', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'target_type' => 'profile',
    'target_id' => (string) $adminId,
    'reason' => 'spam',
    'details' => 'A second report for the same target must not be stored.',
]);
check(
    'Duplicate reports are rejected',
    $duplicateReport['status'] === 302
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM reports WHERE reporter_user_id = ? AND target_type = 'profile' AND target_id = ?",
            [$userId, $adminId],
        ) === 1,
);

$existingReportForm = $userClient->get('/reports/new?target_type=profile&target_id=' . $adminId);
check(
    'The report form shows the existing report instead of a duplicate form',
    $existingReportForm['status'] === 200
        && contains($existingReportForm['body'], 'You already reported this profile')
        && !contains($existingReportForm['body'], 'name="reason"'),
);

$targetReport = submitReport(
    $adminClient,
    'profile',
    $reportTargetId,
    'spam',
    'This profile repeatedly posts the same promotional link.',
);
$targetReportRow = row(
    $pdo,
    'SELECT * FROM reports WHERE reporter_user_id = ? AND target_type = ? AND target_id = ?',
    [$adminId, 'profile', $reportTargetId],
);
$targetReportId = (int) ($targetReportRow['id'] ?? 0);

if ($targetReportId > 0) {
    $createdReportIds[] = $targetReportId;
}

check(
    'An administrator can also file a report',
    $targetReport['status'] === 302
        && $targetReportId > 0
        && (string) ($targetReportRow['reason'] ?? '') === 'spam',
);

$myReports = $userClient->get('/reports');
check(
    'The reporter sees their own report history and no one else’s',
    $myReports['status'] === 200
        && contains($myReports['body'], 'My reports')
        && contains($myReports['body'], 'Harassment or bullying')
        && contains($myReports['body'], '@' . $adminUsername)
        && contains($myReports['body'], 'Report #' . $userReportId . ' · submitted')
        && !contains($myReports['body'], 'Report #' . $targetReportId . ' · submitted'),
);

$targetReportDetail = $adminClient->get('/admin/reports/' . $targetReportId);
check(
    'An open profile report offers warning and timed-suspension actions',
    $targetReportDetail['status'] === 200
        && contains($targetReportDetail['body'], 'Account actions')
        && contains($targetReportDetail['body'], 'Send warning')
        && contains($targetReportDetail['body'], 'Suspend account')
        && contains($targetReportDetail['body'], 'name="duration"')
        && contains($targetReportDetail['body'], '>1 day</option>')
        && contains($targetReportDetail['body'], '>2 days</option>')
        && contains($targetReportDetail['body'], '>30 days</option>')
        && contains($targetReportDetail['body'], '>Permanent</option>'),
);

$warnNoCsrf = $adminClient->post('/admin/reports/' . $targetReportId . '/warn', [
    'message' => 'Missing CSRF must be rejected.',
]);
check(
    'Account actions without a CSRF token return 419 and change nothing',
    $warnNoCsrf['status'] === 419
        && scalar(
            $pdo,
            'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND event_key = ?',
            [$reportTargetId, 'moderation.warning:report:' . $targetReportId],
        ) === 0,
);

$warningMessage = 'Please remove the promotional links from your profile.';
$warnReported = $adminClient->post('/admin/reports/' . $targetReportId . '/warn', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $targetReportId),
    'message' => $warningMessage,
]);
$warningRow = row(
    $pdo,
    'SELECT * FROM notifications WHERE user_id = ? AND event_key = ?',
    [$reportTargetId, 'moderation.warning:report:' . $targetReportId],
);
check(
    'A warning from a report notifies the reported account without identifying the reporter',
    $warnReported['status'] === 302
        && str_ends_with((string) ($warnReported['headers']['location'] ?? ''), '/admin/reports/' . $targetReportId)
        && $warningRow !== null
        && (string) ($warningRow['type'] ?? '') === 'warning'
        && (string) ($warningRow['title'] ?? '') === 'Moderation warning'
        && contains((string) ($warningRow['message'] ?? ''), 'Spam or misleading')
        && contains((string) ($warningRow['message'] ?? ''), $warningMessage)
        && !contains((string) ($warningRow['message'] ?? ''), $adminUsername)
        && (string) ($warningRow['url'] ?? '') === '/notifications',
);
check(
    'The warning is audited against both the report and the account',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'user.warned' AND target_type = 'user' AND target_id = ?
           AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.report_id')) = ?",
        [$reportTargetId, (string) $targetReportId],
    ) === 1,
);

$suspendReported = $adminClient->post('/admin/reports/' . $targetReportId . '/suspend', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $targetReportId),
    'duration' => '2',
    'message' => 'Two days to review the community guidelines.',
]);
$suspendedTarget = row($pdo, 'SELECT * FROM users WHERE id = ?', [$reportTargetId]);
$suspendedUntil = isset($suspendedTarget['suspended_until']) ? (string) $suspendedTarget['suspended_until'] : '';
$suspendedAt = $suspendedUntil !== '' ? strtotime($suspendedUntil . ' UTC') : false;
$reportAfterSuspend = row($pdo, 'SELECT * FROM reports WHERE id = ?', [$targetReportId]);
check(
    'A timed suspension from a report sets the expiry and keeps the report open',
    $suspendReported['status'] === 302
        && (string) ($suspendedTarget['status'] ?? '') === 'suspended'
        && $suspendedAt !== false
        && $suspendedAt > time() + (2 * 86400) - 120
        && $suspendedAt < time() + (2 * 86400) + 120
        && (string) ($reportAfterSuspend['status'] ?? '') === 'open',
);
$suspensionNotice = row(
    $pdo,
    'SELECT * FROM notifications WHERE user_id = ? AND type = ? ORDER BY id DESC LIMIT 1',
    [$reportTargetId, 'moderation'],
);
check(
    'The suspended account is told why and until when',
    $suspensionNotice !== null
        && (string) ($suspensionNotice['type'] ?? '') === 'moderation'
        && (string) ($suspensionNotice['title'] ?? '') === 'Account suspended'
        && contains((string) ($suspensionNotice['message'] ?? ''), 'suspended until')
        && contains((string) ($suspensionNotice['message'] ?? ''), 'Spam or misleading')
        && contains((string) ($suspensionNotice['message'] ?? ''), 'Two days to review the community guidelines.')
        && (string) ($suspensionNotice['url'] ?? '') === '/notifications',
);
check(
    'Suspension from a report revokes sessions and remember-me tokens',
    scalar($pdo, 'SELECT COUNT(*) FROM sessions WHERE user_id = ?', [$reportTargetId]) === 0
        && scalar($pdo, 'SELECT COUNT(*) FROM remember_tokens WHERE user_id = ?', [$reportTargetId]) === 0,
);
check(
    'The suspension is audited with its report, duration, and expiry',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'user.suspended' AND target_type = 'user' AND target_id = ?
           AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.report_id')) = ?
           AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.duration')) = '2'
           AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.suspended_until')) IS NOT NULL",
        [$reportTargetId, (string) $targetReportId],
    ) === 1,
);

$blockedReportClient = new HttpClient($baseUrl);
$blockedLogin = loginAccount($blockedReportClient, $reportTargetEmail, $password);
check(
    'A temporarily suspended account cannot sign in before the expiry',
    $blockedLogin['status'] === 403 && contains($blockedLogin['body'], 'suspended'),
);

$reactivateTarget = $adminClient->post('/admin/users/' . $reportTargetId . '/activate', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $reportTargetId),
]);
$reactivatedTarget = row($pdo, 'SELECT * FROM users WHERE id = ?', [$reportTargetId]);
check(
    'Activating an account clears its suspension expiry',
    $reactivateTarget['status'] === 302
        && (string) ($reactivatedTarget['status'] ?? '') === 'active'
        && $reactivatedTarget['suspended_until'] === null,
);

$invalidDuration = $adminClient->post('/admin/reports/' . $targetReportId . '/suspend', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $targetReportId),
    'duration' => '999',
]);
$invalidDurationPage = $adminClient->get('/admin/reports/' . $targetReportId);
check(
    'An unknown suspension length is rejected without changing the account',
    $invalidDuration['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM users WHERE id = ?', [$reportTargetId])['status'] ?? '') === 'active'
        && contains($invalidDurationPage['body'], 'Choose a valid suspension length.'),
);

$pdo->prepare(
    "UPDATE users SET status = 'suspended', suspended_until = UTC_TIMESTAMP() - INTERVAL 1 MINUTE WHERE id = ?",
)->execute([$reportTargetId]);
$expiredProfile = $guestClient->get('/u/' . rawurlencode($reportTargetUsername));
$expiredTarget = row($pdo, 'SELECT * FROM users WHERE id = ?', [$reportTargetId]);
check(
    'An expired timed suspension is lifted on the next public profile visit',
    $expiredProfile['status'] === 200
        && (string) ($expiredTarget['status'] ?? '') === 'active'
        && $expiredTarget['suspended_until'] === null,
);

$queue = $adminClient->get('/admin/reports');
check(
    'The reports queue lists open reports and filters',
    $queue['status'] === 200
        && contains($queue['body'], 'Reports queue')
        && contains($queue['body'], 'name="search"')
        && contains($queue['body'], 'name="status"')
        && contains($queue['body'], 'name="target_type"')
        && contains($queue['body'], 'name="reason"')
        && contains($queue['body'], reportQueueLink($baseUrl, $userReportId))
        && contains($queue['body'], '@' . $userUsername),
);
check(
    'The queue shows the report reason and target snapshot',
    contains($queue['body'], 'Harassment or bullying')
        && contains($queue['body'], 'Spam or misleading')
        && contains($queue['body'], '@' . $reportTargetUsername),
);

$openQueue = $adminClient->get('/admin/reports?status=open');
check(
    'The status filter keeps open reports visible',
    $openQueue['status'] === 200
        && contains($openQueue['body'], reportQueueLink($baseUrl, $userReportId))
        && contains($openQueue['body'], reportQueueLink($baseUrl, $targetReportId)),
);

$spamQueue = $adminClient->get('/admin/reports?reason=spam');
check(
    'The reason filter narrows the queue',
    $spamQueue['status'] === 200
        && contains($spamQueue['body'], reportQueueLink($baseUrl, $targetReportId))
        && !contains($spamQueue['body'], reportQueueLink($baseUrl, $userReportId)),
);

$profileQueue = $adminClient->get('/admin/reports?target_type=profile');
check(
    'The target-type filter accepts profile reports',
    $profileQueue['status'] === 200
        && contains($profileQueue['body'], reportQueueLink($baseUrl, $userReportId))
        && contains($profileQueue['body'], reportQueueLink($baseUrl, $targetReportId)),
);

$searchQueue = $adminClient->get('/admin/reports?search=' . rawurlencode($reportTargetUsername));
check(
    'The queue search matches the target snapshot',
    $searchQueue['status'] === 200
        && contains($searchQueue['body'], reportQueueLink($baseUrl, $targetReportId))
        && !contains($searchQueue['body'], reportQueueLink($baseUrl, $userReportId)),
);

$userReportDetail = $adminClient->get('/admin/reports/' . $userReportId);
check(
    'The report detail shows the target, reporter, reason, and actions',
    $userReportDetail['status'] === 200
        && contains($userReportDetail['body'], 'Report details')
        && contains($userReportDetail['body'], 'Reporter')
        && contains($userReportDetail['body'], 'Harassment or bullying')
        && contains($userReportDetail['body'], '@' . $adminUsername)
        && contains($userReportDetail['body'], 'Resolve report')
        && contains($userReportDetail['body'], 'Dismiss report'),
);

$resolveReport = $adminClient->post('/admin/reports/' . $userReportId . '/resolve', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $userReportId),
    'resolution_note' => 'Reviewed the profile and confirmed the reported behaviour.',
]);
$resolvedRow = row($pdo, 'SELECT * FROM reports WHERE id = ?', [$userReportId]);
check(
    'An administrator can resolve a report with a note',
    $resolveReport['status'] === 302
        && (string) ($resolvedRow['status'] ?? '') === 'resolved'
        && (int) ($resolvedRow['resolved_by_user_id'] ?? 0) === $adminId
        && (string) ($resolvedRow['resolution_note'] ?? '') === 'Reviewed the profile and confirmed the reported behaviour.'
        && $resolvedRow['resolved_at'] !== null,
);
check(
    'Report resolution is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'report.resolved' AND target_type = 'report' AND target_id = ?",
        [$userReportId],
    ) === 1,
);

$reportNotification = row(
    $pdo,
    'SELECT * FROM notifications WHERE user_id = ? AND event_key = ?',
    [$userId, 'report.closed:' . $userReportId],
);
check(
    'Resolving a report notifies the reporter without leaking the private note',
    $reportNotification !== null
        && (string) ($reportNotification['type'] ?? '') === 'report'
        && (string) ($reportNotification['title'] ?? '') === 'Your report was resolved'
        && contains((string) ($reportNotification['message'] ?? ''), 'resolved your report')
        && (string) ($reportNotification['url'] ?? '') === '/reports'
        && !contains((string) ($reportNotification['message'] ?? ''), 'Reviewed the profile')
        && $reportNotification['read_at'] === null,
);

$notificationInbox = $userClient->get('/notifications');
check(
    'The report notification appears in the reporter’s inbox',
    $notificationInbox['status'] === 200
        && contains($notificationInbox['body'], 'Your report was resolved'),
);

$resolvedMine = $userClient->get('/reports');
check(
    'My Reports reflects the resolved status and closes the loop',
    $resolvedMine['status'] === 200
        && contains($resolvedMine['body'], 'marked it resolved'),
);

$repeatResolve = $adminClient->post('/admin/reports/' . $userReportId . '/resolve', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $userReportId),
    'resolution_note' => 'A repeated decision must not be recorded twice.',
]);
check(
    'A closed report cannot be resolved twice',
    $repeatResolve['status'] === 302
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM audit_logs WHERE action = 'report.resolved' AND target_id = ?",
            [$userReportId],
        ) === 1,
);
check(
    'A repeated decision does not duplicate the reporter notification',
    scalar(
        $pdo,
        'SELECT COUNT(*) FROM notifications WHERE user_id = ? AND event_key = ?',
        [$userId, 'report.closed:' . $userReportId],
    ) === 1,
);

$resolvedQueue = $adminClient->get('/admin/reports?status=resolved');
check(
    'The resolved filter shows the closed report',
    $resolvedQueue['status'] === 200
        && contains($resolvedQueue['body'], reportQueueLink($baseUrl, $userReportId))
        && !contains($resolvedQueue['body'], reportQueueLink($baseUrl, $targetReportId)),
);

$pdo->prepare("DELETE FROM reports WHERE reporter_user_id = ? AND target_label = 'Throttle fixture'")
    ->execute([$userId]);

$throttleInsert = $pdo->prepare(
    "INSERT INTO reports (reporter_user_id, target_type, target_id, target_label, reason, details, status, created_at, updated_at)
     VALUES (?, 'profile', ?, 'Throttle fixture', 'spam', NULL, 'open', UTC_TIMESTAMP(), UTC_TIMESTAMP())",
);

for ($i = 1; $i <= 10; $i++) {
    $throttleInsert->execute([$userId, 900000 + $i]);
}

$throttled = submitReport(
    $userClient,
    'profile',
    $reportTargetId,
    'spam',
    'This report should be blocked by the hourly limit.',
);
check(
    'The hourly report limit blocks new submissions',
    $throttled['status'] === 302
        && scalar(
            $pdo,
            'SELECT COUNT(*) FROM reports WHERE reporter_user_id = ? AND target_type = ? AND target_id = ?',
            [$userId, 'profile', $reportTargetId],
        ) === 0,
);

$pdo->prepare("DELETE FROM reports WHERE reporter_user_id = ? AND target_label = 'Throttle fixture'")
    ->execute([$userId]);

$pdo->prepare('DELETE FROM users WHERE id = ?')->execute([$reportTargetId]);

$deletedTargetDetail = $adminClient->get('/admin/reports/' . $targetReportId);
check(
    'A report against a deleted target keeps its snapshot and shows an empty target state',
    $deletedTargetDetail['status'] === 200
        && contains($deletedTargetDetail['body'], 'This target no longer exists')
        && contains($deletedTargetDetail['body'], '@' . $reportTargetUsername),
);

$dismissReport = $adminClient->post('/admin/reports/' . $targetReportId . '/dismiss', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $targetReportId),
    'resolution_note' => 'The target account no longer exists, so no further action is possible.',
]);
$dismissedRow = row($pdo, 'SELECT * FROM reports WHERE id = ?', [$targetReportId]);
check(
    'An administrator can dismiss a report',
    $dismissReport['status'] === 302
        && (string) ($dismissedRow['status'] ?? '') === 'dismissed'
        && (string) ($dismissedRow['resolution_note'] ?? '') === 'The target account no longer exists, so no further action is possible.',
);
check(
    'Report dismissal is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'report.dismissed' AND target_type = 'report' AND target_id = ?",
        [$targetReportId],
    ) === 1,
);

$dismissedQueue = $adminClient->get('/admin/reports?status=dismissed');
check(
    'The dismissed filter shows the closed report',
    $dismissedQueue['status'] === 200
        && contains($dismissedQueue['body'], reportQueueLink($baseUrl, $targetReportId))
        && !contains($dismissedQueue['body'], reportQueueLink($baseUrl, $userReportId)),
);

$noCsrfResolve = $adminClient->post('/admin/reports/' . $userReportId . '/resolve', []);
check(
    'Report decisions without a CSRF token return 419 and change nothing',
    $noCsrfResolve['status'] === 419
        && (string) (row($pdo, 'SELECT status FROM reports WHERE id = ?', [$userReportId])['status'] ?? '') === 'resolved',
);

$reportAudit = $adminClient->get('/admin/audit?target_type=report');
check(
    'The audit log filters report decisions by target type',
    $reportAudit['status'] === 200
        && contains($reportAudit['body'], 'report.resolved')
        && contains($reportAudit['body'], 'report.dismissed')
        && contains($reportAudit['body'], 'Resolved report #' . $userReportId),
);

echo "\n7b. Read-only moderation\n";

$adminUsersOverview = $adminClient->get('/admin/users');
$overviewActivePattern = '#<a\s[^>]*href="' . preg_quote($baseUrl, '#') . '/admin"[^>]*aria-current="page"#';
$usersActivePattern = '#<a\s[^>]*href="' . preg_quote($baseUrl, '#') . '/admin/users"[^>]*aria-current="page"#';
check(
    'The admin sidebar highlights Users without leaving Overview highlighted',
    preg_match($usersActivePattern, $adminUsersOverview['body']) === 1
        && preg_match($overviewActivePattern, $adminUsersOverview['body']) === 0,
);

$questsBeforeRestriction = scalar($pdo, 'SELECT COUNT(*) FROM quests WHERE user_id = ?', [$userId]);
$restrictUser = $adminClient->post('/admin/users/' . $userId . '/restrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $userId),
    'message' => 'Please review the community guidelines before posting again.',
]);
$restrictedRow = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
check(
    'Restricting an account sets restricted_at without suspending it',
    $restrictUser['status'] === 302
        && (string) ($restrictedRow['status'] ?? '') === 'active'
        && $restrictedRow['restricted_at'] !== null
        && $restrictedRow['suspended_until'] === null,
);
check(
    'Restriction keeps the account signed in',
    $userClient->get('/dashboard')['status'] === 200,
);

$restrictedNotice = row(
    $pdo,
    "SELECT * FROM notifications WHERE user_id = ? AND type = 'moderation' AND title = ? ORDER BY id DESC LIMIT 1",
    [$userId, 'Account limited to read-only'],
);
check(
    'The read-only account is told why and how to regain access',
    $restrictedNotice !== null
        && contains((string) ($restrictedNotice['message'] ?? ''), 'read-only')
        && contains((string) ($restrictedNotice['message'] ?? ''), 'Please review the community guidelines')
        && (string) ($restrictedNotice['url'] ?? '') === '/notifications',
);
check(
    'Restriction is audited against the account',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'user.restricted' AND target_type = 'user' AND target_id = ?",
        [$userId],
    ) === 1,
);

$restrictedDashboard = $userClient->get('/dashboard');
check(
    'A read-only account still sees its dashboard with a read-only banner',
    $restrictedDashboard['status'] === 200
        && contains($restrictedDashboard['body'], 'Read-only account')
        && !contains($restrictedDashboard['body'], '/quests/create'),
);

$blockedCreateForm = $userClient->get('/quests/create');
check(
    'A read-only account is redirected away from the quest form',
    $blockedCreateForm['status'] === 302
        && str_ends_with((string) ($blockedCreateForm['headers']['location'] ?? ''), '/quests'),
);

$blockedCreate = $userClient->post('/quests', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'title' => 'Blocked read-only quest ' . $suffix,
    'description' => 'A read-only account must not be able to create this quest.',
    'difficulty' => 'easy',
    'category_id' => '',
    'xp_reward' => '9999',
]);
check(
    'A read-only account cannot create a quest',
    $blockedCreate['status'] === 302
        && scalar($pdo, 'SELECT COUNT(*) FROM quests WHERE user_id = ?', [$userId]) === $questsBeforeRestriction,
);

$blockedComplete = $userClient->post('/quests/' . $questId . '/complete', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
]);
check(
    'A read-only account cannot complete a quest',
    $blockedComplete['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$questId])['status'] ?? '') === 'active'
        && scalar($pdo, 'SELECT COUNT(*) FROM xp_transactions WHERE quest_id = ?', [$questId]) === 0,
);

$blockedArchive = $userClient->post('/quests/' . $questId . '/archive', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
]);
check(
    'A read-only account cannot archive a quest',
    $blockedArchive['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM quests WHERE id = ?', [$questId])['status'] ?? '') === 'active',
);

$blockedReportForm = $userClient->get('/reports/new?target_type=profile&target_id=' . $adminId);
check(
    'A read-only account cannot open the report form',
    $blockedReportForm['status'] === 302
        && str_ends_with((string) ($blockedReportForm['headers']['location'] ?? ''), '/reports'),
);

$blockedReport = $userClient->post('/reports', [
    '_csrf_token' => csrfFor($userClient, '/reports'),
    'target_type' => 'profile',
    'target_id' => (string) $adminId,
    'reason' => 'spam',
    'details' => 'A read-only account must not be able to submit this report.',
]);
check(
    'A read-only account cannot submit a report',
    $blockedReport['status'] === 302
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM reports WHERE reporter_user_id = ? AND target_type = 'profile' AND target_id = ? AND reason = 'spam'",
            [$userId, $adminId],
        ) === 0,
);

$restrictedFilter = $adminClient->get('/admin/users?status=restricted');
check(
    'The read-only filter lists the restricted account',
    $restrictedFilter['status'] === 200
        && contains($restrictedFilter['body'], '@' . $userUsername)
        && contains($restrictedFilter['body'], 'Read-only'),
);

$restrictedDetail = $adminClient->get('/admin/users/' . $userId);
check(
    'The admin user detail shows the read-only state and restore control',
    $restrictedDetail['status'] === 200
        && contains($restrictedDetail['body'], 'Read-only access')
        && contains($restrictedDetail['body'], 'Restore full access')
        && contains($restrictedDetail['body'], 'Read-only since'),
);

$publicReadOnly = $guestClient->get('/u/' . rawurlencode($userUsername));
check(
    'Read-only accounts keep their public profile',
    $publicReadOnly['status'] === 200,
);

$unrestrictUser = $adminClient->post('/admin/users/' . $userId . '/unrestrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $userId),
    'message' => 'Your account is in good standing again.',
]);
$unrestrictedRow = row($pdo, 'SELECT * FROM users WHERE id = ?', [$userId]);
check(
    'Restoring full access clears restricted_at',
    $unrestrictUser['status'] === 302 && $unrestrictedRow['restricted_at'] === null,
);
check(
    'The unrestricted account is notified',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'moderation' AND title = ?",
        [$userId, 'Read-only limit lifted'],
    ) === 1,
);
check(
    'Restoring full access is audited',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'user.unrestricted' AND target_type = 'user' AND target_id = ?",
        [$userId],
    ) === 1,
);
check(
    'The account can create quests again after the limit is lifted',
    $userClient->get('/quests/create')['status'] === 200,
);

$restrictedReport = submitReport(
    $adminClient,
    'profile',
    $userId,
    'spam',
    'A report that will limit this account to read-only access.',
);
$restrictedReportRow = row(
    $pdo,
    'SELECT * FROM reports WHERE reporter_user_id = ? AND target_type = ? AND target_id = ? AND reason = ?',
    [$adminId, 'profile', $userId, 'spam'],
);
$restrictedReportId = (int) ($restrictedReportRow['id'] ?? 0);

if ($restrictedReportId > 0) {
    $createdReportIds[] = $restrictedReportId;
}

check(
    'A moderator can file the report used for the report-based restriction',
    $restrictedReport['status'] === 302 && $restrictedReportId > 0,
);

$reportRestrict = $adminClient->post('/admin/reports/' . $restrictedReportId . '/restrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $restrictedReportId),
    'duration' => '7',
    'message' => 'Limited while the report is reviewed.',
]);
check(
    'An open report can limit the reported account to read-only',
    $reportRestrict['status'] === 302
        && (string) (row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$userId])['restricted_at'] ?? '') !== ''
        && (string) (row($pdo, 'SELECT restricted_until FROM users WHERE id = ?', [$userId])['restricted_until'] ?? '') !== ''
        && (string) (row($pdo, 'SELECT status FROM reports WHERE id = ?', [$restrictedReportId])['status'] ?? '') === 'open',
);
check(
    'The report-based restriction is audited with the report id',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs
         WHERE action = 'user.restricted' AND target_type = 'user' AND target_id = ?
           AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.report_id')) = ?",
        [$userId, (string) $restrictedReportId],
    ) === 1,
);

$reportRestrictedDetail = $adminClient->get('/admin/reports/' . $restrictedReportId);
check(
    'The report detail shows the read-only state and restore control',
    $reportRestrictedDetail['status'] === 200
        && contains($reportRestrictedDetail['body'], 'Read-only')
        && contains($reportRestrictedDetail['body'], 'Restore full access'),
);

$reportUnrestrict = $adminClient->post('/admin/reports/' . $restrictedReportId . '/unrestrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/reports/' . $restrictedReportId),
    'message' => 'The review is complete.',
]);
check(
    'An open report can restore full access',
    $reportUnrestrict['status'] === 302
        && row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$userId])['restricted_at'] === null,
);

$selfRestrict = $adminClient->post('/admin/users/' . $adminId . '/restrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $adminId),
]);
check(
    'An administrator cannot limit their own account',
    $selfRestrict['status'] === 302
        && row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$adminId])['restricted_at'] === null,
);

echo "\n7c. Timed read-only limits and appeals\n";

$timedRestrict = $adminClient->post('/admin/users/' . $userId . '/restrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $userId),
    'duration' => '1',
    'message' => 'A one-day read-only limit for this test.',
]);
$timedRow = row($pdo, 'SELECT restricted_at, restricted_until FROM users WHERE id = ?', [$userId]);
$timedUntil = (string) ($timedRow['restricted_until'] ?? '');
check(
    'A timed restriction stores an expiry in the future',
    $timedRestrict['status'] === 302
        && (string) ($timedRow['restricted_at'] ?? '') !== ''
        && $timedUntil !== ''
        && strtotime($timedUntil . ' UTC') > time(),
);

$timedDashboard = $userClient->get('/dashboard');
check(
    'The read-only banner shows the expiry for a timed limit',
    $timedDashboard['status'] === 200
        && contains($timedDashboard['body'], 'This limit ends on'),
);

$timedDetail = $adminClient->get('/admin/users/' . $userId);
check(
    'The admin user detail shows the expiry for a timed limit',
    $timedDetail['status'] === 200 && contains($timedDetail['body'], 'Read-only until'),
);

$pdo->prepare('UPDATE users SET restricted_until = ? WHERE id = ?')
    ->execute([gmdate('Y-m-d H:i:s', time() - 60), $userId]);
$expiredDashboard = $userClient->get('/dashboard');
check(
    'An expired timed restriction lifts on the next authenticated request',
    $expiredDashboard['status'] === 200
        && !contains($expiredDashboard['body'], 'Read-only account')
        && row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$userId])['restricted_at'] === null,
);

$appealRestrict = $adminClient->post('/admin/users/' . $userId . '/restrict', [
    '_csrf_token' => csrfFor($adminClient, '/admin/users/' . $userId),
    'duration' => '7',
    'message' => 'Read-only while the appeal test runs.',
]);
check(
    'The account is read-only again for the appeal flow',
    $appealRestrict['status'] === 302
        && (string) (row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$userId])['restricted_at'] ?? '') !== '',
);

$guestAppeal = $guestClient->get('/appeal');
check(
    'A guest is redirected from the appeal page to sign in',
    $guestAppeal['status'] === 302
        && str_ends_with((string) ($guestAppeal['headers']['location'] ?? ''), '/login'),
);

$adminAppealPage = $adminClient->get('/appeal');
check(
    'An account with full access is redirected away from the appeal form',
    $adminAppealPage['status'] === 302
        && str_ends_with((string) ($adminAppealPage['headers']['location'] ?? ''), '/dashboard'),
);

$appealPage = $userClient->get('/appeal');
check(
    'A read-only account can open the appeal form',
    $appealPage['status'] === 200
        && contains($appealPage['body'], 'Request a review')
        && contains($appealPage['body'], 'name="message"'),
);

$shortAppeal = $userClient->post('/appeal', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'message' => 'Too short.',
]);
check(
    'An appeal shorter than the minimum is rejected',
    $shortAppeal['status'] === 302
        && scalar($pdo, 'SELECT COUNT(*) FROM account_appeals WHERE user_id = ?', [$userId]) === 0,
);

$appealMessage = 'I believe the read-only limit on my account was applied by mistake and would like it reviewed.';
$validAppeal = $userClient->post('/appeal', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'message' => $appealMessage,
]);
$firstAppeal = row($pdo, 'SELECT * FROM account_appeals WHERE user_id = ? ORDER BY id ASC LIMIT 1', [$userId]);
$firstAppealId = (int) ($firstAppeal['id'] ?? 0);
check(
    'A read-only account can submit an appeal',
    $validAppeal['status'] === 302
        && $firstAppealId > 0
        && (string) ($firstAppeal['status'] ?? '') === 'open',
);
check(
    'Submitting an appeal is audited and acknowledged',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM audit_logs WHERE action = 'appeal.submitted' AND target_type = 'appeal' AND target_id = ?",
        [$firstAppealId],
    ) === 1
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'moderation' AND title = 'Appeal received'",
            [$userId],
        ) === 1,
);

$duplicateAppeal = $userClient->post('/appeal', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'message' => 'A second appeal while the first one is still waiting for a moderator.',
]);
check(
    'Only one appeal can be open at a time',
    $duplicateAppeal['status'] === 302
        && scalar($pdo, 'SELECT COUNT(*) FROM account_appeals WHERE user_id = ?', [$userId]) === 1,
);

$appealsQueue = $adminClient->get('/admin/appeals');
check(
    'The admin appeals queue lists the open appeal',
    $appealsQueue['status'] === 200
        && contains($appealsQueue['body'], 'Appeals queue')
        && contains($appealsQueue['body'], '@' . $userUsername),
);

$appealDetail = $adminClient->get('/admin/appeals/' . $firstAppealId);
check(
    'The admin appeal detail shows the message and decision controls',
    $appealDetail['status'] === 200
        && contains($appealDetail['body'], $appealMessage)
        && contains($appealDetail['body'], 'Accept and restore access')
        && contains($appealDetail['body'], 'Deny appeal'),
);

check(
    'A normal account cannot open the admin appeals queue',
    $userClient->get('/admin/appeals')['status'] === 403,
);

$denyAppeal = $adminClient->post('/admin/appeals/' . $firstAppealId . '/deny', [
    '_csrf_token' => csrfFor($adminClient, '/admin/appeals/' . $firstAppealId),
    'resolution_note' => 'The limit stays until the review is complete.',
]);
check(
    'Denying an appeal keeps the read-only limit',
    $denyAppeal['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM account_appeals WHERE id = ?', [$firstAppealId])['status'] ?? '') === 'denied'
        && (string) (row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$userId])['restricted_at'] ?? '') !== '',
);
check(
    'A denied appeal is audited and the account is notified',
    scalar($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action = 'appeal.denied' AND target_id = ?", [$firstAppealId]) === 1
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'moderation' AND title = 'Appeal denied'",
            [$userId],
        ) === 1,
);

$cooldownAppeal = $userClient->post('/appeal', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'message' => 'I would like to appeal again immediately after the decision.',
]);
check(
    'A new appeal is blocked during the 24-hour cooldown',
    $cooldownAppeal['status'] === 302
        && scalar($pdo, 'SELECT COUNT(*) FROM account_appeals WHERE user_id = ?', [$userId]) === 1,
);

$pdo->prepare('UPDATE account_appeals SET resolved_at = ?, updated_at = ? WHERE id = ?')
    ->execute([
        gmdate('Y-m-d H:i:s', time() - 90000),
        gmdate('Y-m-d H:i:s', time() - 90000),
        $firstAppealId,
    ]);
$secondAppeal = $userClient->post('/appeal', [
    '_csrf_token' => csrfFor($userClient, '/dashboard'),
    'message' => 'The earlier decision is now outside the cooldown, so I am requesting another review.',
]);
$secondAppealRow = row($pdo, 'SELECT * FROM account_appeals WHERE user_id = ? ORDER BY id DESC LIMIT 1', [$userId]);
$secondAppealId = (int) ($secondAppealRow['id'] ?? 0);
check(
    'A second appeal is allowed after the cooldown',
    $secondAppeal['status'] === 302
        && $secondAppealId > $firstAppealId
        && (string) ($secondAppealRow['status'] ?? '') === 'open',
);

$acceptAppeal = $adminClient->post('/admin/appeals/' . $secondAppealId . '/accept', [
    '_csrf_token' => csrfFor($adminClient, '/admin/appeals/' . $secondAppealId),
    'resolution_note' => 'Thanks for the context. Your full access is restored.',
]);
check(
    'Accepting an appeal lifts the read-only limit',
    $acceptAppeal['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM account_appeals WHERE id = ?', [$secondAppealId])['status'] ?? '') === 'accepted'
        && row($pdo, 'SELECT restricted_at FROM users WHERE id = ?', [$userId])['restricted_at'] === null,
);
check(
    'Accepting an appeal is audited as a decision and an unrestricted account',
    scalar($pdo, "SELECT COUNT(*) FROM audit_logs WHERE action = 'appeal.accepted' AND target_id = ?", [$secondAppealId]) === 1
        && scalar(
            $pdo,
            "SELECT COUNT(*) FROM audit_logs
             WHERE action = 'user.unrestricted' AND target_type = 'user' AND target_id = ?
               AND JSON_UNQUOTE(JSON_EXTRACT(metadata, '$.appeal_id')) = ?",
            [$userId, (string) $secondAppealId],
        ) === 1,
);
check(
    'The accepted appeal notifies the account',
    scalar(
        $pdo,
        "SELECT COUNT(*) FROM notifications WHERE user_id = ? AND type = 'moderation' AND title = 'Appeal accepted'",
        [$userId],
    ) === 1,
);
check(
    'The account can create quests again after the appeal is accepted',
    $userClient->get('/quests/create')['status'] === 200,
);

$repeatDeny = $adminClient->post('/admin/appeals/' . $secondAppealId . '/deny', [
    '_csrf_token' => csrfFor($adminClient, '/admin/appeals/' . $secondAppealId),
    'resolution_note' => 'This decision should not be possible.',
]);
check(
    'A decided appeal cannot be reviewed again',
    $repeatDeny['status'] === 302
        && (string) (row($pdo, 'SELECT status FROM account_appeals WHERE id = ?', [$secondAppealId])['status'] ?? '') === 'accepted',
);

echo "\n8. Audit trail\n";

$auditPage = $adminClient->get('/admin/audit');
check(
    'Audit log renders privileged actions',
    $auditPage['status'] === 200
        && contains($auditPage['body'], 'Audit log')
        && contains($auditPage['body'], 'user.suspended')
        && contains($auditPage['body'], 'category.created')
        && contains($auditPage['body'], 'achievement.created')
        && contains($auditPage['body'], 'quest.deleted'),
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

$auditByQuest = $adminClient->get('/admin/audit?target_type=quest');
check(
    'Audit log filters by the quest target type',
    $auditByQuest['status'] === 200
        && contains($auditByQuest['body'], 'Deleted quest')
        && contains($auditByQuest['body'], 'quest.deleted')
        && !contains($auditByQuest['body'], 'Suspended Phase Six User'),
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

$recentAuditSummary = (string) (AuditLog::paginate(['actor' => $adminUsername], 1, 1)[0]['summary'] ?? '');
$auditInvalidTarget = $adminClient->get('/admin/audit?target_type=not-a-target');
check(
    'Audit log ignores unknown target types and still lists entries',
    $auditInvalidTarget['status'] === 200
        && $recentAuditSummary !== ''
        && contains($auditInvalidTarget['body'], $recentAuditSummary),
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

echo "\n9. CSRF protection on admin mutations\n";

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

echo "\n10. Cleanup\n";

$reportCleanup = array_values(array_filter($createdReportIds, static fn (int $id): bool => $id > 0));

if ($reportCleanup !== []) {
    $placeholders = implode(', ', array_fill(0, count($reportCleanup), '?'));
    $pdo->prepare("DELETE FROM reports WHERE id IN ({$placeholders})")->execute($reportCleanup);
}

$pdo->prepare('DELETE FROM user_achievements WHERE user_id = ? AND achievement_id = ?')->execute([$userId, $firstStepsId]);
$pdo->prepare('DELETE FROM audit_logs WHERE actor_user_id IN (?, ?)')->execute([$adminId, $userId]);
$pdo->prepare('DELETE FROM users WHERE id IN (?, ?, ?)')->execute([$adminId, $userId, $reportTargetId]);

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

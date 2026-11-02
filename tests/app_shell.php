<?php

declare(strict_types=1);

/**
 * Phase 2 application shell regression suite.
 *
 * Requires the app to be served at APP_URL and the database to be migrated.
 * Usage: php tests/app_shell.php
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
        $this->cookieFile = (string) tempnam(sys_get_temp_dir(), 'questify_shell_');
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

function jarWithoutComments(string $file): array
{
    $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

    return array_values(array_filter(
        $lines,
        static fn (string $line): bool => !str_starts_with($line, '# ') && $line !== '#HttpOnly_',
    ));
}

/**
 * Clone the authenticated jar and add one preference cookie.
 */
function jarWithCookie(string $file, string $name, string $value): string
{
    $target = (string) tempnam(sys_get_temp_dir(), 'questify_shell_jar_');
    $lines = array_values(array_filter(
        jarWithoutComments($file),
        static function (string $line) use ($name): bool {
            $fields = explode("\t", $line);

            return ($fields[5] ?? '') !== $name;
        },
    ));
    $lines[] = implode("\t", ['localhost', 'FALSE', '/', 'FALSE', '0', $name, $value]);
    file_put_contents($target, implode(PHP_EOL, $lines) . PHP_EOL);

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
$username = 'shell_' . $suffix;
$email = 'shell_' . $suffix . '@example.test';
$password = 'ShellQuest-' . $suffix . '7';
$displayName = 'Shell Tester ' . $suffix;

$client = new HttpClient($baseUrl);

echo "\n1. Authentication protection\n";

foreach (['/dashboard', '/quests', '/achievements', '/leaderboard', '/profile', '/settings'] as $route) {
    $response = $client->get($route);
    check(
        "{$route} requires authentication",
        $response['status'] === 302 && str_ends_with($response['headers']['location'] ?? '', '/login'),
    );
}

$login = $client->get('/login');
check('Sign-in page preserves Phase 1 rendering', $login['status'] === 200 && contains($login['body'], 'Welcome back, adventurer'));

echo "\n2. Create an authenticated session\n";

$register = $client->get('/register');
$csrf = $client->csrf($register['body']);
$created = $client->post('/register', [
    '_csrf_token' => $csrf,
    'display_name' => $displayName,
    'username' => $username,
    'email' => $email,
    'timezone' => 'Europe/London',
    'password' => $password,
    'password_confirmation' => $password,
    'terms' => '1',
]);
check(
    'Registration still signs the user in',
    $created['status'] === 302 && str_ends_with($created['headers']['location'] ?? '', '/dashboard'),
);

$dashboard = $client->get('/dashboard');
$html = $dashboard['body'];
check('Dashboard renders for a signed-in user', $dashboard['status'] === 200);
check('Application shell is present', contains($html, 'data-app-shell'));
check('Sidebar is expanded by default', contains($html, 'data-sidebar="expanded"'));
check('Sidebar collapse control is present', contains($html, 'data-sidebar-collapse'));
check('Mobile navigation is present', contains($html, 'app-bottom-nav'));

echo "\n3. Navigation and identity\n";

foreach (['Dashboard', 'My Quests', 'Achievements', 'Leaderboard', 'My Profile', 'Settings'] as $label) {
    check("Sidebar includes {$label}", contains($html, '>' . $label . '</span>'));
}

check('Authenticated display name is rendered', contains($html, e($displayName)));
check('Authenticated username is rendered', contains($html, '@' . e($username)));
check('Account email is available in the profile menu', contains($html, e($email)));
check('Profile menu is wired', contains($html, 'data-user-menu-toggle') && contains($html, 'data-user-menu-panel'));
check('Quick action is present', contains($html, 'data-quest-open') && contains($html, 'id="quest-dialog"'));
check('Quest preview dialog is explicitly non-functional', contains($html, 'This is a non-functional preview'));
check('Preview fields are disabled', substr_count($html, 'disabled') >= 3);

echo "\n4. Real values and honest empty states\n";

check('Welcome message uses the account name', contains($html, 'Welcome back, ' . e($displayName)));
check('Level is shown from the account', contains($html, '>Level</p>') && substr_count($html, 'Level 1') >= 1);
check('XP is shown from the account', contains($html, 'XP total') && contains($html, 'data-counter="0"'));
check('Completed quests is an empty state', contains($html, 'Completed quests') && contains($html, 'No quest data yet'));
check('Active quests is an empty state', contains($html, 'Active quests') && contains($html, 'Quest engine arrives in Phase 3'));
check('Recent activity has an empty state', contains($html, 'No activity yet'));
check('Achievements are not fabricated', contains($html, 'No achievements are defined yet') && contains($html, 'Locked slot'));
check('Leaderboard shows only the real account row', contains($html, 'No other adventurers are shown') && contains($html, 'Unranked'));
check('Unfinished modules are labelled by phase', contains($html, 'Phase 3') && contains($html, 'Phase 4') && contains($html, 'Phase 5'));
check('No PHP diagnostics leaked into the page', !contains($html, 'Warning:') && !contains($html, 'Fatal error') && !contains($html, 'Undefined'));

echo "\n5. Placeholder destinations\n";

$placeholders = [
    '/quests' => ['My Quests', 'Phase 3', 'Create one-off, daily, and weekly quests'],
    '/achievements' => ['Achievements', 'Phase 4', 'A gallery of unlockable achievement definitions'],
    '/leaderboard' => ['Leaderboard', 'Phase 5', 'Weekly, monthly, and all-time rankings'],
    '/profile' => ['My Profile', 'Phase 5', 'A shareable public profile at your username'],
    '/settings' => ['Settings', 'Phase 5', 'Password changes and active session management'],
];

foreach ($placeholders as $route => [$heading, $phase, $feature]) {
    $page = $client->get($route);
    check(
        "{$route} renders its placeholder",
        $page['status'] === 200
            && contains($page['body'], '<h1')
            && contains($page['body'], e($heading))
            && contains($page['body'], 'Planned for ' . $phase),
    );
    check(
        "{$route} clearly marks the module inactive",
        contains($page['body'], 'This module is not active yet') && contains($page['body'], e($feature)),
    );
    check(
        "{$route} still uses the application shell",
        contains($page['body'], 'data-app-shell') && contains($page['body'], 'aria-current="page"'),
    );
}

echo "\n6. Persisted sidebar preference\n";

$collapsedJar = jarWithCookie($client->cookieJar(), 'questify_sidebar', 'collapsed');
$client->useJar($collapsedJar);
$collapsed = $client->get('/dashboard');
check('Collapsed preference is rendered server-side', $collapsed['status'] === 200 && contains($collapsed['body'], 'data-sidebar="collapsed"'));

$invalidJar = jarWithCookie($client->cookieJar(), 'questify_sidebar', 'sideways');
$client->useJar($invalidJar);
$invalid = $client->get('/dashboard');
check('Unknown sidebar values fall back to expanded', $invalid['status'] === 200 && contains($invalid['body'], 'data-sidebar="expanded"'));

echo "\n7. Phase 1 behaviour is intact\n";

$logoutCsrf = $client->csrf($invalid['body']);
$logout = $client->post('/logout', ['_csrf_token' => $logoutCsrf]);
check('Logout still redirects to sign-in', $logout['status'] === 302 && str_ends_with($logout['headers']['location'] ?? '', '/login'));

$afterLogout = $client->get('/dashboard');
check('Dashboard is protected after logout', $afterLogout['status'] === 302);

$landing = $client->get('/');
check('Landing page still renders', $landing['status'] === 200 && contains($landing['body'], 'Turn your to-do list'));

$health = $client->get('/api/health');
check('Health endpoint still healthy', $health['status'] === 200 && contains($health['body'], '"database":"ok"'));

echo "\n8. Cleanup\n";

$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$email]);
$pdo->prepare('DELETE FROM login_attempts WHERE identifier = ?')->execute([strtolower($email)]);
$deleted = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
$deleted->execute([$email]);
check('Shell test account cleaned up', (int) $deleted->fetchColumn() === 0);

echo "\n----------------------------------------\n";
echo "{$passed} passed, {$failed} failed\n";

exit($failed === 0 ? 0 : 1);

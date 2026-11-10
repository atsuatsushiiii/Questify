<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\ReadOnlyMiddleware;
use App\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Guards the role gate at the route table level.
 *
 * The HTTP suites prove a normal user receives 403; this test stops a future
 * admin route from being added without AdminMiddleware in the first place.
 */
final class RouteAuthorizationTest extends TestCase
{
    /** @var list<array{method: string, pattern: string, middleware: list<class-string|callable>}> */
    private array $routes;

    protected function setUp(): void
    {
        $router = new Router();

        (static function (Router $router): void {
            require BASE_PATH . '/routes/web.php';
            require BASE_PATH . '/routes/api.php';
        })($router);

        $this->routes = $router->routes();
    }

    /** @return list<array{method: string, pattern: string, middleware: list<class-string|callable>}> */
    private function adminRoutes(): array
    {
        return array_values(array_filter(
            $this->routes,
            static fn (array $route): bool => str_starts_with($route['pattern'], '/admin')
                || str_starts_with($route['pattern'], '/api/admin'),
        ));
    }

    public function testEveryAdminRouteCarriesTheRoleGate(): void
    {
        $adminRoutes = $this->adminRoutes();

        $this->assertGreaterThanOrEqual(18, count($adminRoutes), 'Expected the full admin surface to be registered.');

        foreach ($adminRoutes as $route) {
            $label = $route['method'] . ' ' . $route['pattern'];

            $this->assertContains(AuthMiddleware::class, $route['middleware'], "{$label} must require authentication.");
            $this->assertContains(AdminMiddleware::class, $route['middleware'], "{$label} must require an administrator.");

            $auth = array_search(AuthMiddleware::class, $route['middleware'], true);
            $admin = array_search(AdminMiddleware::class, $route['middleware'], true);

            $this->assertTrue($auth < $admin, "{$label} must authenticate before checking the role.");
        }
    }

    public function testAdminMutationsCheckTheRoleBeforeCsrf(): void
    {
        $mutations = array_filter(
            $this->adminRoutes(),
            static fn (array $route): bool => in_array($route['method'], ['POST', 'PUT', 'PATCH', 'DELETE'], true),
        );

        $this->assertNotEmpty($mutations);

        foreach ($mutations as $route) {
            $label = $route['method'] . ' ' . $route['pattern'];

            $this->assertContains(VerifyCsrfToken::class, $route['middleware'], "{$label} must verify CSRF.");

            $admin = array_search(AdminMiddleware::class, $route['middleware'], true);
            $csrf = array_search(VerifyCsrfToken::class, $route['middleware'], true);

            $this->assertTrue($admin < $csrf, "{$label} must reject non-admins before checking CSRF.");
        }
    }

    public function testTheStatsApiIsTheOnlyAdminApi(): void
    {
        $apiAdminRoutes = array_filter(
            $this->routes,
            static fn (array $route): bool => str_starts_with($route['pattern'], '/api/admin'),
        );

        $this->assertCount(1, $apiAdminRoutes);
        $this->assertSame('/api/admin/stats', array_values($apiAdminRoutes)[0]['pattern']);
    }

    public function testPublicRoutesDoNotCarryTheRoleGate(): void
    {
        foreach ($this->routes as $route) {
            if (str_starts_with($route['pattern'], '/admin') || str_starts_with($route['pattern'], '/api/admin')) {
                continue;
            }

            $this->assertNotContains(
                AdminMiddleware::class,
                $route['middleware'],
                $route['method'] . ' ' . $route['pattern'] . ' should not require an administrator.',
            );
        }
    }

    /** @return array<string, array{0: string, 1: string}> */
    public static function readOnlyRouteProvider(): array
    {
        return [
            'create form' => ['GET', '/quests/create'],
            'create quest' => ['POST', '/quests'],
            'edit form' => ['GET', '/quests/{id}/edit'],
            'update quest' => ['PUT', '/quests/{id}'],
            'complete quest' => ['POST', '/quests/{id}/complete'],
            'archive quest' => ['POST', '/quests/{id}/archive'],
            'restore quest' => ['POST', '/quests/{id}/restore'],
            'delete quest' => ['POST', '/quests/{id}/delete'],
            'report form' => ['GET', '/reports/new'],
            'submit report' => ['POST', '/reports'],
        ];
    }

    /**
     * Read-only accounts may browse and review, but every quest mutation and
     * report submission must pass through the read-only guard.
     */
    #[DataProvider('readOnlyRouteProvider')]
    public function testQuestAndReportMutationsCarryTheReadOnlyGuard(string $method, string $pattern): void
    {
        $route = $this->route($method, $pattern);

        $this->assertNotNull($route, "{$method} {$pattern} must be registered.");
        $this->assertContains(ReadOnlyMiddleware::class, $route['middleware'], "{$method} {$pattern} must reject read-only accounts.");

        $auth = array_search(AuthMiddleware::class, $route['middleware'], true);
        $readOnly = array_search(ReadOnlyMiddleware::class, $route['middleware'], true);

        $this->assertTrue($auth < $readOnly, "{$method} {$pattern} must authenticate before the read-only check.");
    }

    public function testReadOnlyGuardRunsBeforeCsrf(): void
    {
        foreach ($this->routes as $route) {
            if (!in_array(ReadOnlyMiddleware::class, $route['middleware'], true)) {
                continue;
            }

            $label = $route['method'] . ' ' . $route['pattern'];
            $readOnly = array_search(ReadOnlyMiddleware::class, $route['middleware'], true);
            $csrf = array_search(VerifyCsrfToken::class, $route['middleware'], true);

            if ($csrf === false) {
                continue;
            }

            $this->assertTrue($readOnly < $csrf, "{$label} must reject read-only accounts before checking CSRF.");
        }
    }

    public function testReadOnlyGuardLeavesBrowsingAndSettingsAlone(): void
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== 'GET') {
                continue;
            }

            if (
                !in_array($route['pattern'], ['/dashboard', '/quests', '/quests/{id}', '/reports', '/settings', '/notifications', '/achievements', '/leaderboard'], true)
            ) {
                continue;
            }

            $this->assertNotContains(
                ReadOnlyMiddleware::class,
                $route['middleware'],
                $route['method'] . ' ' . $route['pattern'] . ' must stay available to read-only accounts.',
            );
        }
    }

    /** @return array{method: string, pattern: string, middleware: list<class-string|callable>}|null */
    private function route(string $method, string $pattern): ?array
    {
        foreach ($this->routes as $route) {
            if ($route['method'] === $method && $route['pattern'] === $pattern) {
                return $route;
            }
        }

        return null;
    }
}

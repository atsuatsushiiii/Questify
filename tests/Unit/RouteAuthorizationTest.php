<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Router;
use App\Middleware\AdminMiddleware;
use App\Middleware\AuthMiddleware;
use App\Middleware\VerifyCsrfToken;
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
}

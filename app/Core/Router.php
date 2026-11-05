<?php

// Code © 2026 Kevin Cyril Maraño, MIT license.

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny regex router with per-route middleware.
 */
final class Router
{
    /**
     * @var list<array{method: string, pattern: string, regex: string, handler: array{0: class-string, 1: string}|callable, middleware: list<class-string|callable>}>
     */
    private array $routes = [];

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|callable> $middleware
     */
    public function get(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('GET', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|callable> $middleware
     */
    public function post(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('POST', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|callable> $middleware
     */
    public function put(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('PUT', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|callable> $middleware
     */
    public function patch(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('PATCH', $pattern, $handler, $middleware);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|callable> $middleware
     */
    public function delete(string $pattern, array|callable $handler, array $middleware = []): void
    {
        $this->add('DELETE', $pattern, $handler, $middleware);
    }

    /**
     * Read-only view of the registered routes.
     *
     * Diagnostics and the authorization suite use this so a new admin route
     * cannot silently skip the role gate.
     *
     * @return list<array{method: string, pattern: string, middleware: list<class-string|callable>}>
     */
    public function routes(): array
    {
        return array_map(
            static fn (array $route): array => [
                'method' => $route['method'],
                'pattern' => $route['pattern'],
                'middleware' => $route['middleware'],
            ],
            $this->routes,
        );
    }

    public function dispatch(Request $request): Response
    {
        $allowedMethods = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $request->path(), $matches)) {
                continue;
            }

            if ($route['method'] !== $request->method()) {
                $allowedMethods[] = $route['method'];
                continue;
            }

            /** @var array<string, string> $params */
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            return $this->runPipeline($route['middleware'], $request, $route['handler'], $params);
        }

        if ($allowedMethods !== []) {
            throw HttpException::methodNotAllowed();
        }

        throw HttpException::notFound();
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param list<class-string|callable> $middleware
     */
    private function add(string $method, string $pattern, array|callable $handler, array $middleware): void
    {
        $segments = preg_split(
            '/(\{[a-zA-Z_][a-zA-Z0-9_]*\})/',
            $pattern,
            -1,
            PREG_SPLIT_DELIM_CAPTURE,
        ) ?: [$pattern];

        $regex = '';

        foreach ($segments as $segment) {
            if (preg_match('/^\{([a-zA-Z_][a-zA-Z0-9_]*)\}$/', $segment, $match) === 1) {
                $regex .= '(?P<' . $match[1] . '>[^/]+)';
            } else {
                $regex .= preg_quote($segment, '#');
            }
        }

        $this->routes[] = [
            'method' => $method,
            'pattern' => $pattern,
            'regex' => '#^' . $regex . '$#',
            'handler' => $handler,
            'middleware' => $middleware,
        ];
    }

    /**
     * @param list<class-string|callable> $middleware
     * @param array{0: class-string, 1: string}|callable $handler
     * @param array<string, string> $params
     */
    private function runPipeline(array $middleware, Request $request, array|callable $handler, array $params): Response
    {
        $next = fn (Request $current): Response => $this->invoke($handler, $current, $params);

        foreach (array_reverse($middleware) as $layer) {
            $next = static function (Request $current) use ($layer, $next): Response {
                if ($layer instanceof \Closure || (is_object($layer) && !method_exists($layer, 'handle'))) {
                    return $layer($current, $next);
                }

                $instance = is_string($layer) ? new $layer() : $layer;

                return $instance->handle($current, $next);
            };
        }

        return $next($request);
    }

    /**
     * @param array{0: class-string, 1: string}|callable $handler
     * @param array<string, string> $params
     */
    private function invoke(array|callable $handler, Request $request, array $params): Response
    {
        if (is_array($handler)) {
            [$class, $method] = $handler;
            $controller = new $class();
            $result = $controller->{$method}($request, ...array_values($params));
        } else {
            $result = $handler($request, ...array_values($params));
        }

        if ($result instanceof Response) {
            return $result;
        }

        if (is_string($result)) {
            return Response::html($result);
        }

        throw new \RuntimeException('Route handlers must return a Response.');
    }
}

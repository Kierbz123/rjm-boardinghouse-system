<?php

namespace App\Support;

/**
 * Minimal explicit-route-table router. No magic, no framework — every route
 * is declared in public/index.php so the whole app's surface is readable in
 * one place, per CLAUDE.md's "controllers stay thin" convention.
 */
class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [$method, $pattern, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        foreach ($this->routes as [$routeMethod, $pattern, $handler]) {
            if ($routeMethod !== $method) {
                continue;
            }
            $regex = preg_replace('#\{[a-zA-Z_]+\}#', '([^/]+)', $pattern);
            if (preg_match('#^' . $regex . '$#', $path, $matches)) {
                array_shift($matches);
                call_user_func_array($handler, $matches);
                return;
            }
        }
        http_response_code(404);
        echo '404 Not Found';
    }
}

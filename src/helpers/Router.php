<?php

declare(strict_types=1);

namespace App\Helpers;

final class Router
{
    /**
     * @var array<string, array<string, callable>>
     */
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->addRoute('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->addRoute('POST', $path, $handler);
    }

    public function dispatch(string $method, string $uri): void
    {
        $path = parse_url($uri, PHP_URL_PATH) ?? '/';
        $normalizedPath = rtrim($path, '/') ?: '/';

        $handler = $this->routes[$method][$normalizedPath] ?? null;

        if ($handler === null) {
            http_response_code(404);
            echo '404 - Ruta no encontrada';
            return;
        }

        $handler();
    }

    private function addRoute(string $method, string $path, callable $handler): void
    {
        $normalizedPath = rtrim($path, '/') ?: '/';
        $this->routes[$method][$normalizedPath] = $handler;
    }
}

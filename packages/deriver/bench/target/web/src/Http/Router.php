<?php

declare(strict_types=1);

namespace Harbor\Http;

final class Router
{
    private array $routes = [];

    public function add(string $method, string $path, callable $handler): void
    {
        $pattern = preg_replace('/\\\{([a-z]+)\\\}/', '(?P<$1>[1-9][0-9]*)', preg_quote($path, '~'));
        $this->routes[] = [$method, '~^' . $pattern . '$~D', $handler];
    }

    public function dispatch(Request $request): Response
    {
        $matched = false;
        foreach ($this->routes as [$method, $pattern, $handler]) {
            if (!preg_match($pattern, $request->path, $matches)) {
                continue;
            }
            $matched = true;
            if ($method !== $request->method) {
                continue;
            }
            $parameters = array_map(intval(...), array_filter($matches, is_string(...), ARRAY_FILTER_USE_KEY));
            return $handler($request, $parameters);
        }
        throw new Problem($matched ? 405 : 404, $matched ? 'Method not allowed.' : 'Route not found.');
    }
}

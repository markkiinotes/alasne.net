<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;

class Router
{
    protected array $routes = [];

    protected ?string $lastMethod = null;

    protected ?string $lastPath = null;

    public function get(string $path, callable|array $callback): self
    {
        return $this->addRoute('GET', $path, $callback);
    }

    public function post(string $path, callable|array $callback): self
    {
        return $this->addRoute('POST', $path, $callback);
    }

    protected function addRoute(string $method, string $path, callable|array $callback): self
    {
        $path = rtrim($path, '/') ?: '/';

        $this->routes[$method][$path] = [
            'callback' => $callback,
            'middleware' => [],
        ];

        $this->lastMethod = $method;
        $this->lastPath = $path;

        return $this;
    }

    public function middleware(string $middleware): self
    {
        if ($this->lastMethod === null || $this->lastPath === null) {
            return $this;
        }

        $this->routes[$this->lastMethod][$this->lastPath]['middleware'][] = $middleware;

        return $this;
    }

    public function resolve(): mixed
    {
        $request = new Request();

        $method = $request->method();
        $path = $request->path();

        $matched = $this->matchRoute($method, $path);

        if (! $matched) {
            http_response_code(404);
            return '404 - Page not found';
        }

        $route = $matched['route'];

        $request->setRouteParams($matched['params']);

        foreach ($route['middleware'] as $middleware) {
            $this->runMiddleware($middleware);
        }

        $callback = $route['callback'];

        if (is_array($callback)) {
            [$class, $method] = $callback;

            $controller = app()->container->make($class);

            return $controller->$method($request);
        }

        return call_user_func($callback, $request);
    }

    protected function matchRoute(string $method, string $path): ?array
    {
        if (isset($this->routes[$method][$path])) {
            return [
                'route' => $this->routes[$method][$path],
                'params' => [],
            ];
        }

        foreach ($this->routes[$method] ?? [] as $routePath => $route) {
            $pattern = preg_replace(
                '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
                '(?P<$1>[^/]+)',
                $routePath
            );

            $pattern = '#^' . $pattern . '$#';

            if (preg_match($pattern, $path, $matches)) {
                $params = array_filter(
                    $matches,
                    fn ($key) => is_string($key),
                    ARRAY_FILTER_USE_KEY
                );

                return [
                    'route' => $route,
                    'params' => $params,
                ];
            }
        }

        return null;
    }

    protected function runMiddleware(string $middleware): void
    {
        if ($middleware === 'auth') {
            app()->container->make(AuthMiddleware::class)->handle();
            return;
        }

        if (str_starts_with($middleware, 'permission:')) {
            $permission = substr($middleware, strlen('permission:'));

            app()->container->make(PermissionMiddleware::class)->handle($permission);
            return;
        }
    }
}
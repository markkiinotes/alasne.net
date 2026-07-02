<?php

declare(strict_types=1);

namespace App\Core;

class Router
{
    protected array $routes = [];

    public function get(string $path, callable|array $callback): void
    {
        $this->routes['GET'][$path] = $callback;
    }

    public function post(string $path, callable|array $callback): void
    {
        $this->routes['POST'][$path] = $callback;
    }

    public function resolve(): mixed
    {
        $request = new Request();

        $method = $request->method();
        $path = $request->path();

        $callback = $this->routes[$method][$path] ?? false;

        if (!$callback) {
            http_response_code(404);
            return '404 - Page not found';
        }

        if (is_array($callback)) {
            [$class, $method] = $callback;
            $controller = new $class();

            return $controller->$method($request);
        }

        return call_user_func($callback, $request);
    }
}
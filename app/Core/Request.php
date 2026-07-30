<?php

declare(strict_types=1);

namespace App\Core;

class Request
{
    protected array $routeParams = [];

    public function method(): string
    {
        return $_SERVER['REQUEST_METHOD'];
    }

    public function path(): string
    {
        $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

        return rtrim($path, '/') ?: '/';
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $_POST[$key] ?? $_GET[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($_GET, $_POST);
    }

    public function isPost(): bool
    {
        return $this->method() === 'POST';
    }

    public function isGet(): bool
    {
        return $this->method() === 'GET';
    }

    public function has(string $key): bool
    {
        return isset($_POST[$key]) || isset($_GET[$key]);
    }

    public function setRouteParams(array $params): void
    {
        $this->routeParams = $params;
    }

    public function route(string $key, mixed $default = null): mixed
    {
        return $this->routeParams[$key] ?? $default;
    }
}
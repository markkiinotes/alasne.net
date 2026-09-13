<?php

declare(strict_types=1);

namespace App\Core;

use App\Middleware\AuthMiddleware;
use App\Middleware\PermissionMiddleware;
use App\Support\NotFoundPage;

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

    protected function addRoute(
        string $method,
        string $path,
        callable|array $callback
    ): self {
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
        if (
            $this->lastMethod === null
            || $this->lastPath === null
        ) {
            return $this;
        }

        $this->routes[
            $this->lastMethod
        ][
            $this->lastPath
        ]['middleware'][] = $middleware;

        return $this;
    }

    public function resolve(): mixed
    {
        $request = new Request();

        $method = $request->method();
        $path = $request->path();

        $matched = $this->matchRoute(
            $method,
            $path
        );

        if (! $matched) {
            return app()
                ->container
                ->make(NotFoundPage::class)
                ->render($request);
        }

        $route = $matched['route'];

        $request->setRouteParams(
            $matched['params']
        );

        foreach (
            $route['middleware']
            as $middleware
        ) {
            $this->runMiddleware(
                $middleware
            );
        }

        $callback = $route['callback'];

        if (is_array($callback)) {
            [$class, $method] = $callback;

            $controller = app()
                ->container
                ->make($class);

            $result = $controller->$method(
                $request
            );

            return $this->presentResult(
                $request,
                $result
            );
        }

        $result = call_user_func(
            $callback,
            $request
        );

        return $this->presentResult(
            $request,
            $result
        );
    }

    protected function matchRoute(
        string $method,
        string $path
    ): ?array {
        if (
            isset(
                $this->routes[$method][$path]
            )
        ) {
            return [
                'route' =>
                    $this->routes[
                        $method
                    ][
                        $path
                    ],
                'params' => [],
            ];
        }

        foreach (
            $this->routes[$method] ?? []
            as $routePath => $route
        ) {
            $pattern = preg_replace(
                '#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#',
                '(?P<$1>[^/]+)',
                $routePath
            );

            $pattern =
                '#^'
                . $pattern
                . '$#';

            if (
                preg_match(
                    $pattern,
                    $path,
                    $matches
                )
            ) {
                $params = array_filter(
                    $matches,
                    fn ($key) =>
                        is_string($key),
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

    protected function runMiddleware(
        string $middleware
    ): void {
        if ($middleware === 'auth') {
            app()
                ->container
                ->make(
                    AuthMiddleware::class
                )
                ->handle();

            return;
        }

        if (
            str_starts_with(
                $middleware,
                'permission:'
            )
        ) {
            $permission = substr(
                $middleware,
                strlen('permission:')
            );

            app()
                ->container
                ->make(
                    PermissionMiddleware::class
                )
                ->handle($permission);

            return;
        }
    }

    private function presentResult(
        Request $request,
        mixed $result
    ): mixed {
        if (! is_string($result)) {
            return $result;
        }

        /*
         * Preserve fully rendered HTML responses, including the polished
         * storefront 404 pages returned directly by controllers.
         */
        if (
            stripos($result, '<!DOCTYPE html') !== false
            || stripos($result, '<html') !== false
        ) {
            return $result;
        }

        /*
         * Only normalize raw client-error strings for the public storefront.
         * Admin and internal routes keep their existing behavior.
         */
        if (! str_starts_with(
            $request->path(),
            '/store/'
        )) {
            return $result;
        }

        $status = http_response_code();

        if (
            $status < 400
            || $status > 499
        ) {
            return $result;
        }

        [$heading, $message] =
            $this->customerErrorCopy(
                $status,
                $result
            );

        return app()
            ->container
            ->make(NotFoundPage::class)
            ->renderStatus(
                $request,
                $status,
                null,
                $heading,
                $message
            );
    }

    private function customerErrorCopy(
        int $status,
        string $rawResult
    ): array {
        $raw = strtolower(
            trim($rawResult)
        );

        if ($status === 400) {
            return [
                'Request could not be completed',
                'The request is missing or contains invalid information. Return to the previous page and try again.',
            ];
        }

        if ($status === 403) {
            return [
                'Request not allowed',
                'This request could not be completed. Return to the previous page and try again.',
            ];
        }

        if ($status === 404) {
            if (
                str_contains(
                    $raw,
                    'receipt'
                )
            ) {
                return [
                    'Receipt not found',
                    'We could not find a receipt matching those details.',
                ];
            }

            if (
                str_contains(
                    $raw,
                    'customer account'
                )
            ) {
                return [
                    'Customer account not found',
                    'We could not find the requested customer account.',
                ];
            }

            if (
                str_contains(
                    $raw,
                    'order'
                )
            ) {
                return [
                    'Order not found',
                    'We could not find the requested order.',
                ];
            }

            if (
                str_contains(
                    $raw,
                    'store'
                )
            ) {
                return [
                    'Storefront not found',
                    'The store you requested is unavailable or does not exist.',
                ];
            }

            return [
                'Page not found',
                'The page you requested could not be found.',
            ];
        }

        return [
            'Request could not be completed',
            'The request could not be completed. Return to the previous page and try again.',
        ];
    }
}

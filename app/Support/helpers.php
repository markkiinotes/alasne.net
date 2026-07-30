<?php

declare(strict_types=1);

use App\Core\Application;
use App\Core\Config;
use App\Services\Auth\AuthService;
use App\Services\Auth\AuthorizationService;

if (! function_exists('app')) {
    function app(): Application
    {
        return $GLOBALS['app'];
    }
}

if (! function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return app()->container
            ->make(Config::class)
            ->get($key, $default);
    }
}

if (! function_exists('auth')) {
    function auth(): AuthService
    {
        return app()->container->make(AuthService::class);
    }
}

if (! function_exists('current_user_id')) {
    function current_user_id(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }
}

if (! function_exists('current_user_name')) {
    function current_user_name(): string
    {
        return $_SESSION['user_name'] ?? 'User';
    }
}

if (! function_exists('current_user_roles')) {
    function current_user_roles(): array
    {
        $userId = current_user_id();

        if (! $userId) {
            return [];
        }

        return app()
            ->container
            ->make(AuthorizationService::class)
            ->rolesForUser((int) $userId);
    }
}

if (! function_exists('can')) {
    function can(string $permission): bool
    {
        $userId = current_user_id();

        if (! $userId) {
            return false;
        }

        return app()
            ->container
            ->make(AuthorizationService::class)
            ->can((int) $userId, $permission);
    }
}

if (! function_exists('has_role')) {
    function has_role(string $role): bool
    {
        $userId = current_user_id();

        if (! $userId) {
            return false;
        }

        return app()
            ->container
            ->make(AuthorizationService::class)
            ->hasRole((int) $userId, $role);
    }
	
	if (! function_exists('current_path')) {
		function current_path(): string
		{
			$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

			return rtrim($path, '/') ?: '/';
		}
	}

	if (! function_exists('nav_active')) {
		function nav_active(string $path, bool $startsWith = false): string
		{
			$current = current_path();

			$path = rtrim($path, '/') ?: '/';

			if ($startsWith) {
				return str_starts_with($current, $path) ? 'active' : '';
			}

			return $current === $path ? 'active' : '';
		}
	}
	
	if (! function_exists('app_url')) {
		function app_url(string $path = ''): string
		{
			$baseUrl = $_ENV['APP_URL']
				?? getenv('APP_URL')
				?: '';

			$baseUrl = rtrim((string) $baseUrl, '/');

			if ($path === '') {
				return $baseUrl;
			}

			return $baseUrl . '/' . ltrim($path, '/');
		}
	}
}
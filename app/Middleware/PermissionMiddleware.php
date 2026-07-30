<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth\AuthorizationService;

class PermissionMiddleware
{
    public function handle(string $permission): void
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $authorization = app()->container->make(AuthorizationService::class);

        if (! $userId || ! $authorization->can($userId, $permission)) {
            http_response_code(403);
            echo '403 - Forbidden';
            exit;
        }
    }
}
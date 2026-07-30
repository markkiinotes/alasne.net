<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\Auth\AuthService;

class AuthMiddleware
{
    public function handle(): void
    {
        $auth = app()->container->make(AuthService::class);

        if (! $auth->check()) {
            header('Location: /login');
            exit;
        }
    }
}
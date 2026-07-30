<?php

declare(strict_types=1);

namespace App\Services\Auth;

class CsrfService
{
    public function token(): string
    {
        if (empty($_SESSION['_csrf_token'])) {
            $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
        }

        return $_SESSION['_csrf_token'];
    }

    public function validate(?string $token): bool
    {
        if (empty($_SESSION['_csrf_token']) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION['_csrf_token'], $token);
    }

    public function regenerate(): void
    {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }
}
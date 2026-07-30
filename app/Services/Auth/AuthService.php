<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Repositories\UserRepository;

class AuthService
{
    public function __construct(private UserRepository $users)
    {
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->users->findByEmail($email);

        if (! $user || $user->status !== 'active') {
            return false;
        }

        if (! password_verify($password, $user->password)) {
            return false;
        }

        session_regenerate_id(true);

        $_SESSION['user_id'] = $user->id;
        $_SESSION['user_name'] = $user->name;
        $_SESSION['user_email'] = $user->email;
        $_SESSION['user_role'] = $user->role;

        return true;
    }

    public function check(): bool
    {
        return isset($_SESSION['user_id']);
    }

    public function userId(): ?int
    {
        return $_SESSION['user_id'] ?? null;
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();

            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                $params['secure'],
                $params['httponly']
            );
        }

        session_destroy();
    }
}
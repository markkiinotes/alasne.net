<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Controller;
use App\Services\Auth\AuthService;
use App\Services\Auth\CsrfService;

class AuthController extends Controller
{
    public function __construct(
        private AuthService $auth,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function showLogin()
    {
        if ($this->auth->check()) {
            $this->response->redirect('/admin');
        }

        $error = $_SESSION['auth_error'] ?? null;

        unset($_SESSION['auth_error']);

        return $this->view(
            'auth.login',
            [
                'title' => 'Mission Control',
                'error' => $error,
                'csrf_token' => $this->csrf->token(),
            ],
            'auth'
        );
    }

    public function login()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['auth_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/login');
        }

        $email = trim((string) $this->request->input('email'));
        $password = (string) $this->request->input('password');

        if (! $this->auth->attempt($email, $password)) {
            $_SESSION['auth_error'] = 'Invalid email or password.';
            $this->response->redirect('/login');
        }

        $this->csrf->regenerate();

        $this->response->redirect('/admin');
    }

    public function logout()
    {
        $this->auth->logout();

        $this->response->redirect('/login');
    }
}
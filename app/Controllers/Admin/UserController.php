<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\RoleRepository;
use App\Repositories\UserRepository;
use App\Services\Auth\CsrfService;

class UserController extends Controller
{
    public function __construct(
        private UserRepository $users,
        private RoleRepository $roles,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('admin.users.index', [
            'title' => 'Users',
            'users' => $this->users->all(),
            'success' => $_SESSION['users_success'] ?? null,
            'error' => $_SESSION['users_error'] ?? null,
        ], 'admin');
    }

    public function create()
    {
        return $this->view('admin.users.create', [
            'title' => 'Create User',
            'roles' => $this->roles->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['users_error'] ?? null,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['users_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/users/create');
        }

        $name = trim((string) $this->request->input('name'));
        $email = trim((string) $this->request->input('email'));
        $password = (string) $this->request->input('password');
        $roleSlug = (string) $this->request->input('role');

        if ($name === '' || $email === '' || $password === '' || $roleSlug === '') {
            $_SESSION['users_error'] = 'All fields are required.';
            $this->response->redirect('/admin/users/create');
        }

        if ($this->users->findByEmail($email)) {
            $_SESSION['users_error'] = 'A user with that email already exists.';
            $this->response->redirect('/admin/users/create');
        }

        $role = $this->roles->findBySlug($roleSlug);

        if (! $role) {
            $_SESSION['users_error'] = 'Selected role does not exist.';
            $this->response->redirect('/admin/users/create');
        }

        $userId = $this->users->create([
            'name' => $name,
            'email' => $email,
            'password' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $roleSlug,
            'status' => 'active',
        ]);

        $this->roles->attachToUser($userId, (int) $role['id']);

        $this->csrf->regenerate();

        $_SESSION['users_success'] = 'User created successfully.';

        $this->response->redirect('/admin/users');
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('id');

        $user = $this->users->find($id);

        if (! $user) {
            http_response_code(404);
            return '404 - User not found';
        }

        return $this->view('admin.users.edit', [
            'title' => 'Edit User',
            'user' => $user,
            'roles' => $this->roles->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['users_error'] ?? null,
        ], 'admin');
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('id');

        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['users_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/users/' . $id . '/edit');
        }

        $user = $this->users->find($id);

        if (! $user) {
            http_response_code(404);
            return '404 - User not found';
        }

        $name = trim((string) $this->request->input('name'));
        $email = trim((string) $this->request->input('email'));
        $status = (string) $this->request->input('status');
        $roleSlug = (string) $this->request->input('role');

        if ($name === '' || $email === '' || $status === '' || $roleSlug === '') {
            $_SESSION['users_error'] = 'Name, email, status, and role are required.';
            $this->response->redirect('/admin/users/' . $id . '/edit');
        }

        $role = $this->roles->findBySlug($roleSlug);

        if (! $role) {
            $_SESSION['users_error'] = 'Selected role does not exist.';
            $this->response->redirect('/admin/users/' . $id . '/edit');
        }

        $this->users->update($id, [
            'name' => $name,
            'email' => $email,
            'role' => $roleSlug,
            'status' => $status,
        ]);

        $this->users->syncRole($id, (int) $role['id']);

        $this->csrf->regenerate();

        $_SESSION['users_success'] = 'User updated successfully.';

        $this->response->redirect('/admin/users');
    }
}
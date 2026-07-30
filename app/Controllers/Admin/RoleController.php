<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\PermissionRepository;
use App\Repositories\RoleRepository;
use App\Services\Auth\CsrfService;

class RoleController extends Controller
{
    public function __construct(
        private RoleRepository $roles,
        private PermissionRepository $permissions,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('admin.roles.index', [
            'title' => 'Roles',
            'roles' => $this->roles->all(),
            'success' => $_SESSION['roles_success'] ?? null,
            'error' => $_SESSION['roles_error'] ?? null,
        ], 'admin');
    }

    public function create()
    {
        return $this->view('admin.roles.create', [
            'title' => 'Create Role',
            'permissions' => $this->permissions->all(),
            'csrf_token' => $this->csrf->token(),
            'error' => $_SESSION['roles_error'] ?? null,
        ], 'admin');
    }

    public function store()
    {
        $csrfToken = (string) $this->request->input('_csrf_token');

        if (! $this->csrf->validate($csrfToken)) {
            $_SESSION['roles_error'] = 'Security token expired. Please try again.';
            $this->response->redirect('/admin/roles/create');
        }

        $name = trim((string) $this->request->input('name'));
        $slug = trim((string) $this->request->input('slug'));
        $description = trim((string) $this->request->input('description'));
        $permissionIds = $this->request->input('permissions', []);

        if ($name === '' || $slug === '') {
            $_SESSION['roles_error'] = 'Role name and slug are required.';
            $this->response->redirect('/admin/roles/create');
        }

        if ($this->roles->findBySlug($slug)) {
            $_SESSION['roles_error'] = 'A role with that slug already exists.';
            $this->response->redirect('/admin/roles/create');
        }

        if (! is_array($permissionIds)) {
            $permissionIds = [];
        }

        $roleId = $this->roles->create([
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
        ]);

        $this->roles->attachPermissions($roleId, $permissionIds);

        $this->csrf->regenerate();

        $_SESSION['roles_success'] = 'Role created successfully.';

        $this->response->redirect('/admin/roles');
    }
	
	public function edit(Request $request)
	{
		$id = (int) $request->route('id');

		$role = $this->roles->find($id);

		if (! $role) {
			http_response_code(404);
			return '404 - Role not found';
		}

		return $this->view('admin.roles.edit', [
			'title' => 'Edit Role',
			'role' => $role,
			'permissions' => $this->permissions->all(),
			'selectedPermissions' => $this->roles->permissionIds($id),
			'csrf_token' => $this->csrf->token(),
			'error' => $_SESSION['roles_error'] ?? null,
		], 'admin');
	}

	public function update(Request $request)
	{
		$id = (int) $request->route('id');

		$csrfToken = (string) $this->request->input('_csrf_token');

		if (! $this->csrf->validate($csrfToken)) {
			$_SESSION['roles_error'] = 'Security token expired. Please try again.';
			$this->response->redirect('/admin/roles/' . $id . '/edit');
		}

		$role = $this->roles->find($id);

		if (! $role) {
			http_response_code(404);
			return '404 - Role not found';
		}

		$name = trim((string) $this->request->input('name'));
		$slug = trim((string) $this->request->input('slug'));
		$description = trim((string) $this->request->input('description'));
		$permissionIds = $this->request->input('permissions', []);

		if ($name === '' || $slug === '') {
			$_SESSION['roles_error'] = 'Role name and slug are required.';
			$this->response->redirect('/admin/roles/' . $id . '/edit');
		}

		$existingRole = $this->roles->findBySlug($slug);

		if ($existingRole && (int) $existingRole['id'] !== $id) {
			$_SESSION['roles_error'] = 'A role with that slug already exists.';
			$this->response->redirect('/admin/roles/' . $id . '/edit');
		}

		if (! is_array($permissionIds)) {
			$permissionIds = [];
		}

		$this->roles->update($id, [
			'name' => $name,
			'slug' => $slug,
			'description' => $description,
		]);

		$this->roles->syncPermissions($id, $permissionIds);

		$this->csrf->regenerate();

		$_SESSION['roles_success'] = 'Role updated successfully.';

		$this->response->redirect('/admin/roles');
	}
}
<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\PermissionRepository;

class PermissionController extends Controller
{
    public function __construct(private PermissionRepository $permissions)
    {
        parent::__construct();
    }

    public function index()
    {
        return $this->view('admin.permissions.index', [
            'title' => 'Permissions',
            'permissions' => $this->permissions->all(),
        ], 'admin');
    }
}
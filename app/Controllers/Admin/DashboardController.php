<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        return $this->view('admin.dashboard.index', [
            'title' => 'Admin Dashboard',
        ], 'admin');
    }
}
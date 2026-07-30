<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\DashboardRepository;
use App\Services\Auth\AuthorizationService;

class DashboardController extends Controller
{
    public function __construct(
        private DashboardRepository $dashboard,
        private AuthorizationService $authorization
    ) {
        parent::__construct();
    }

    public function index()
    {
        $userId = (int) ($_SESSION['user_id'] ?? 0);

        $roles = [];
        $permissions = [];
        $canViewMissionControl = false;

        if ($userId > 0) {
            $roles = $this->authorization->rolesForUser($userId);
            $permissions = $this->authorization->permissionsForUser($userId);
            $canViewMissionControl = $this->authorization->can(
                $userId,
                'mission_control.view'
            );
        }

        return $this->view('admin.dashboard.index', [
            'title' => 'Mission Control',
            'stats' => $this->dashboard->stats(),
            'lowStockProducts' => $this->dashboard->lowStockProducts(),
            'recentOrderEvents' => $this->dashboard->recentOrderEvents(10),
            'roles' => $roles,
            'permissions' => $permissions,
            'canViewMissionControl' => $canViewMissionControl,
        ], 'admin');
    }
}
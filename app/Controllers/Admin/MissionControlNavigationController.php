<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Admin\MissionControlNavigationService;

class MissionControlNavigationController extends Controller
{
    public function __construct(
        private MissionControlNavigationService $navigation
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view(
            'admin.mission-control.index',
            [
                'title' => 'Mission Control',
                'hub' => $this->navigation->hub(),
            ],
            'admin'
        );
    }
}

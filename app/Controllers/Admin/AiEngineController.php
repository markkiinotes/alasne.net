<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\AI\AiEngineService;

class AiEngineController extends Controller
{
    public function __construct(
        private AiEngineService $engine
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view(
            'admin.ai.index',
            [
                'title' => 'AI Engine',
                'dashboard' =>
                    $this->engine->dashboard(),
            ],
            'admin'
        );
    }
}

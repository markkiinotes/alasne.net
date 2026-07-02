<?php

declare(strict_types=1);

use App\Core\Router;
use App\Controllers\HomeController;
use App\Controllers\Admin\DashboardController;

$router = $app->router;

$router->get('/', [HomeController::class, 'index']);
$router->get('/admin', [DashboardController::class, 'index']);

return $router;
<?php

declare(strict_types=1);

use App\Controllers\SeoController;

$router = $app->router;

$router->get(
    '/robots.txt',
    [SeoController::class, 'robots']
);

$router->get(
    '/sitemap.xml',
    [SeoController::class, 'sitemap']
);

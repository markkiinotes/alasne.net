<?php

declare(strict_types=1);

$app = require dirname(__DIR__) . '/bootstrap/app.php';

require BASE_PATH . '/config/routes.php';

$app->run();
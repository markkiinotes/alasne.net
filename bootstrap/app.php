<?php

declare(strict_types=1);

use App\Core\Application;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(BASE_PATH);
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/New_York');

$app = new Application();

return $app;
<?php

declare(strict_types=1);

use App\Core\Application;

define('BASE_PATH', dirname(__DIR__));

require BASE_PATH . '/vendor/autoload.php';

require BASE_PATH . '/app/Support/helpers.php';

$dotenv = Dotenv\Dotenv::createUnsafeImmutable(BASE_PATH);
$dotenv->load();

date_default_timezone_set($_ENV['APP_TIMEZONE'] ?? 'America/New_York');

session_name($_ENV['SESSION_NAME'] ?? 'ALASNESESSID');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => false,
    'httponly' => true,
    'samesite' => 'Lax',
]);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$app = new Application();

$GLOBALS['app'] = $app;

return $app;
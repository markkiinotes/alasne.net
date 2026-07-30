<?php

declare(strict_types=1);

namespace App\Providers;

use App\Core\Config;
use App\Core\Database;
use App\Core\ServiceProvider;
use PDO;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->container->singleton(PDO::class, function () {
            return Database::connect();
        });

        $this->app->container->singleton(Config::class, function () {
            $config = new Config();

            $config->set('app.name', $_ENV['APP_NAME'] ?? 'Alasne Platform');
            $config->set('app.env', $_ENV['APP_ENV'] ?? 'development');
            $config->set('app.debug', ($_ENV['APP_DEBUG'] ?? 'true') === 'true');

            $config->set('database.connection', $_ENV['DB_CONNECTION'] ?? 'mysql');
            $config->set('database.host', $_ENV['DB_HOST'] ?? 'localhost');
            $config->set('database.port', $_ENV['DB_PORT'] ?? '3306');
            $config->set('database.database', $_ENV['DB_DATABASE'] ?? '');
            $config->set('database.username', $_ENV['DB_USERNAME'] ?? '');
            $config->set('database.password', $_ENV['DB_PASSWORD'] ?? '');

            return $config;
        });
    }
}
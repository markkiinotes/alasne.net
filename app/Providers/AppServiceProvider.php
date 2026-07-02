<?php

declare(strict_types=1);

namespace App\Providers;

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
    }
}
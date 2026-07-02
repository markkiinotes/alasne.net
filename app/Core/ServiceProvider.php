<?php

declare(strict_types=1);

namespace App\Core;

abstract class ServiceProvider
{
    public function __construct(protected Application $app)
    {
    }

    abstract public function register(): void;

    public function boot(): void
    {
        //
    }
}
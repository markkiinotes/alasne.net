<?php

declare(strict_types=1);

namespace App\Core;

class Container
{
    protected array $bindings = [];

    protected array $instances = [];

    public function bind(string $abstract, callable $factory): void
    {
        $this->bindings[$abstract] = $factory;
    }

    public function singleton(string $abstract, callable $factory): void
    {
        $this->instances[$abstract] = $factory($this);
    }

    public function make(string $abstract): mixed
    {
        if (isset($this->instances[$abstract])) {
            return $this->instances[$abstract];
        }

        if (isset($this->bindings[$abstract])) {
            return $this->bindings[$abstract]($this);
        }

        throw new \RuntimeException("Nothing bound for {$abstract}");
    }
}
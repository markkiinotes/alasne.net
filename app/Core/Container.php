<?php

declare(strict_types=1);

namespace App\Core;

use ReflectionClass;
use RuntimeException;

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

        return $this->resolve($abstract);
    }

    protected function resolve(string $class): object
    {
        if (!class_exists($class)) {
            throw new RuntimeException("Class {$class} does not exist.");
        }

        $reflection = new ReflectionClass($class);

        if (!$reflection->isInstantiable()) {
            throw new RuntimeException("Class {$class} is not instantiable.");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $class();
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                throw new RuntimeException(
                    "Cannot resolve parameter {$parameter->getName()} in {$class}."
                );
            }

            $dependencies[] = $this->make($type->getName());
        }

        return $reflection->newInstanceArgs($dependencies);
    }
}
<?php

declare(strict_types=1);

namespace App\Core;

class Config
{
    protected array $items = [];

    public function set(string $key, mixed $value): void
    {
        $this->items[$key] = $value;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->items[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->items);
    }
}
<?php

declare(strict_types=1);

namespace App\Models;

class User
{
    public function __construct(
        public int $id,
        public string $name,
        public string $email,
        public string $password,
        public string $role,
        public string $status
    ) {
    }
}
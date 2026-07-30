<?php

declare(strict_types=1);

namespace App\Services;

class TestService
{
    public function message(): string
    {
        return 'Auto-wiring is working.';
    }
}
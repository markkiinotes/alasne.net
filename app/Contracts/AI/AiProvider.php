<?php

declare(strict_types=1);

namespace App\Contracts\AI;

interface AiProvider
{
    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>
     */
    public function generate(array $request): array;
}

<?php

declare(strict_types=1);

namespace App\DTO\Suppliers;

final class PreparedSupplierSubmission
{
    /**
     * @param array<string, mixed> $payload
     */
    public function __construct(
        public readonly string $providerCode,
        public readonly string $channel,
        public readonly string $status,
        public readonly string $exportFileName,
        public readonly array $payload
    ) {
    }
}

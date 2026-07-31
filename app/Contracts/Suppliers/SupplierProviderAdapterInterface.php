<?php

declare(strict_types=1);

namespace App\Contracts\Suppliers;

use App\DTO\Suppliers\PreparedSupplierSubmission;

interface SupplierProviderAdapterInterface
{
    public function code(): string;

    public function label(): string;

    /**
     * @return array<string, bool>
     */
    public function capabilities(): array;

    /**
     * @param array<string, mixed> $integration
     * @return list<string>
     */
    public function validateIntegration(
        array $integration
    ): array;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $integration
     */
    public function preparePurchaseOrder(
        array $context,
        array $integration
    ): PreparedSupplierSubmission;
}

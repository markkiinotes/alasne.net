<?php

declare(strict_types=1);

namespace App\Services\Suppliers\Adapters;

use App\Contracts\Suppliers\SupplierProviderAdapterInterface;
use App\DTO\Suppliers\PreparedSupplierSubmission;

class ManualDirectSupplierAdapter
    implements SupplierProviderAdapterInterface
{
    public function code(): string
    {
        return 'manual_direct';
    }

    public function label(): string
    {
        return 'Manual / Direct Supplier';
    }

    public function capabilities(): array
    {
        return [
            'catalog_import' => false,
            'inventory_import' => false,
            'order_export' => true,
            'automatic_order_submission' => false,
        ];
    }

    public function validateIntegration(
        array $integration
    ): array {
        $errors = [];

        if (
            ! empty($integration['auto_submit_orders'])
        ) {
            $errors[] =
                'Manual/direct suppliers cannot automatically submit orders.';
        }

        return $errors;
    }

    public function preparePurchaseOrder(
        array $context,
        array $integration
    ): PreparedSupplierSubmission {
        $purchaseOrderNumber = (string) (
            $context['purchase_order'][
                'purchase_order_number'
            ] ?? 'purchase-order'
        );

        $safeNumber = preg_replace(
            '/[^A-Za-z0-9_.-]+/',
            '-',
            $purchaseOrderNumber
        ) ?: 'purchase-order';

        return new PreparedSupplierSubmission(
            $this->code(),
            'manual',
            'awaiting_manual',
            $safeNumber . '.csv',
            $context
        );
    }
}

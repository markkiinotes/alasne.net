<?php

declare(strict_types=1);

namespace App\Services\Suppliers\Adapters;

use App\Contracts\Suppliers\SupplierProviderAdapterInterface;
use App\DTO\Suppliers\PreparedSupplierSubmission;

class CsvFeedSupplierAdapter
    implements SupplierProviderAdapterInterface
{
    public function code(): string
    {
        return 'csv_feed';
    }

    public function label(): string
    {
        return 'CSV Catalog / Inventory Feed';
    }

    public function capabilities(): array
    {
        return [
            'catalog_import' => true,
            'inventory_import' => true,
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
                'CSV-feed suppliers cannot automatically submit orders.';
        }

        $feedUrl = trim(
            (string) (
                $integration['catalog_feed_url']
                ?? ''
            )
        );

        if (
            $feedUrl !== ''
            && ! filter_var(
                $feedUrl,
                FILTER_VALIDATE_URL
            )
        ) {
            $errors[] =
                'The catalog feed URL is not valid.';
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
            'csv_export',
            'awaiting_manual',
            $safeNumber . '.csv',
            $context
        );
    }
}

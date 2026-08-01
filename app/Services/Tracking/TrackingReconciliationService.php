<?php

declare(strict_types=1);

namespace App\Services\Tracking;

use App\Repositories\TrackingReconciliationRepository;
use PDO;
use RuntimeException;

class TrackingReconciliationService
{
    private const MAX_ROWS = 50000;

    public function __construct(
        private PDO $db,
        private TrackingReconciliationRepository $repository
    ) {
    }

    public function importCsv(
        string $filePath,
        string $originalName,
        ?int $storeId = null,
        ?int $supplierId = null
    ): array {
        $runId = $this->repository->createRun(
            $storeId,
            $supplierId,
            $originalName
        );

        $counts = [
            'received' => 0,
            'matched' => 0,
            'updated' => 0,
            'skipped' => 0,
            'failed' => 0,
        ];

        try {
            $handle = fopen($filePath, 'rb');

            if ($handle === false) {
                throw new RuntimeException(
                    'Unable to open the uploaded CSV file.'
                );
            }

            $header = fgetcsv($handle);

            if (! is_array($header)) {
                fclose($handle);

                throw new RuntimeException(
                    'The CSV file must include a header row.'
                );
            }

            $headers = $this->normalizeHeaders($header);
            $this->validateHeaders($headers);

            $rowNumber = 1;

            while (($values = fgetcsv($handle)) !== false) {
                $rowNumber++;

                if ($rowNumber > self::MAX_ROWS + 1) {
                    throw new RuntimeException(
                        'The CSV exceeds the '
                        . self::MAX_ROWS
                        . '-row import limit.'
                    );
                }

                if ($this->blankRow($values)) {
                    $counts['skipped']++;
                    continue;
                }

                $counts['received']++;

                $row = $this->combineRow(
                    $headers,
                    $values
                );

                try {
                    $result = $this->processRow(
                        $runId,
                        $rowNumber,
                        $row,
                        $storeId,
                        $supplierId
                    );

                    if ($result['matched']) {
                        $counts['matched']++;
                    }

                    if ($result['updated']) {
                        $counts['updated']++;
                    } elseif ($result['skipped']) {
                        $counts['skipped']++;
                    }

                    if ($result['failed']) {
                        $counts['failed']++;
                    }
                } catch (\Throwable $rowError) {
                    $counts['failed']++;

                    $this->repository->logRow(
                        $runId,
                        $rowNumber,
                        'failed',
                        [
                            'purchase_order_number' =>
                                $row['purchase_order_number'] ?? null,
                            'supplier_order_id' =>
                                $row['supplier_order_id']
                                ?? $row['provider_order_id']
                                ?? $row['external_order_id']
                                ?? null,
                            'carrier' => $row['carrier'] ?? null,
                            'tracking_number' =>
                                $row['tracking_number'] ?? null,
                            'tracking_url' =>
                                $row['tracking_url'] ?? null,
                            'shipment_status' =>
                                $row['shipment_status'] ?? null,
                            'normalized_status' => null,
                            'message' =>
                                $rowError->getMessage()
                                ?: 'Unable to reconcile this row.',
                            'raw_data' => $row,
                        ]
                    );
                }
            }

            fclose($handle);

            $status = $counts['failed'] > 0
                ? (
                    $counts['updated'] > 0
                    || $counts['matched'] > 0
                        ? 'partial'
                        : 'failed'
                )
                : 'succeeded';

            $this->repository->completeRun(
                $runId,
                $status,
                $counts
            );

            return [
                'run_id' => $runId,
                'status' => $status,
                'counts' => $counts,
            ];
        } catch (\Throwable $exception) {
            if (isset($handle) && is_resource($handle)) {
                fclose($handle);
            }

            $this->repository->completeRun(
                $runId,
                'failed',
                $counts,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * @return array{matched:bool,updated:bool,skipped:bool,failed:bool}
     */
    private function processRow(
        int $runId,
        int $rowNumber,
        array $row,
        ?int $storeId,
        ?int $supplierId
    ): array {
        $tracking = $this->trackingData($row);

        $purchaseOrder =
            $this->repository->findPurchaseOrder(
                $row,
                $storeId,
                $supplierId
            );

        if ($purchaseOrder === null) {
            $this->repository->logRow(
                $runId,
                $rowNumber,
                'unmatched',
                array_merge($tracking, [
                    'purchase_order_number' =>
                        $row['purchase_order_number'] ?? null,
                    'supplier_order_id' =>
                        $row['supplier_order_id']
                        ?? $row['provider_order_id']
                        ?? $row['external_order_id']
                        ?? null,
                    'message' =>
                        'No purchase order matched the row.',
                    'raw_data' => $row,
                ])
            );

            return [
                'matched' => false,
                'updated' => false,
                'skipped' => false,
                'failed' => true,
            ];
        }

        if (! empty($purchaseOrder['ambiguous'])) {
            $this->repository->logRow(
                $runId,
                $rowNumber,
                'ambiguous',
                array_merge($tracking, [
                    'match_strategy' =>
                        $purchaseOrder['match_strategy']
                        ?? null,
                    'purchase_order_number' =>
                        $row['purchase_order_number'] ?? null,
                    'supplier_order_id' =>
                        $row['supplier_order_id']
                        ?? $row['provider_order_id']
                        ?? $row['external_order_id']
                        ?? null,
                    'message' =>
                        'More than one purchase order matched the row.',
                    'raw_data' => $row,
                ])
            );

            return [
                'matched' => false,
                'updated' => false,
                'skipped' => false,
                'failed' => true,
            ];
        }

        $duplicate =
            $this->repository->hasDuplicateTracking(
                (int) $purchaseOrder['id'],
                $tracking['carrier'],
                $tracking['tracking_number']
            );

        if ($duplicate) {
            $this->repository->logRow(
                $runId,
                $rowNumber,
                'duplicate_tracking',
                array_merge($tracking, [
                    'match_strategy' =>
                        $purchaseOrder['match_strategy'] ?? null,
                    'purchase_order_id' =>
                        (int) $purchaseOrder['id'],
                    'order_id' =>
                        (int) $purchaseOrder['order_id'],
                    'store_id' =>
                        (int) $purchaseOrder['store_id'],
                    'supplier_id' =>
                        (int) $purchaseOrder['supplier_id'],
                    'purchase_order_number' =>
                        $purchaseOrder['purchase_order_number'],
                    'supplier_order_id' =>
                        $row['supplier_order_id']
                        ?? $row['provider_order_id']
                        ?? $row['external_order_id']
                        ?? null,
                    'message' =>
                        'Tracking number already belongs to purchase order '
                        . $duplicate['purchase_order_number']
                        . '.',
                    'raw_data' => $row,
                ])
            );

            $this->repository->createException(
                (int) $purchaseOrder['store_id'],
                (int) $purchaseOrder['order_id'],
                (int) $purchaseOrder['id'],
                'duplicate_supplier_tracking',
                'Tracking number '
                    . $tracking['tracking_number']
                    . ' appears on another supplier purchase order.'
            );

            return [
                'matched' => true,
                'updated' => false,
                'skipped' => false,
                'failed' => true,
            ];
        }

        $rowId = $this->repository->logRow(
            $runId,
            $rowNumber,
            'matched',
            array_merge($tracking, [
                'match_strategy' =>
                    $purchaseOrder['match_strategy'] ?? null,
                'purchase_order_id' =>
                    (int) $purchaseOrder['id'],
                'order_id' =>
                    (int) $purchaseOrder['order_id'],
                'store_id' =>
                    (int) $purchaseOrder['store_id'],
                'supplier_id' =>
                    (int) $purchaseOrder['supplier_id'],
                'purchase_order_number' =>
                    $purchaseOrder['purchase_order_number'],
                'supplier_order_id' =>
                    $row['supplier_order_id']
                    ?? $row['provider_order_id']
                    ?? $row['external_order_id']
                    ?? null,
                'message' =>
                    'Purchase order matched and queued for update.',
                'raw_data' => $row,
            ])
        );

        $this->db->beginTransaction();

        try {
            $this->repository->upsertTrackingRecord(
                $purchaseOrder,
                $runId,
                $rowId,
                $tracking,
                $row
            );

            $this->repository->applyTracking(
                $purchaseOrder,
                $tracking
            );

            if (
                $tracking['normalized_status']
                === 'exception'
            ) {
                $this->repository->createException(
                    (int) $purchaseOrder['store_id'],
                    (int) $purchaseOrder['order_id'],
                    (int) $purchaseOrder['id'],
                    'supplier_tracking_exception',
                    'Supplier tracking feed reported an exception for tracking number '
                        . $tracking['tracking_number']
                        . '.'
                );
            }

            $this->db->commit();
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }

        return [
            'matched' => true,
            'updated' => true,
            'skipped' => false,
            'failed' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function trackingData(array $row): array
    {
        $trackingNumber = trim(
            (string) ($row['tracking_number'] ?? '')
        );

        if ($trackingNumber === '') {
            throw new RuntimeException(
                'tracking_number is required.'
            );
        }

        $carrier = $this->normalizeCarrier(
            (string) ($row['carrier'] ?? '')
        );

        $statusText = trim(
            (string) (
                $row['shipment_status']
                ?? $row['tracking_status']
                ?? $row['status']
                ?? ''
            )
        );

        $normalized = $this->normalizeStatus(
            $statusText
        );

        $trackingUrl = trim(
            (string) ($row['tracking_url'] ?? '')
        );

        if (
            $trackingUrl !== ''
            && ! filter_var(
                $trackingUrl,
                FILTER_VALIDATE_URL
            )
        ) {
            throw new RuntimeException(
                'tracking_url must be a valid URL when supplied.'
            );
        }

        $shippedAt = $this->dateValue(
            $row['shipped_date']
            ?? $row['shipped_at']
            ?? null
        );
        $deliveredAt = $this->dateValue(
            $row['delivered_date']
            ?? $row['delivered_at']
            ?? null
        );

        if (
            $normalized === 'delivered'
            && $deliveredAt === null
        ) {
            $deliveredAt = date('Y-m-d H:i:s');
        }

        if (
            in_array(
                $normalized,
                [
                    'in_transit',
                    'out_for_delivery',
                    'delivered',
                ],
                true
            )
            && $shippedAt === null
        ) {
            $shippedAt = date('Y-m-d H:i:s');
        }

        return [
            'carrier' =>
                $carrier !== '' ? $carrier : null,
            'tracking_number' => $trackingNumber,
            'tracking_url' =>
                $trackingUrl !== '' ? $trackingUrl : null,
            'shipment_status' =>
                $statusText !== ''
                    ? $statusText
                    : 'unknown',
            'normalized_status' => $normalized,
            'shipped_at' => $shippedAt,
            'delivered_at' => $deliveredAt,
        ];
    }

    private function normalizeCarrier(string $carrier): string
    {
        $carrier = trim($carrier);

        if ($carrier === '') {
            return '';
        }

        $key = strtolower(
            preg_replace('/[^a-z0-9]+/', '', $carrier)
            ?? $carrier
        );

        return match ($key) {
            'usps', 'unitedstatespostalservice' => 'USPS',
            'ups', 'unitedparcelservice' => 'UPS',
            'fedex', 'federalexpress' => 'FedEx',
            'dhl', 'dhlexpress' => 'DHL',
            default => mb_substr($carrier, 0, 100),
        };
    }

    private function normalizeStatus(string $status): string
    {
        $status = strtolower(trim($status));
        $status = preg_replace(
            '/[^a-z0-9]+/',
            '_',
            $status
        ) ?? '';
        $status = trim($status, '_');

        if ($status === '') {
            return 'unknown';
        }

        return match ($status) {
            'created',
            'label',
            'label_created',
            'pre_transit',
            'ready',
            'ready_to_ship' => 'label_created',

            'in_transit',
            'transit',
            'shipped',
            'picked_up',
            'pickup',
            'departed',
            'arrived',
            'on_the_way' => 'in_transit',

            'out_for_delivery',
            'ofd' => 'out_for_delivery',

            'delivered',
            'complete',
            'completed' => 'delivered',

            'exception',
            'delivery_exception',
            'failed',
            'failure',
            'return_to_sender',
            'returned',
            'lost',
            'damaged' => 'exception',

            default => 'unknown',
        };
    }

    private function dateValue(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            throw new RuntimeException(
                'Shipment date values must be valid dates.'
            );
        }

        return date('Y-m-d H:i:s', $timestamp);
    }

    /**
     * @return list<string>
     */
    private function normalizeHeaders(array $header): array
    {
        $normalized = [];

        foreach ($header as $index => $value) {
            $value = (string) $value;

            if ($index === 0) {
                $value = preg_replace(
                    '/^\xEF\xBB\xBF/',
                    '',
                    $value
                ) ?? $value;
            }

            $value = strtolower(trim($value));
            $value = preg_replace(
                '/[^a-z0-9]+/',
                '_',
                $value
            ) ?? '';
            $value = trim($value, '_');

            if ($value === '') {
                throw new RuntimeException(
                    'The CSV contains an empty header.'
                );
            }

            if (in_array($value, $normalized, true)) {
                throw new RuntimeException(
                    'Duplicate CSV header: ' . $value
                );
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    private function validateHeaders(array $headers): void
    {
        if (! in_array('tracking_number', $headers, true)) {
            throw new RuntimeException(
                'The CSV must include tracking_number.'
            );
        }

        $matchFields = [
            'purchase_order_number',
            'supplier_order_id',
            'provider_order_id',
            'external_order_id',
            'supplier_reference',
        ];

        foreach ($matchFields as $field) {
            if (in_array($field, $headers, true)) {
                return;
            }
        }

        throw new RuntimeException(
            'The CSV must include at least one match field: purchase_order_number, supplier_order_id, provider_order_id, external_order_id, or supplier_reference.'
        );
    }

    private function combineRow(array $headers, array $values): array
    {
        $row = [];

        foreach ($headers as $index => $header) {
            $row[$header] = trim(
                (string) ($values[$index] ?? '')
            );
        }

        return $row;
    }

    private function blankRow(array $values): bool
    {
        foreach ($values as $value) {
            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}

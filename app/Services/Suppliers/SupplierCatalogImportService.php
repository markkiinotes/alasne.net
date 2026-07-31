<?php

declare(strict_types=1);

namespace App\Services\Suppliers;

use App\Repositories\SupplierIntegrationRepository;
use PDO;
use RuntimeException;

class SupplierCatalogImportService
{
    private const MAX_ROWS = 50000;

    private const STOCK_STATUSES = [
        'unknown',
        'in_stock',
        'out_of_stock',
        'backorder',
        'discontinued',
    ];

    public function __construct(
        private PDO $db,
        private SupplierIntegrationRepository $integrations
    ) {
    }

    public function importCsv(
        int $supplierId,
        string $filePath,
        string $originalName,
        string $syncType
    ): array {
        if (
            ! in_array(
                $syncType,
                ['catalog', 'inventory'],
                true
            )
        ) {
            throw new RuntimeException(
                'Invalid supplier sync type.'
            );
        }

        $supplier =
            $this->integrations->supplier(
                $supplierId
            );

        if (! $supplier) {
            throw new RuntimeException(
                'Supplier not found.'
            );
        }

        $integration =
            $this->integrations->forSupplier(
                $supplierId
            );

        if (empty($integration['id'])) {
            throw new RuntimeException(
                'Save the supplier integration before importing a feed.'
            );
        }

        if (
            ($integration['provider_code'] ?? '')
            !== 'csv_feed'
        ) {
            throw new RuntimeException(
                'CSV imports require the CSV Catalog / Inventory Feed adapter.'
            );
        }

        $runId =
            $this->integrations->createSyncRun(
                $integration,
                $syncType,
                'uploaded_csv',
                $originalName
            );

        $counts = [
            'received' => 0,
            'processed' => 0,
            'created' => 0,
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
                    'The CSV file does not contain a header row.'
                );
            }

            $headers = $this->normalizeHeaders(
                $header
            );

            $this->validateHeaders(
                $headers,
                $syncType
            );

            $this->db->beginTransaction();

            $rowNumber = 1;

            while (
                ($values = fgetcsv($handle))
                !== false
            ) {
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
                        $supplier,
                        $row,
                        $syncType
                    );

                    $counts['processed']++;

                    if ($result === 'created') {
                        $counts['created']++;
                    } elseif ($result === 'updated') {
                        $counts['updated']++;
                    } else {
                        $counts['skipped']++;
                    }
                } catch (\Throwable $rowError) {
                    $counts['failed']++;

                    $this->integrations->addSyncError(
                        $runId,
                        $rowNumber,
                        $row['supplier_sku']
                            ?? null,
                        'row_validation_failed',
                        $rowError->getMessage()
                            ?: 'Unable to import this row.',
                        $row
                    );
                }
            }

            fclose($handle);

            $this->db->commit();

            $status = $counts['failed'] > 0
                ? (
                    $counts['processed'] > 0
                        ? 'partial'
                        : 'failed'
                )
                : 'succeeded';

            $this->integrations->completeSyncRun(
                $runId,
                $status,
                $counts
            );

            if ($counts['processed'] > 0) {
                $this->integrations->touchSync(
                    (int) $integration['id'],
                    $syncType
                );
            }

            return [
                'run_id' => $runId,
                'status' => $status,
                'counts' => $counts,
            ];
        } catch (\Throwable $exception) {
            if (
                isset($handle)
                && is_resource($handle)
            ) {
                fclose($handle);
            }

            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            $this->integrations->completeSyncRun(
                $runId,
                'failed',
                $counts,
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * @return list<string>
     */
    private function normalizeHeaders(
        array $header
    ): array {
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
                    'The CSV contains an empty header name.'
                );
            }

            if (in_array(
                $value,
                $normalized,
                true
            )) {
                throw new RuntimeException(
                    'The CSV contains a duplicate header: '
                    . $value
                );
            }

            $normalized[] = $value;
        }

        return $normalized;
    }

    private function validateHeaders(
        array $headers,
        string $syncType
    ): void {
        if (! in_array(
            'supplier_sku',
            $headers,
            true
        )) {
            throw new RuntimeException(
                'The CSV must include a supplier_sku column.'
            );
        }

        if (
            $syncType === 'catalog'
            && ! in_array(
                'product_id',
                $headers,
                true
            )
            && ! in_array(
                'product_sku',
                $headers,
                true
            )
        ) {
            /*
             * Existing mappings can still be updated using
             * supplier_sku alone, but new mappings need a
             * product identifier. Requiring the column keeps
             * one reusable template for mixed create/update
             * imports.
             */
            throw new RuntimeException(
                'Catalog CSV files must include product_id or product_sku.'
            );
        }
    }

    private function combineRow(
        array $headers,
        array $values
    ): array {
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

    private function processRow(
        array $supplier,
        array $row,
        string $syncType
    ): string {
        $supplierSku = trim(
            (string) (
                $row['supplier_sku'] ?? ''
            )
        );

        if ($supplierSku === '') {
            throw new RuntimeException(
                'supplier_sku is required.'
            );
        }

        $existing = $this->mappingBySupplierSku(
            (int) $supplier['id'],
            $supplierSku
        );

        if (
            $syncType === 'inventory'
            && ! $existing
        ) {
            throw new RuntimeException(
                'Inventory rows must match an existing supplier_sku mapping.'
            );
        }

        $sourceHash = $this->sourceHash($row);

        if (
            $existing
            && ($existing['source_hash'] ?? null)
                === $sourceHash
        ) {
            return 'skipped';
        }

        $created = false;

        if (! $existing) {
            $product = $this->resolveProduct(
                (int) $supplier['store_id'],
                $row
            );

            if (! $product) {
                throw new RuntimeException(
                    'No store product matches product_id or product_sku.'
                );
            }

            $mappingId = $this->createMapping(
                $supplier,
                $product,
                $row,
                $supplierSku
            );

            $existing = $this->mapping(
                $mappingId
            );

            $created = true;
        }

        if (! $existing) {
            throw new RuntimeException(
                'Unable to load the supplier mapping.'
            );
        }

        $changed = $this->updateMapping(
            $existing,
            $row,
            $syncType
        );

        if ($created) {
            return 'created';
        }

        return $changed
            ? 'updated'
            : 'skipped';
    }

    private function mappingBySupplierSku(
        int $supplierId,
        string $supplierSku
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_products
            WHERE supplier_id = :supplier_id
            AND supplier_sku = :supplier_sku
            ORDER BY id ASC
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'supplier_id' => $supplierId,
            'supplier_sku' => $supplierSku,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function mapping(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM supplier_products
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute(['id' => $id]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    private function resolveProduct(
        int $storeId,
        array $row
    ): ?array {
        $productId = (int) (
            $row['product_id'] ?? 0
        );

        if ($productId > 0) {
            $stmt = $this->db->prepare("
                SELECT *
                FROM products
                WHERE id = :id
                AND store_id = :store_id
                LIMIT 1
            ");

            $stmt->execute([
                'id' => $productId,
                'store_id' => $storeId,
            ]);

            $product = $stmt->fetch();

            if ($product) {
                return $product;
            }
        }

        $productSku = trim(
            (string) (
                $row['product_sku'] ?? ''
            )
        );

        if ($productSku === '') {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM products
            WHERE store_id = :store_id
            AND sku = :sku
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'sku' => $productSku,
        ]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    private function createMapping(
        array $supplier,
        array $product,
        array $row,
        string $supplierSku
    ): int {
        $preferred =
            $this->booleanValue(
                $row['is_preferred'] ?? ''
            );

        if ($preferred) {
            $clear = $this->db->prepare("
                UPDATE supplier_products
                SET is_preferred = 0,
                    updated_at = NOW()
                WHERE product_id = :product_id
            ");

            $clear->execute([
                'product_id' =>
                    (int) $product['id'],
            ]);
        }

        $cost = $this->decimalValue(
            $row['wholesale_cost'] ?? '',
            false
        );

        $stock = $this->stockValues($row);

        $stmt = $this->db->prepare("
            INSERT INTO supplier_products (
                supplier_id,
                store_id,
                product_id,
                supplier_sku,
                provider_product_id,
                wholesale_cost,
                currency,
                available_quantity,
                stock_status,
                lead_time_min,
                lead_time_max,
                minimum_order_quantity,
                pack_size,
                is_preferred,
                priority,
                last_synced_at,
                source_hash,
                source_updated_at,
                last_cost_synced_at,
                last_stock_synced_at,
                created_at,
                updated_at
            ) VALUES (
                :supplier_id,
                :store_id,
                :product_id,
                :supplier_sku,
                :provider_product_id,
                :wholesale_cost,
                :currency,
                :available_quantity,
                :stock_status,
                :lead_time_min,
                :lead_time_max,
                :minimum_order_quantity,
                :pack_size,
                :is_preferred,
                :priority,
                NOW(),
                :source_hash,
                :source_updated_at,
                NOW(),
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'supplier_id' =>
                (int) $supplier['id'],
            'store_id' =>
                (int) $supplier['store_id'],
            'product_id' =>
                (int) $product['id'],
            'supplier_sku' => $supplierSku,
            'provider_product_id' =>
                $this->nullable(
                    $row['provider_product_id']
                        ?? null
                ),
            'wholesale_cost' =>
                number_format(
                    $cost ?? 0,
                    2,
                    '.',
                    ''
                ),
            'currency' =>
                $this->currency(
                    $row['currency']
                        ?? $supplier['currency']
                        ?? 'USD'
                ),
            'available_quantity' =>
                $stock['available_quantity'],
            'stock_status' =>
                $stock['stock_status'],
            'lead_time_min' =>
                $this->integerValue(
                    $row['lead_time_min'] ?? '',
                    true
                ),
            'lead_time_max' =>
                $this->integerValue(
                    $row['lead_time_max'] ?? '',
                    true
                ),
            'minimum_order_quantity' =>
                $this->integerValue(
                    $row[
                        'minimum_order_quantity'
                    ] ?? '1',
                    false,
                    1
                ),
            'pack_size' =>
                $this->integerValue(
                    $row['pack_size'] ?? '1',
                    false,
                    1
                ),
            'is_preferred' =>
                $preferred ? 1 : 0,
            'priority' =>
                $this->integerValue(
                    $row['priority'] ?? '100',
                    false,
                    1
                ),
            'source_hash' =>
                $this->sourceHash($row),
            'source_updated_at' =>
                $this->dateTimeValue(
                    $row['source_updated_at']
                        ?? null
                ),
        ]);

        return (int) $this->db->lastInsertId();
    }

    private function updateMapping(
        array $existing,
        array $row,
        string $syncType
    ): bool {
        $updates = [];
        $params = ['id' => (int) $existing['id']];

        $set = static function (
            string $column,
            mixed $value
        ) use (&$updates, &$params): void {
            $parameter =
                'value_' . count($params);

            $updates[] =
                "`{$column}` = :{$parameter}";
            $params[$parameter] = $value;
        };

        if (
            array_key_exists(
                'provider_product_id',
                $row
            )
            && trim(
                (string) $row[
                    'provider_product_id'
                ]
            ) !== ''
        ) {
            $set(
                'provider_product_id',
                trim(
                    (string) $row[
                        'provider_product_id'
                    ]
                )
            );
        }

        if (
            array_key_exists(
                'wholesale_cost',
                $row
            )
            && trim(
                (string) $row[
                    'wholesale_cost'
                ]
            ) !== ''
        ) {
            $cost = $this->decimalValue(
                $row['wholesale_cost'],
                false
            );

            $set(
                'wholesale_cost',
                number_format(
                    $cost,
                    2,
                    '.',
                    ''
                )
            );

            $updates[] =
                'last_cost_synced_at = NOW()';
        }

        if (
            array_key_exists('currency', $row)
            && trim(
                (string) $row['currency']
            ) !== ''
        ) {
            $set(
                'currency',
                $this->currency(
                    $row['currency']
                )
            );
        }

        $stockFieldsPresent =
            (
                array_key_exists(
                    'available_quantity',
                    $row
                )
                && trim(
                    (string) $row[
                        'available_quantity'
                    ]
                ) !== ''
            )
            || (
                array_key_exists(
                    'stock_status',
                    $row
                )
                && trim(
                    (string) $row[
                        'stock_status'
                    ]
                ) !== ''
            );

        if ($stockFieldsPresent) {
            $stock = $this->stockValues(
                $row,
                $existing
            );

            $set(
                'available_quantity',
                $stock['available_quantity']
            );
            $set(
                'stock_status',
                $stock['stock_status']
            );

            $updates[] =
                'last_stock_synced_at = NOW()';
        }

        if ($syncType === 'catalog') {
            foreach ([
                'lead_time_min',
                'lead_time_max',
            ] as $field) {
                if (
                    array_key_exists($field, $row)
                    && trim(
                        (string) $row[$field]
                    ) !== ''
                ) {
                    $set(
                        $field,
                        $this->integerValue(
                            $row[$field],
                            true
                        )
                    );
                }
            }

            foreach ([
                'minimum_order_quantity',
                'pack_size',
                'priority',
            ] as $field) {
                if (
                    array_key_exists($field, $row)
                    && trim(
                        (string) $row[$field]
                    ) !== ''
                ) {
                    $set(
                        $field,
                        $this->integerValue(
                            $row[$field],
                            false,
                            1
                        )
                    );
                }
            }

            if (
                array_key_exists(
                    'is_preferred',
                    $row
                )
                && trim(
                    (string) $row[
                        'is_preferred'
                    ]
                ) !== ''
            ) {
                $preferred =
                    $this->booleanValue(
                        $row['is_preferred']
                    );

                if ($preferred) {
                    $clear = $this->db->prepare("
                        UPDATE supplier_products
                        SET is_preferred = 0,
                            updated_at = NOW()
                        WHERE product_id =
                            :product_id
                        AND id <> :mapping_id
                    ");

                    $clear->execute([
                        'product_id' =>
                            (int) $existing[
                                'product_id'
                            ],
                        'mapping_id' =>
                            (int) $existing['id'],
                    ]);
                }

                $set(
                    'is_preferred',
                    $preferred ? 1 : 0
                );
            }
        }

        $set(
            'source_hash',
            $this->sourceHash($row)
        );

        if (
            array_key_exists(
                'source_updated_at',
                $row
            )
            && trim(
                (string) $row[
                    'source_updated_at'
                ]
            ) !== ''
        ) {
            $set(
                'source_updated_at',
                $this->dateTimeValue(
                    $row[
                        'source_updated_at'
                    ]
                )
            );
        }

        $updates[] = 'last_synced_at = NOW()';
        $updates[] = 'updated_at = NOW()';

        if (empty($updates)) {
            return false;
        }

        $stmt = $this->db->prepare(
            "UPDATE supplier_products
             SET " . implode(', ', $updates) . "
             WHERE id = :id"
        );

        $stmt->execute($params);

        return true;
    }

    private function stockValues(
        array $row,
        array $existing = []
    ): array {
        $quantityText = trim(
            (string) (
                $row['available_quantity']
                ?? ''
            )
        );

        $quantity = $quantityText !== ''
            ? $this->integerValue(
                $quantityText,
                false,
                0
            )
            : (
                array_key_exists(
                    'available_quantity',
                    $existing
                )
                    ? $existing[
                        'available_quantity'
                    ]
                    : null
            );

        $status = strtolower(trim(
            (string) (
                $row['stock_status']
                ?? ''
            )
        ));

        if ($status === '') {
            if ($quantity === null) {
                $status = (string) (
                    $existing['stock_status']
                    ?? 'unknown'
                );
            } else {
                $status = (int) $quantity > 0
                    ? 'in_stock'
                    : 'out_of_stock';
            }
        }

        if (! in_array(
            $status,
            self::STOCK_STATUSES,
            true
        )) {
            throw new RuntimeException(
                'Invalid stock_status: ' . $status
            );
        }

        return [
            'available_quantity' =>
                $quantity !== null
                    ? (int) $quantity
                    : null,
            'stock_status' => $status,
        ];
    }

    private function decimalValue(
        mixed $value,
        bool $allowBlank
    ): ?float {
        $text = trim((string) $value);

        if ($text === '' && $allowBlank) {
            return null;
        }

        if (
            $text === ''
            || ! is_numeric($text)
        ) {
            throw new RuntimeException(
                'A valid wholesale_cost is required.'
            );
        }

        $number = round((float) $text, 2);

        if ($number < 0) {
            throw new RuntimeException(
                'wholesale_cost cannot be negative.'
            );
        }

        return $number;
    }

    private function integerValue(
        mixed $value,
        bool $allowBlank,
        int $minimum = 0
    ): ?int {
        $text = trim((string) $value);

        if ($text === '' && $allowBlank) {
            return null;
        }

        if (
            $text === ''
            || filter_var(
                $text,
                FILTER_VALIDATE_INT
            ) === false
        ) {
            throw new RuntimeException(
                'Expected a whole-number value.'
            );
        }

        $number = (int) $text;

        if ($number < $minimum) {
            throw new RuntimeException(
                'The value must be at least '
                . $minimum
                . '.'
            );
        }

        return $number;
    }

    private function booleanValue(
        mixed $value
    ): bool {
        return in_array(
            strtolower(trim((string) $value)),
            ['1', 'true', 'yes', 'y', 'on'],
            true
        );
    }

    private function currency(
        mixed $value
    ): string {
        $currency = strtoupper(
            trim((string) $value)
        );

        if (! preg_match(
            '/^[A-Z]{3}$/',
            $currency
        )) {
            throw new RuntimeException(
                'Currency must use a three-letter code.'
            );
        }

        return $currency;
    }

    private function nullable(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        return $value !== ''
            ? $value
            : null;
    }

    private function dateTimeValue(
        mixed $value
    ): ?string {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);

        if ($timestamp === false) {
            throw new RuntimeException(
                'source_updated_at is not a valid date.'
            );
        }

        return date(
            'Y-m-d H:i:s',
            $timestamp
        );
    }

    private function sourceHash(
        array $row
    ): string {
        ksort($row);

        return hash(
            'sha256',
            json_encode(
                $row,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
                | JSON_THROW_ON_ERROR
            )
        );
    }
}

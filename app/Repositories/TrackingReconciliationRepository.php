<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class TrackingReconciliationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function stores(): array
    {
        return $this->db->query("
            SELECT id, name
            FROM stores
            ORDER BY name ASC
        ")->fetchAll();
    }

    public function suppliers(int $storeId = 0): array
    {
        $sql = "
            SELECT id, name, code, store_id
            FROM suppliers
            WHERE 1 = 1
        ";
        $params = [];

        if ($storeId > 0) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function dashboard(array $filters): array
    {
        return [
            'summary' => $this->summary($filters),
            'runs' => $this->runs($filters, 15),
            'queue' => $this->queue($filters, 100),
            'unmatchedRows' => $this->unmatchedRows($filters, 25),
            'duplicateTracking' => $this->duplicateTracking($filters),
            'recentTracking' => $this->recentTracking($filters, 25),
        ];
    }

    public function createRun(
        ?int $storeId,
        ?int $supplierId,
        string $sourceFileName
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO tracking_reconciliation_runs (
                store_id,
                supplier_id,
                source_type,
                source_file_name,
                status,
                started_at,
                created_at
            ) VALUES (
                :store_id,
                :supplier_id,
                'csv_upload',
                :source_file_name,
                'running',
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $storeId && $storeId > 0
                ? $storeId
                : null,
            'supplier_id' => $supplierId && $supplierId > 0
                ? $supplierId
                : null,
            'source_file_name' =>
                $sourceFileName !== ''
                    ? $sourceFileName
                    : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function completeRun(
        int $runId,
        string $status,
        array $counts,
        ?string $errorMessage = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE tracking_reconciliation_runs
            SET
                status = :status,
                rows_received = :rows_received,
                rows_matched = :rows_matched,
                rows_updated = :rows_updated,
                rows_skipped = :rows_skipped,
                rows_failed = :rows_failed,
                error_message = :error_message,
                finished_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $runId,
            'status' => $status,
            'rows_received' => max(
                0,
                (int) ($counts['received'] ?? 0)
            ),
            'rows_matched' => max(
                0,
                (int) ($counts['matched'] ?? 0)
            ),
            'rows_updated' => max(
                0,
                (int) ($counts['updated'] ?? 0)
            ),
            'rows_skipped' => max(
                0,
                (int) ($counts['skipped'] ?? 0)
            ),
            'rows_failed' => max(
                0,
                (int) ($counts['failed'] ?? 0)
            ),
            'error_message' =>
                $errorMessage !== null
                    ? mb_substr($errorMessage, 0, 1000)
                    : null,
        ]);
    }

    public function logRow(
        int $runId,
        int $rowNumber,
        string $status,
        array $data
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO tracking_reconciliation_rows (
                run_id,
                row_number,
                status,
                match_strategy,
                purchase_order_id,
                order_id,
                store_id,
                supplier_id,
                purchase_order_number,
                supplier_order_id,
                carrier,
                tracking_number,
                tracking_url,
                shipment_status,
                normalized_status,
                message,
                raw_data,
                created_at
            ) VALUES (
                :run_id,
                :row_number,
                :status,
                :match_strategy,
                :purchase_order_id,
                :order_id,
                :store_id,
                :supplier_id,
                :purchase_order_number,
                :supplier_order_id,
                :carrier,
                :tracking_number,
                :tracking_url,
                :shipment_status,
                :normalized_status,
                :message,
                :raw_data,
                NOW()
            )
        ");

        $stmt->execute([
            'run_id' => $runId,
            'row_number' => $rowNumber,
            'status' => $status,
            'match_strategy' =>
                $this->nullable(
                    $data['match_strategy'] ?? null
                ),
            'purchase_order_id' =>
                $this->positiveIntOrNull(
                    $data['purchase_order_id'] ?? null
                ),
            'order_id' =>
                $this->positiveIntOrNull(
                    $data['order_id'] ?? null
                ),
            'store_id' =>
                $this->positiveIntOrNull(
                    $data['store_id'] ?? null
                ),
            'supplier_id' =>
                $this->positiveIntOrNull(
                    $data['supplier_id'] ?? null
                ),
            'purchase_order_number' =>
                $this->nullable(
                    $data['purchase_order_number'] ?? null
                ),
            'supplier_order_id' =>
                $this->nullable(
                    $data['supplier_order_id'] ?? null
                ),
            'carrier' =>
                $this->nullable(
                    $data['carrier'] ?? null
                ),
            'tracking_number' =>
                $this->nullable(
                    $data['tracking_number'] ?? null
                ),
            'tracking_url' =>
                $this->nullable(
                    $data['tracking_url'] ?? null
                ),
            'shipment_status' =>
                $this->nullable(
                    $data['shipment_status'] ?? null
                ),
            'normalized_status' =>
                $this->nullable(
                    $data['normalized_status'] ?? null
                ),
            'message' =>
                $this->nullable(
                    isset($data['message'])
                        ? mb_substr(
                            (string) $data['message'],
                            0,
                            1000
                        )
                        : null
                ),
            'raw_data' =>
                isset($data['raw_data'])
                    ? json_encode(
                        $data['raw_data'],
                        JSON_UNESCAPED_SLASHES
                        | JSON_UNESCAPED_UNICODE
                    )
                    : null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function findPurchaseOrder(
        array $row,
        ?int $storeId,
        ?int $supplierId
    ): ?array {
        $strategies = [
            'purchase_order_number' => [
                'column' => 'po.purchase_order_number',
                'value' => $row['purchase_order_number'] ?? '',
            ],
            'provider_order_id' => [
                'column' => 'po.provider_order_id',
                'value' => $row['supplier_order_id']
                    ?? $row['provider_order_id']
                    ?? '',
            ],
            'external_order_id' => [
                'column' => 'po.external_order_id',
                'value' => $row['external_order_id']
                    ?? $row['supplier_order_id']
                    ?? '',
            ],
            'supplier_reference' => [
                'column' => 'po.supplier_reference',
                'value' => $row['supplier_reference']
                    ?? $row['supplier_order_id']
                    ?? '',
            ],
        ];

        foreach ($strategies as $strategy => $config) {
            $value = trim((string) $config['value']);

            if ($value === '') {
                continue;
            }

            $sql = "
                SELECT
                    po.*,
                    sup.name AS supplier_name,
                    sup.code AS supplier_code,
                    o.order_number,
                    s.name AS store_name
                FROM purchase_orders po
                INNER JOIN suppliers sup
                    ON sup.id = po.supplier_id
                INNER JOIN orders o
                    ON o.id = po.order_id
                INNER JOIN stores s
                    ON s.id = po.store_id
                WHERE {$config['column']} = :value
            ";

            $params = ['value' => $value];

            if ($storeId !== null && $storeId > 0) {
                $sql .= ' AND po.store_id = :store_id';
                $params['store_id'] = $storeId;
            }

            if ($supplierId !== null && $supplierId > 0) {
                $sql .= ' AND po.supplier_id = :supplier_id';
                $params['supplier_id'] = $supplierId;
            }

            $sql .= ' ORDER BY po.id DESC LIMIT 2';

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            $matches = $stmt->fetchAll();

            if (count($matches) === 1) {
                $matches[0]['match_strategy'] = $strategy;

                return $matches[0];
            }

            if (count($matches) > 1) {
                return [
                    'ambiguous' => true,
                    'match_strategy' => $strategy,
                    'match_count' => count($matches),
                ];
            }
        }

        return null;
    }

    public function hasDuplicateTracking(
        int $purchaseOrderId,
        ?string $carrier,
        string $trackingNumber
    ): ?array {
        $sql = "
            SELECT
                str.*,
                po.purchase_order_number
            FROM supplier_tracking_records str
            INNER JOIN purchase_orders po
                ON po.id = str.purchase_order_id
            WHERE str.tracking_number = :tracking_number
            AND str.purchase_order_id <> :purchase_order_id
        ";

        $params = [
            'tracking_number' => $trackingNumber,
            'purchase_order_id' => $purchaseOrderId,
        ];

        if ($carrier !== null && trim($carrier) !== '') {
            $sql .= " AND (
                str.carrier = :carrier
                OR str.carrier IS NULL
                OR str.carrier = ''
            )";
            $params['carrier'] = $carrier;
        }

        $sql .= ' LIMIT 1';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $duplicate = $stmt->fetch();

        return $duplicate ?: null;
    }

    public function upsertTrackingRecord(
        array $purchaseOrder,
        int $runId,
        int $rowId,
        array $tracking,
        array $rawData
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO supplier_tracking_records (
                purchase_order_id,
                order_id,
                store_id,
                supplier_id,
                source_run_id,
                source_row_id,
                source_type,
                carrier,
                tracking_number,
                tracking_url,
                shipment_status,
                normalized_status,
                shipped_at,
                delivered_at,
                first_seen_at,
                last_seen_at,
                raw_data,
                created_at,
                updated_at
            ) VALUES (
                :purchase_order_id,
                :order_id,
                :store_id,
                :supplier_id,
                :source_run_id,
                :source_row_id,
                'csv_upload',
                :carrier,
                :tracking_number,
                :tracking_url,
                :shipment_status,
                :normalized_status,
                :shipped_at,
                :delivered_at,
                NOW(),
                NOW(),
                :raw_data,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                source_run_id = VALUES(source_run_id),
                source_row_id = VALUES(source_row_id),
                carrier = VALUES(carrier),
                tracking_url = VALUES(tracking_url),
                shipment_status = VALUES(shipment_status),
                normalized_status = VALUES(normalized_status),
                shipped_at = COALESCE(
                    VALUES(shipped_at),
                    shipped_at
                ),
                delivered_at = COALESCE(
                    VALUES(delivered_at),
                    delivered_at
                ),
                last_seen_at = NOW(),
                raw_data = VALUES(raw_data),
                updated_at = NOW()
        ");

        $stmt->execute([
            'purchase_order_id' => (int) $purchaseOrder['id'],
            'order_id' => (int) $purchaseOrder['order_id'],
            'store_id' => (int) $purchaseOrder['store_id'],
            'supplier_id' => (int) $purchaseOrder['supplier_id'],
            'source_run_id' => $runId,
            'source_row_id' => $rowId,
            'carrier' =>
                $this->nullable($tracking['carrier'] ?? null),
            'tracking_number' =>
                (string) $tracking['tracking_number'],
            'tracking_url' =>
                $this->nullable(
                    $tracking['tracking_url'] ?? null
                ),
            'shipment_status' =>
                (string) $tracking['shipment_status'],
            'normalized_status' =>
                (string) $tracking['normalized_status'],
            'shipped_at' =>
                $this->nullable(
                    $tracking['shipped_at'] ?? null
                ),
            'delivered_at' =>
                $this->nullable(
                    $tracking['delivered_at'] ?? null
                ),
            'raw_data' => json_encode(
                $rawData,
                JSON_UNESCAPED_SLASHES
                | JSON_UNESCAPED_UNICODE
            ),
        ]);
    }

    public function applyTracking(
        array $purchaseOrder,
        array $tracking
    ): void {
        $oldStatus = (string) $purchaseOrder['status'];
        $newStatus = $this->purchaseOrderStatus(
            $oldStatus,
            (string) $tracking['normalized_status']
        );

        $stmt = $this->db->prepare("
            UPDATE purchase_orders
            SET
                status = :status,
                shipping_carrier = :carrier,
                tracking_number = :tracking_number,
                tracking_url = :tracking_url,
                tracking_status = :tracking_status,
                tracking_source = 'csv_reconciliation',
                shipped_at = CASE
                    WHEN :shipped_flag = 1
                    THEN COALESCE(
                        :shipped_at,
                        shipped_at,
                        NOW()
                    )
                    ELSE shipped_at
                END,
                delivered_at = CASE
                    WHEN :delivered_flag = 1
                    THEN COALESCE(
                        :delivered_at,
                        delivered_at,
                        NOW()
                    )
                    ELSE delivered_at
                END,
                last_tracking_reconciled_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $purchaseOrder['id'],
            'status' => $newStatus,
            'carrier' =>
                $this->nullable(
                    $tracking['carrier'] ?? null
                ),
            'tracking_number' =>
                (string) $tracking['tracking_number'],
            'tracking_url' =>
                $this->nullable(
                    $tracking['tracking_url'] ?? null
                ),
            'tracking_status' =>
                (string) $tracking['normalized_status'],
            'shipped_flag' =>
                in_array(
                    $newStatus,
                    [
                        'partially_shipped',
                        'shipped',
                        'delivered',
                    ],
                    true
                )
                    ? 1
                    : 0,
            'delivered_flag' =>
                $newStatus === 'delivered' ? 1 : 0,
            'shipped_at' =>
                $this->nullable(
                    $tracking['shipped_at'] ?? null
                ),
            'delivered_at' =>
                $this->nullable(
                    $tracking['delivered_at'] ?? null
                ),
        ]);

        $this->purchaseOrderEvent(
            (int) $purchaseOrder['id'],
            'tracking_reconciled',
            'Tracking reconciled',
            $this->trackingDescription(
                $purchaseOrder,
                $tracking,
                $newStatus
            ),
            $oldStatus,
            $newStatus,
            true
        );

        $this->syncOrderDropshipStatus(
            (int) $purchaseOrder['order_id']
        );

        $this->syncCustomerOrderTracking(
            $purchaseOrder,
            $tracking,
            $newStatus
        );
    }

    public function createException(
        int $storeId,
        int $orderId,
        ?int $purchaseOrderId,
        string $code,
        string $message
    ): void {
        $exceptionCode = $purchaseOrderId !== null
            ? $code . '_po_' . $purchaseOrderId
            : $code;

        $stmt = $this->db->prepare("
            SELECT id
            FROM dropship_exceptions
            WHERE order_id = :order_id
            AND exception_code = :exception_code
            AND status = 'open'
            LIMIT 1
        ");
        $stmt->execute([
            'order_id' => $orderId,
            'exception_code' => $exceptionCode,
        ]);

        if ($stmt->fetchColumn()) {
            return;
        }

        $insert = $this->db->prepare("
            INSERT INTO dropship_exceptions (
                store_id,
                order_id,
                order_item_id,
                product_id,
                exception_code,
                message,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :order_id,
                NULL,
                NULL,
                :exception_code,
                :message,
                'open',
                NOW(),
                NOW()
            )
        ");

        $insert->execute([
            'store_id' => $storeId,
            'order_id' => $orderId,
            'exception_code' => $exceptionCode,
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    public function run(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT trr.*, s.name AS store_name, sup.name AS supplier_name
            FROM tracking_reconciliation_runs trr
            LEFT JOIN stores s
                ON s.id = trr.store_id
            LEFT JOIN suppliers sup
                ON sup.id = trr.supplier_id
            WHERE trr.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);

        $run = $stmt->fetch();

        return $run ?: null;
    }

    public function rowsForRun(int $runId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM tracking_reconciliation_rows
            WHERE run_id = :run_id
            ORDER BY row_number ASC, id ASC
        ");

        $stmt->execute(['run_id' => $runId]);

        return $stmt->fetchAll();
    }

    public function runs(array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT
                trr.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM tracking_reconciliation_runs trr
            LEFT JOIN stores s
                ON s.id = trr.store_id
            LEFT JOIN suppliers sup
                ON sup.id = trr.supplier_id
            WHERE 1 = 1
        ";
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND trr.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND trr.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY trr.id DESC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function queue(array $filters = [], int $limit = 100): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        $sql = "
            SELECT
                po.*,
                o.order_number,
                s.name AS store_name,
                sup.name AS supplier_name,
                sup.code AS supplier_code
            FROM purchase_orders po
            INNER JOIN orders o
                ON o.id = po.order_id
            INNER JOIN stores s
                ON s.id = po.store_id
            INNER JOIN suppliers sup
                ON sup.id = po.supplier_id
            WHERE {$where}
            AND (
                (
                    po.status IN (
                        'shipped',
                        'partially_shipped',
                        'delivered'
                    )
                    AND (
                        po.tracking_number IS NULL
                        OR po.tracking_number = ''
                    )
                )
                OR po.tracking_status IN (
                    'unknown',
                    'exception'
                )
                OR po.last_tracking_reconciled_at IS NULL
            )
            ORDER BY
                po.last_tracking_reconciled_at IS NULL DESC,
                po.updated_at DESC
            LIMIT " . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function unmatchedRows(array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT
                trrow.*,
                trr.source_file_name
            FROM tracking_reconciliation_rows trrow
            INNER JOIN tracking_reconciliation_runs trr
                ON trr.id = trrow.run_id
            WHERE trrow.status IN (
                'unmatched',
                'failed',
                'ambiguous',
                'duplicate_tracking'
            )
        ";
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= " AND (
                trrow.store_id = :store_id
                OR trr.store_id = :store_id
            )";
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= " AND (
                trrow.supplier_id = :supplier_id
                OR trr.supplier_id = :supplier_id
            )";
            $params['supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY trrow.id DESC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function duplicateTracking(array $filters = []): array
    {
        $sql = "
            SELECT
                carrier,
                tracking_number,
                COUNT(DISTINCT purchase_order_id) AS po_count,
                MAX(last_seen_at) AS last_seen_at
            FROM supplier_tracking_records
            WHERE tracking_number IS NOT NULL
            AND tracking_number <> ''
        ";
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $sql .= "
            GROUP BY carrier, tracking_number
            HAVING po_count > 1
            ORDER BY last_seen_at DESC
            LIMIT 25
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function recentTracking(array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT
                str.*,
                po.purchase_order_number,
                o.order_number,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM supplier_tracking_records str
            INNER JOIN purchase_orders po
                ON po.id = str.purchase_order_id
            INNER JOIN orders o
                ON o.id = str.order_id
            INNER JOIN stores s
                ON s.id = str.store_id
            INNER JOIN suppliers sup
                ON sup.id = str.supplier_id
            WHERE 1 = 1
        ";
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);
        if ($storeId > 0) {
            $sql .= ' AND str.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);
        if ($supplierId > 0) {
            $sql .= ' AND str.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        $sql .= "
            ORDER BY str.last_seen_at DESC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function summary(array $filters): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS purchase_orders,
                SUM(
                    po.tracking_number IS NULL
                    OR po.tracking_number = ''
                ) AS missing_tracking,
                SUM(po.tracking_status = 'exception') AS tracking_exceptions,
                SUM(po.last_tracking_reconciled_at IS NULL) AS never_reconciled,
                SUM(po.status = 'delivered') AS delivered,
                SUM(po.status IN ('shipped','partially_shipped')) AS shipped
            FROM purchase_orders po
            WHERE {$where}
        ");

        $stmt->execute($params);

        $po = $stmt->fetch() ?: [];

        $runStmt = $this->db->prepare("
            SELECT
                COUNT(*) AS runs,
                SUM(status = 'failed') AS failed_runs,
                SUM(status = 'partial') AS partial_runs
            FROM tracking_reconciliation_runs trr
            WHERE 1 = 1
        ");

        $runStmt->execute();

        $runs = $runStmt->fetch() ?: [];

        return [
            'purchase_orders' => (int) ($po['purchase_orders'] ?? 0),
            'missing_tracking' => (int) ($po['missing_tracking'] ?? 0),
            'tracking_exceptions' => (int) ($po['tracking_exceptions'] ?? 0),
            'never_reconciled' => (int) ($po['never_reconciled'] ?? 0),
            'delivered' => (int) ($po['delivered'] ?? 0),
            'shipped' => (int) ($po['shipped'] ?? 0),
            'runs' => (int) ($runs['runs'] ?? 0),
            'failed_runs' => (int) ($runs['failed_runs'] ?? 0),
            'partial_runs' => (int) ($runs['partial_runs'] ?? 0),
        ];
    }

    private function purchaseOrderStatus(
        string $current,
        string $normalizedTrackingStatus
    ): string {
        return match ($normalizedTrackingStatus) {
            'delivered' => 'delivered',
            'in_transit', 'out_for_delivery' => 'shipped',
            'label_created' => in_array(
                $current,
                [
                    'pending',
                    'submitted',
                    'accepted',
                ],
                true
            )
                ? 'partially_shipped'
                : $current,
            'exception' => $current === 'pending'
                ? 'accepted'
                : $current,
            default => $current,
        };
    }

    private function syncCustomerOrderTracking(
        array $purchaseOrder,
        array $tracking,
        string $newStatus
    ): void {
        $orderId = (int) $purchaseOrder['order_id'];

        $countStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM purchase_orders
            WHERE order_id = :order_id
        ");

        $countStmt->execute(['order_id' => $orderId]);

        if (
            (int) $countStmt->fetchColumn() === 1
            && trim((string) $tracking['tracking_number']) !== ''
        ) {
            $update = $this->db->prepare("
                UPDATE orders
                SET
                    shipping_carrier = :carrier,
                    tracking_number = :tracking_number,
                    tracking_url = :tracking_url,
                    shipped_at = CASE
                        WHEN :shipped_flag = 1
                        THEN COALESCE(
                            shipped_at,
                            :shipped_at,
                            NOW()
                        )
                        ELSE shipped_at
                    END,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $update->execute([
                'id' => $orderId,
                'carrier' =>
                    $this->nullable(
                        $tracking['carrier'] ?? null
                    ),
                'tracking_number' =>
                    (string) $tracking['tracking_number'],
                'tracking_url' =>
                    $this->nullable(
                        $tracking['tracking_url'] ?? null
                    ),
                'shipped_flag' =>
                    in_array(
                        $newStatus,
                        [
                            'partially_shipped',
                            'shipped',
                            'delivered',
                        ],
                        true
                    )
                        ? 1
                        : 0,
                'shipped_at' =>
                    $this->nullable(
                        $tracking['shipped_at'] ?? null
                    ),
            ]);
        }

        $description = $this->trackingDescription(
            $purchaseOrder,
            $tracking,
            $newStatus
        );

        $event = $this->db->prepare("
            INSERT INTO order_events (
                order_id,
                type,
                title,
                description,
                old_value,
                new_value,
                is_public,
                created_at
            ) VALUES (
                :order_id,
                'tracking_reconciled',
                :title,
                :description,
                :old_value,
                :new_value,
                1,
                NOW()
            )
        ");

        $event->execute([
            'order_id' => $orderId,
            'title' => 'Tracking updated',
            'description' => $description,
            'old_value' => $purchaseOrder['status'] ?? null,
            'new_value' => $newStatus,
        ]);
    }

    private function syncOrderDropshipStatus(int $orderId): void
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_count,
                SUM(status = 'delivered') AS delivered_count,
                SUM(status IN ('shipped','partially_shipped')) AS shipped_count,
                SUM(status IN ('failed','cancelled')) AS problem_count
            FROM purchase_orders
            WHERE order_id = :order_id
        ");

        $stmt->execute(['order_id' => $orderId]);
        $summary = $stmt->fetch() ?: [];

        $total = (int) ($summary['total_count'] ?? 0);
        $delivered = (int) ($summary['delivered_count'] ?? 0);
        $shipped = (int) ($summary['shipped_count'] ?? 0);
        $problems = (int) ($summary['problem_count'] ?? 0);

        $status = 'routed';

        if ($total > 0 && $delivered === $total) {
            $status = 'delivered';
        } elseif ($problems > 0) {
            $status = 'attention';
        } elseif ($shipped > 0) {
            $status = 'partially_shipped';
        }

        $update = $this->db->prepare("
            UPDATE orders
            SET dropship_status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $update->execute([
            'id' => $orderId,
            'status' => $status,
        ]);
    }

    private function purchaseOrderEvent(
        int $purchaseOrderId,
        string $type,
        string $title,
        ?string $description,
        ?string $oldValue,
        ?string $newValue,
        bool $isPublic
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO purchase_order_events (
                purchase_order_id,
                event_type,
                title,
                description,
                old_value,
                new_value,
                is_public,
                created_at
            ) VALUES (
                :purchase_order_id,
                :event_type,
                :title,
                :description,
                :old_value,
                :new_value,
                :is_public,
                NOW()
            )
        ");

        $stmt->execute([
            'purchase_order_id' => $purchaseOrderId,
            'event_type' => $type,
            'title' => $title,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    private function trackingDescription(
        array $purchaseOrder,
        array $tracking,
        string $status
    ): string {
        $description =
            'Tracking for purchase order '
            . $purchaseOrder['purchase_order_number']
            . ' was reconciled as '
            . ucwords(str_replace('_', ' ', $status))
            . '.';

        if (trim((string) ($tracking['tracking_number'] ?? '')) !== '') {
            $description .=
                ' Tracking: '
                . $tracking['tracking_number'];
        }

        if (trim((string) ($tracking['carrier'] ?? '')) !== '') {
            $description .=
                ' via '
                . $tracking['carrier'];
        }

        $description .= '.';

        return mb_substr($description, 0, 1000);
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function purchaseOrderWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $storeId = (int) ($filters['store_id'] ?? 0);

        if ($storeId > 0) {
            $where[] = 'po.store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $supplierId = (int) ($filters['supplier_id'] ?? 0);

        if ($supplierId > 0) {
            $where[] = 'po.supplier_id = :supplier_id';
            $params['supplier_id'] = $supplierId;
        }

        return [implode(' AND ', $where), $params];
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function positiveIntOrNull(mixed $value): ?int
    {
        $value = (int) $value;

        return $value > 0 ? $value : null;
    }
}

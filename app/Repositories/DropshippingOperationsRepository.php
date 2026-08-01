<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class DropshippingOperationsRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function dashboard(array $filters): array
    {
        return [
            'summary' => $this->summary($filters),
            'ordersNeedingRouting' =>
                $this->ordersNeedingRouting($filters),
            'openExceptions' =>
                $this->openExceptions($filters),
            'purchaseOrdersAwaitingSubmission' =>
                $this->purchaseOrdersAwaitingSubmission($filters),
            'failedSubmissions' =>
                $this->failedSubmissions($filters),
            'latePurchaseOrders' =>
                $this->latePurchaseOrders($filters),
            'missingTracking' =>
                $this->missingTracking($filters),
            'failedSyncRuns' =>
                $this->failedSyncRuns($filters),
            'lowMarginPurchaseOrders' =>
                $this->lowMarginPurchaseOrders($filters),
            'supplierPerformance' =>
                $this->supplierPerformance($filters),
            'recentActivity' =>
                $this->recentActivity($filters),
        ];
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

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRows(array $filters): array
    {
        $rows = [];

        foreach ($this->ordersNeedingRouting($filters, 1000) as $order) {
            $rows[] = [
                'queue' => 'Orders Needing Routing',
                'reference' => $order['order_number'] ?? '',
                'store' => $order['store_name'] ?? '',
                'supplier' => '',
                'status' => $order['dropship_status'] ?? 'unrouted',
                'amount' => $order['grand_total'] ?? '',
                'date' => $order['created_at'] ?? '',
                'action_url' => '/admin/orders/' . $order['id'] . '/dropship',
                'note' => 'Paid order has not been routed to suppliers.',
            ];
        }

        foreach ($this->openExceptions($filters, 1000) as $exception) {
            $rows[] = [
                'queue' => 'Open Fulfillment Exceptions',
                'reference' => $exception['order_number'] ?? '',
                'store' => $exception['store_name'] ?? '',
                'supplier' => '',
                'status' => $exception['status'] ?? 'open',
                'amount' => '',
                'date' => $exception['created_at'] ?? '',
                'action_url' => '/admin/orders/' . $exception['order_id'] . '/dropship',
                'note' => $exception['message'] ?? '',
            ];
        }

        foreach ($this->purchaseOrdersAwaitingSubmission($filters, 1000) as $po) {
            $rows[] = [
                'queue' => 'Purchase Orders Awaiting Submission',
                'reference' => $po['purchase_order_number'] ?? '',
                'store' => $po['store_name'] ?? '',
                'supplier' => $po['supplier_name'] ?? '',
                'status' => $po['submission_status'] ?? 'not_prepared',
                'amount' => $po['total_cost'] ?? '',
                'date' => $po['created_at'] ?? '',
                'action_url' => '/admin/purchase-orders/' . $po['id'],
                'note' => 'Supplier purchase order needs submission action.',
            ];
        }

        foreach ($this->latePurchaseOrders($filters, 1000) as $po) {
            $rows[] = [
                'queue' => 'Late Purchase Orders',
                'reference' => $po['purchase_order_number'] ?? '',
                'store' => $po['store_name'] ?? '',
                'supplier' => $po['supplier_name'] ?? '',
                'status' => $po['status'] ?? '',
                'amount' => $po['total_cost'] ?? '',
                'date' => $po['expected_ship_at'] ?? '',
                'action_url' => '/admin/purchase-orders/' . $po['id'],
                'note' => 'Expected ship date has passed.',
            ];
        }

        foreach ($this->failedSubmissions($filters, 1000) as $submission) {
            $rows[] = [
                'queue' => 'Failed Supplier Submissions',
                'reference' => $submission['purchase_order_number'] ?? '',
                'store' => $submission['store_name'] ?? '',
                'supplier' => $submission['supplier_name'] ?? '',
                'status' => $submission['status'] ?? '',
                'amount' => $submission['total_cost'] ?? '',
                'date' => $submission['updated_at'] ?? '',
                'action_url' => '/admin/supplier-submissions/' . $submission['id'],
                'note' => $submission['error_message'] ?? '',
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(array $filters): array
    {
        $summary = [
            'paid_unrouted_orders' => $this->countPaidUnroutedOrders($filters),
            'open_exceptions' => $this->countOpenExceptions($filters),
            'po_awaiting_submission' => $this->countPurchaseOrdersAwaitingSubmission($filters),
            'failed_submissions' => $this->countFailedSubmissions($filters),
            'late_purchase_orders' => $this->countLatePurchaseOrders($filters),
            'missing_tracking' => $this->countMissingTracking($filters),
            'failed_sync_runs' => $this->countFailedSyncRuns($filters),
        ];

        $totals = $this->financialTotals($filters);

        return array_merge($summary, $totals, [
            'attention_total' =>
                (int) $summary['paid_unrouted_orders']
                + (int) $summary['open_exceptions']
                + (int) $summary['po_awaiting_submission']
                + (int) $summary['failed_submissions']
                + (int) $summary['late_purchase_orders']
                + (int) $summary['missing_tracking']
                + (int) $summary['failed_sync_runs'],
        ]);
    }

    private function countPaidUnroutedOrders(array $filters): int
    {
        [$where, $params] = $this->orderWhere($filters);

        $sql = "
            SELECT COUNT(*)
            FROM orders o
            WHERE {$where}
            AND (
                o.payment_status = 'paid'
                OR o.status = 'paid'
            )
            AND (
                o.dropship_status IS NULL
                OR o.dropship_status IN (
                    'unrouted',
                    'not_routed',
                    'routing_failed'
                )
            )
        ";

        return $this->count($sql, $params);
    }

    private function countOpenExceptions(array $filters): int
    {
        [$where, $params] = $this->exceptionWhere($filters);

        return $this->count("
            SELECT COUNT(*)
            FROM dropship_exceptions de
            INNER JOIN orders o ON o.id = de.order_id
            WHERE {$where}
            AND de.status = 'open'
        ", $params);
    }

    private function countPurchaseOrdersAwaitingSubmission(array $filters): int
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        return $this->count("
            SELECT COUNT(*)
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            WHERE {$where}
            AND po.status NOT IN ('cancelled', 'failed', 'delivered')
            AND (
                po.submission_status IS NULL
                OR po.submission_status IN (
                    'not_prepared',
                    'prepared',
                    'awaiting_manual'
                )
            )
        ", $params);
    }

    private function countFailedSubmissions(array $filters): int
    {
        [$where, $params] = $this->submissionWhere($filters);

        return $this->count("
            SELECT COUNT(*)
            FROM supplier_order_submissions sos
            INNER JOIN purchase_orders po
                ON po.id = sos.purchase_order_id
            INNER JOIN suppliers sup ON sup.id = sos.supplier_id
            WHERE {$where}
            AND sos.status = 'failed'
        ", $params);
    }

    private function countLatePurchaseOrders(array $filters): int
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        return $this->count("
            SELECT COUNT(*)
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            WHERE {$where}
            AND po.expected_ship_at IS NOT NULL
            AND po.expected_ship_at < NOW()
            AND po.status NOT IN (
                'delivered',
                'cancelled',
                'failed'
            )
        ", $params);
    }

    private function countMissingTracking(array $filters): int
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        return $this->count("
            SELECT COUNT(*)
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            WHERE {$where}
            AND po.status IN (
                'partially_shipped',
                'shipped'
            )
            AND (
                po.tracking_number IS NULL
                OR TRIM(po.tracking_number) = ''
            )
        ", $params);
    }

    private function countFailedSyncRuns(array $filters): int
    {
        [$where, $params] = $this->syncWhere($filters);

        return $this->count("
            SELECT COUNT(*)
            FROM supplier_sync_runs ssr
            INNER JOIN suppliers sup ON sup.id = ssr.supplier_id
            WHERE {$where}
            AND (
                ssr.status IN ('failed', 'partial')
                OR ssr.rows_failed > 0
            )
        ", $params);
    }

    /**
     * @return array<string, float>
     */
    private function financialTotals(array $filters): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);
        $params['date_from'] = $this->dateFrom($filters);

        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(po.total_cost), 0) AS supplier_cost,
                COALESCE(SUM(po.customer_revenue), 0) AS revenue,
                COALESCE(SUM(po.estimated_profit), 0) AS profit,
                COUNT(*) AS purchase_order_count
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            WHERE {$where}
            AND po.created_at >= :date_from
            AND po.status NOT IN ('cancelled')
        ");
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        $revenue = (float) ($row['revenue'] ?? 0);
        $profit = (float) ($row['profit'] ?? 0);

        return [
            'supplier_cost_total' => (float) ($row['supplier_cost'] ?? 0),
            'routed_revenue_total' => $revenue,
            'gross_profit_total' => $profit,
            'margin_percent' => $revenue > 0
                ? round(($profit / $revenue) * 100, 3)
                : 0.0,
            'purchase_order_count' => (int) ($row['purchase_order_count'] ?? 0),
        ];
    }

    public function ordersNeedingRouting(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->orderWhere($filters);

        return $this->fetchAll("
            SELECT
                o.id,
                o.order_number,
                o.status,
                o.payment_status,
                o.dropship_status,
                o.grand_total,
                o.created_at,
                s.name AS store_name,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                c.email AS customer_email
            FROM orders o
            INNER JOIN stores s ON s.id = o.store_id
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE {$where}
            AND (
                o.payment_status = 'paid'
                OR o.status = 'paid'
            )
            AND (
                o.dropship_status IS NULL
                OR o.dropship_status IN (
                    'unrouted',
                    'not_routed',
                    'routing_failed'
                )
            )
            ORDER BY o.created_at ASC, o.id ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function openExceptions(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->exceptionWhere($filters);

        return $this->fetchAll("
            SELECT
                de.*,
                o.order_number,
                s.name AS store_name,
                p.name AS product_name,
                p.sku AS product_sku
            FROM dropship_exceptions de
            INNER JOIN orders o ON o.id = de.order_id
            INNER JOIN stores s ON s.id = de.store_id
            LEFT JOIN products p ON p.id = de.product_id
            WHERE {$where}
            AND de.status = 'open'
            ORDER BY de.created_at ASC, de.id ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function purchaseOrdersAwaitingSubmission(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        return $this->fetchAll("
            SELECT
                po.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            WHERE {$where}
            AND po.status NOT IN ('cancelled', 'failed', 'delivered')
            AND (
                po.submission_status IS NULL
                OR po.submission_status IN (
                    'not_prepared',
                    'prepared',
                    'awaiting_manual'
                )
            )
            ORDER BY po.created_at ASC, po.id ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function failedSubmissions(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->submissionWhere($filters);

        return $this->fetchAll("
            SELECT
                sos.*,
                po.purchase_order_number,
                po.total_cost,
                o.order_number,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                s.name AS store_name
            FROM supplier_order_submissions sos
            INNER JOIN purchase_orders po
                ON po.id = sos.purchase_order_id
            INNER JOIN suppliers sup ON sup.id = sos.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            WHERE {$where}
            AND sos.status = 'failed'
            ORDER BY sos.updated_at ASC, sos.id ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function latePurchaseOrders(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        return $this->fetchAll("
            SELECT
                po.*,
                TIMESTAMPDIFF(DAY, po.expected_ship_at, NOW()) AS days_late,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            WHERE {$where}
            AND po.expected_ship_at IS NOT NULL
            AND po.expected_ship_at < NOW()
            AND po.status NOT IN (
                'delivered',
                'cancelled',
                'failed'
            )
            ORDER BY po.expected_ship_at ASC, po.id ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function missingTracking(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);

        return $this->fetchAll("
            SELECT
                po.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            WHERE {$where}
            AND po.status IN (
                'partially_shipped',
                'shipped'
            )
            AND (
                po.tracking_number IS NULL
                OR TRIM(po.tracking_number) = ''
            )
            ORDER BY po.updated_at ASC, po.id ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function failedSyncRuns(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->syncWhere($filters);

        return $this->fetchAll("
            SELECT
                ssr.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                s.name AS store_name
            FROM supplier_sync_runs ssr
            INNER JOIN suppliers sup ON sup.id = ssr.supplier_id
            INNER JOIN stores s ON s.id = ssr.store_id
            WHERE {$where}
            AND (
                ssr.status IN ('failed', 'partial')
                OR ssr.rows_failed > 0
            )
            ORDER BY ssr.started_at DESC, ssr.id DESC
            LIMIT " . $this->limit($limit), $params);
    }

    public function lowMarginPurchaseOrders(array $filters, int $limit = 15): array
    {
        [$where, $params] = $this->purchaseOrderWhere($filters);
        $params['date_from'] = $this->dateFrom($filters);
        $params['min_margin'] = (float) ($filters['min_margin'] ?? 20);

        return $this->fetchAll("
            SELECT
                po.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            WHERE {$where}
            AND po.created_at >= :date_from
            AND po.status NOT IN ('cancelled')
            AND po.customer_revenue > 0
            AND po.estimated_margin_percent < :min_margin
            ORDER BY po.estimated_margin_percent ASC, po.created_at DESC
            LIMIT " . $this->limit($limit), $params);
    }

    public function supplierPerformance(array $filters, int $limit = 20): array
    {
        [$where, $params] = $this->supplierWhere($filters);
        $params['date_from'] = $this->dateFrom($filters);

        return $this->fetchAll("
            SELECT
                sup.id,
                sup.name,
                sup.code,
                sup.status,
                s.name AS store_name,
                COUNT(po.id) AS purchase_order_count,
                COALESCE(SUM(po.total_cost), 0) AS supplier_cost,
                COALESCE(SUM(po.customer_revenue), 0) AS customer_revenue,
                COALESCE(SUM(po.estimated_profit), 0) AS estimated_profit,
                CASE
                    WHEN COALESCE(SUM(po.customer_revenue), 0) > 0
                    THEN (
                        COALESCE(SUM(po.estimated_profit), 0)
                        / COALESCE(SUM(po.customer_revenue), 0)
                    ) * 100
                    ELSE 0
                END AS margin_percent,
                SUM(po.status = 'delivered') AS delivered_count,
                SUM(po.status IN ('failed', 'cancelled')) AS problem_count,
                SUM(
                    po.expected_ship_at IS NOT NULL
                    AND po.expected_ship_at < NOW()
                    AND po.status NOT IN (
                        'delivered',
                        'cancelled',
                        'failed'
                    )
                ) AS late_count,
                SUM(sos.status = 'failed') AS failed_submission_count,
                MAX(po.updated_at) AS last_purchase_order_at
            FROM suppliers sup
            INNER JOIN stores s ON s.id = sup.store_id
            LEFT JOIN purchase_orders po
                ON po.supplier_id = sup.id
                AND po.created_at >= :date_from
            LEFT JOIN supplier_order_submissions sos
                ON sos.purchase_order_id = po.id
            WHERE {$where}
            GROUP BY sup.id
            HAVING purchase_order_count > 0
                OR late_count > 0
                OR failed_submission_count > 0
            ORDER BY
                late_count DESC,
                failed_submission_count DESC,
                estimated_profit DESC,
                purchase_order_count DESC,
                sup.name ASC
            LIMIT " . $this->limit($limit), $params);
    }

    public function recentActivity(array $filters, int $limit = 20): array
    {
        [$poWhere, $poParams] = $this->purchaseOrderWhere($filters, 'po');
        [$syncWhere, $syncParams] = $this->syncWhere($filters, 'ssr');

        $params = [];
        $poWhere = $this->renameParams($poWhere, $poParams, 'poa', $params);
        $syncWhere = $this->renameParams($syncWhere, $syncParams, 'sra', $params);

        return $this->fetchAll("
            SELECT * FROM (
                SELECT
                    'purchase_order' AS activity_type,
                    po.updated_at AS activity_at,
                    po.purchase_order_number AS reference,
                    po.status AS status,
                    sup.name AS supplier_name,
                    s.name AS store_name,
                    CONCAT(
                        'Purchase order ',
                        po.purchase_order_number,
                        ' is ',
                        REPLACE(po.status, '_', ' ')
                    ) AS message,
                    CONCAT('/admin/purchase-orders/', po.id) AS action_url
                FROM purchase_orders po
                INNER JOIN suppliers sup ON sup.id = po.supplier_id
                INNER JOIN stores s ON s.id = po.store_id
                WHERE {$poWhere}

                UNION ALL

                SELECT
                    'supplier_sync' AS activity_type,
                    COALESCE(ssr.finished_at, ssr.started_at) AS activity_at,
                    CONCAT('Sync #', ssr.id) AS reference,
                    ssr.status AS status,
                    sup.name AS supplier_name,
                    s.name AS store_name,
                    CONCAT(
                        UPPER(ssr.sync_type),
                        ' sync ',
                        ssr.status,
                        ': ',
                        ssr.rows_created,
                        ' created, ',
                        ssr.rows_updated,
                        ' updated, ',
                        ssr.rows_failed,
                        ' failed'
                    ) AS message,
                    CONCAT(
                        '/admin/suppliers/',
                        ssr.supplier_id,
                        '/integration/sync-runs/',
                        ssr.id
                    ) AS action_url
                FROM supplier_sync_runs ssr
                INNER JOIN suppliers sup ON sup.id = ssr.supplier_id
                INNER JOIN stores s ON s.id = ssr.store_id
                WHERE {$syncWhere}
            ) activity
            ORDER BY activity_at DESC
            LIMIT " . $this->limit($limit), $params);
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function orderWhere(array $filters): array
    {
        $where = '1 = 1';
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where .= ' AND o.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        return [$where, $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function exceptionWhere(array $filters): array
    {
        $where = '1 = 1';
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where .= ' AND de.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        return [$where, $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function purchaseOrderWhere(array $filters, string $alias = 'po'): array
    {
        $where = '1 = 1';
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where .= " AND {$alias}.store_id = :store_id";
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $where .= " AND {$alias}.supplier_id = :supplier_id";
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        return [$where, $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function submissionWhere(array $filters): array
    {
        $where = '1 = 1';
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where .= ' AND po.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $where .= ' AND sos.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        return [$where, $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function syncWhere(array $filters, string $alias = 'ssr'): array
    {
        $where = '1 = 1';
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where .= " AND {$alias}.store_id = :store_id";
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $where .= " AND {$alias}.supplier_id = :supplier_id";
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        return [$where, $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function supplierWhere(array $filters): array
    {
        $where = '1 = 1';
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where .= ' AND sup.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $where .= ' AND sup.id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        return [$where, $params];
    }

    private function dateFrom(array $filters): string
    {
        $days = max(
            1,
            min(
                365,
                (int) ($filters['lookback_days'] ?? 30)
            )
        );

        return date('Y-m-d H:i:s', strtotime('-' . $days . ' days'));
    }

    private function count(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function fetchAll(string $sql, array $params): array
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function limit(int $limit): int
    {
        return max(1, min(1000, $limit));
    }

    /**
     * @param array<string, mixed> $sourceParams
     * @param array<string, mixed> $targetParams
     */
    private function renameParams(
        string $where,
        array $sourceParams,
        string $prefix,
        array &$targetParams
    ): string {
        foreach ($sourceParams as $name => $value) {
            $newName = $prefix . '_' . $name;
            $where = str_replace(
                ':' . $name,
                ':' . $newName,
                $where
            );
            $targetParams[$newName] = $value;
        }

        return $where;
    }
}

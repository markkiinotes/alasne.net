<?php

declare(strict_types=1);

namespace App\Services\Admin;

use PDO;

class ReportsKpiService
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function report(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        return [
            'filters' => $filters,
            'stores' => $this->stores(),
            'suppliers' => $this->suppliers((int) $filters['store_id']),
            'summary' => $this->summary($filters),
            'salesByStore' => $this->salesByStore($filters),
            'supplierPerformance' => $this->supplierPerformance($filters),
            'operations' => $this->operations($filters),
            'returns' => $this->returns($filters),
            'storeReadiness' => $this->storeReadiness($filters),
            'sourcing' => $this->sourcing($filters),
            'recentOrders' => $this->recentOrders($filters),
            'breadcrumbs' => [
                ['label' => 'Mission Control', 'url' => '/admin'],
                ['label' => 'Reports & KPI Center', 'url' => null],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function exportRows(array $filters): array
    {
        $report = $this->report($filters);
        $summary = $report['summary'];
        $rows = [];

        $rows[] = [
            'section' => 'Summary',
            'name' => 'Sales Revenue',
            'value' => $summary['revenue'],
            'detail' => $summary['paid_orders'] . ' paid order(s)',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Average Order Value',
            'value' => $summary['average_order_value'],
            'detail' => '',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Supplier Cost',
            'value' => $summary['supplier_cost'],
            'detail' => '',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Gross Profit',
            'value' => $summary['gross_profit'],
            'detail' => $summary['margin_percent'] . '% margin',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Open Returns',
            'value' => $summary['open_returns'],
            'detail' => '',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Tracking Gaps',
            'value' => $summary['tracking_gaps'],
            'detail' => '',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Open Fulfillment Exceptions',
            'value' => $summary['open_exceptions'],
            'detail' => '',
        ];
        $rows[] = [
            'section' => 'Summary',
            'name' => 'Blocked Stores',
            'value' => $summary['blocked_stores'],
            'detail' => '',
        ];

        foreach ($report['salesByStore'] as $row) {
            $rows[] = [
                'section' => 'Sales by Store',
                'name' => (string) $row['store_name'],
                'value' => (string) $row['revenue'],
                'detail' =>
                    (int) $row['paid_orders']
                    . ' paid order(s), '
                    . (float) $row['margin_percent']
                    . '% margin',
            ];
        }

        foreach ($report['supplierPerformance'] as $row) {
            $rows[] = [
                'section' => 'Supplier Performance',
                'name' => (string) $row['supplier_name'],
                'value' => (string) $row['gross_profit'],
                'detail' =>
                    'Revenue '
                    . $row['revenue']
                    . ', Cost '
                    . $row['supplier_cost']
                    . ', Score '
                    . $row['performance_score'],
            ];
        }

        foreach ($report['operations'] as $row) {
            $rows[] = [
                'section' => 'Operations',
                'name' => (string) $row['metric'],
                'value' => (string) $row['value'],
                'detail' => (string) $row['detail'],
            ];
        }

        foreach ($report['returns'] as $row) {
            $rows[] = [
                'section' => 'Returns',
                'name' => (string) $row['status'],
                'value' => (string) $row['return_count'],
                'detail' => (string) $row['approved_value'],
            ];
        }

        foreach ($report['storeReadiness'] as $row) {
            $rows[] = [
                'section' => 'Store Readiness',
                'name' => (string) $row['store_name'],
                'value' => (string) $row['automation_health_score'],
                'detail' =>
                    'Launch '
                    . ($row['automation_launch_status'] ?? 'planning')
                    . ', Status '
                    . ($row['store_status'] ?? ''),
            ];
        }

        foreach ($report['sourcing'] as $row) {
            $rows[] = [
                'section' => 'Product Sourcing',
                'name' => (string) $row['status'],
                'value' => (string) $row['product_count'],
                'detail' =>
                    'Average score '
                    . (string) $row['average_score'],
            ];
        }

        return $rows;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(array $filters): array
    {
        $sales = $this->salesSummary($filters);
        $supplierCost = $sales['supplier_cost'];
        $grossProfit = $sales['gross_profit'];
        $revenue = $sales['revenue'];

        if ($grossProfit === null) {
            $grossProfit = round($revenue - $supplierCost, 2);
        }

        $margin = $revenue > 0
            ? round(($grossProfit / $revenue) * 100, 3)
            : 0.0;

        return [
            'revenue' => round($revenue, 2),
            'paid_orders' => (int) $sales['paid_orders'],
            'average_order_value' =>
                (int) $sales['paid_orders'] > 0
                    ? round($revenue / (int) $sales['paid_orders'], 2)
                    : 0.0,
            'supplier_cost' => round($supplierCost, 2),
            'gross_profit' => round($grossProfit, 2),
            'margin_percent' => $margin,
            'refunds' => $this->refundTotal($filters),
            'open_returns' => $this->openReturns($filters),
            'store_credit_issued' => $this->storeCreditIssued($filters),
            'store_credit_redeemed' => $this->storeCreditRedeemed($filters),
            'tracking_gaps' => $this->trackingGaps($filters),
            'open_exceptions' => $this->openExceptions($filters),
            'failed_submissions' => $this->failedSubmissions($filters),
            'blocked_stores' => $this->blockedStores($filters),
            'approved_sourcing' => $this->approvedSourcing($filters),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function salesSummary(array $filters): array
    {
        if (! $this->tableExists('orders')) {
            return [
                'revenue' => 0.0,
                'paid_orders' => 0,
                'supplier_cost' => 0.0,
                'gross_profit' => null,
            ];
        }

        $revenueColumn = $this->firstExistingColumn(
            'orders',
            ['total_amount', 'grand_total', 'order_total', 'total']
        );

        $supplierCostColumn = $this->firstExistingColumn(
            'orders',
            ['supplier_cost_total', 'supplier_cost', 'cost_total']
        );

        $grossProfitColumn = $this->firstExistingColumn(
            'orders',
            ['estimated_gross_profit', 'gross_profit']
        );

        [$where, $params] = $this->orderWhere($filters, 'o');

        $sql = "
            SELECT
                COUNT(*) AS paid_orders,
                SUM(" . ($revenueColumn ? "COALESCE(o.`{$revenueColumn}`, 0)" : '0') . ") AS revenue,
                SUM(" . ($supplierCostColumn ? "COALESCE(o.`{$supplierCostColumn}`, 0)" : '0') . ") AS supplier_cost,
                SUM(" . ($grossProfitColumn ? "COALESCE(o.`{$grossProfitColumn}`, 0)" : 'NULL') . ") AS gross_profit
            FROM orders o
            WHERE {$where}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'revenue' => (float) ($row['revenue'] ?? 0),
            'paid_orders' => (int) ($row['paid_orders'] ?? 0),
            'supplier_cost' => (float) ($row['supplier_cost'] ?? 0),
            'gross_profit' =>
                $grossProfitColumn
                    ? (float) ($row['gross_profit'] ?? 0)
                    : null,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function salesByStore(array $filters): array
    {
        if (! $this->tableExists('orders') || ! $this->tableExists('stores')) {
            return [];
        }

        $revenueColumn = $this->firstExistingColumn(
            'orders',
            ['total_amount', 'grand_total', 'order_total', 'total']
        );
        $supplierCostColumn = $this->firstExistingColumn(
            'orders',
            ['supplier_cost_total', 'supplier_cost', 'cost_total']
        );
        $grossProfitColumn = $this->firstExistingColumn(
            'orders',
            ['estimated_gross_profit', 'gross_profit']
        );

        [$where, $params] = $this->orderWhere($filters, 'o');

        $grossProfitExpression = $grossProfitColumn
            ? "SUM(COALESCE(o.`{$grossProfitColumn}`, 0))"
            : "SUM(" . ($revenueColumn ? "COALESCE(o.`{$revenueColumn}`, 0)" : '0') . ")
               - SUM(" . ($supplierCostColumn ? "COALESCE(o.`{$supplierCostColumn}`, 0)" : '0') . ")";

        $sql = "
            SELECT
                s.id AS store_id,
                s.name AS store_name,
                COUNT(o.id) AS paid_orders,
                SUM(" . ($revenueColumn ? "COALESCE(o.`{$revenueColumn}`, 0)" : '0') . ") AS revenue,
                SUM(" . ($supplierCostColumn ? "COALESCE(o.`{$supplierCostColumn}`, 0)" : '0') . ") AS supplier_cost,
                {$grossProfitExpression} AS gross_profit
            FROM stores s
            LEFT JOIN orders o
                ON o.store_id = s.id
                AND {$where}
            GROUP BY s.id, s.name
            ORDER BY revenue DESC, paid_orders DESC, s.name ASC
            LIMIT 25
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $revenue = (float) ($row['revenue'] ?? 0);
            $profit = (float) ($row['gross_profit'] ?? 0);
            $row['average_order_value'] =
                (int) $row['paid_orders'] > 0
                    ? round($revenue / (int) $row['paid_orders'], 2)
                    : 0.0;
            $row['margin_percent'] =
                $revenue > 0
                    ? round(($profit / $revenue) * 100, 3)
                    : 0.0;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function supplierPerformance(array $filters): array
    {
        if (! $this->tableExists('purchase_orders') || ! $this->tableExists('suppliers')) {
            return [];
        }

        [$where, $params] = $this->purchaseOrderWhere($filters, 'po');

        $revenueColumn = $this->firstExistingColumn(
            'purchase_orders',
            ['revenue_total', 'customer_revenue_total', 'order_revenue_total']
        );
        $costColumn = $this->firstExistingColumn(
            'purchase_orders',
            ['supplier_cost_total', 'cost_total', 'total_cost']
        );
        $profitColumn = $this->firstExistingColumn(
            'purchase_orders',
            ['gross_profit', 'estimated_gross_profit']
        );

        $revenueExpr = $revenueColumn
            ? "SUM(COALESCE(po.`{$revenueColumn}`, 0))"
            : '0';
        $costExpr = $costColumn
            ? "SUM(COALESCE(po.`{$costColumn}`, 0))"
            : '0';
        $profitExpr = $profitColumn
            ? "SUM(COALESCE(po.`{$profitColumn}`, 0))"
            : "({$revenueExpr} - {$costExpr})";

        $scoreColumn = $this->columnExists('suppliers', 'performance_score')
            ? 'sup.performance_score'
            : 'NULL';

        $statusColumn = $this->columnExists('suppliers', 'performance_status')
            ? 'sup.performance_status'
            : 'NULL';

        $sql = "
            SELECT
                sup.id AS supplier_id,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                {$scoreColumn} AS performance_score,
                {$statusColumn} AS performance_status,
                COUNT(po.id) AS purchase_orders,
                {$revenueExpr} AS revenue,
                {$costExpr} AS supplier_cost,
                {$profitExpr} AS gross_profit,
                SUM(po.status = 'delivered') AS delivered_purchase_orders,
                SUM(po.status IN ('failed','cancelled')) AS problem_purchase_orders
            FROM suppliers sup
            LEFT JOIN purchase_orders po
                ON po.supplier_id = sup.id
                AND {$where}
            WHERE 1 = 1
        ";

        if ((int) $filters['supplier_id'] > 0) {
            $sql .= ' AND sup.id = :outer_supplier_id';
            $params['outer_supplier_id'] = (int) $filters['supplier_id'];
        }

        if ((int) $filters['store_id'] > 0) {
            $sql .= ' AND sup.store_id = :outer_store_id';
            $params['outer_store_id'] = (int) $filters['store_id'];
        }

        $sql .= "
            GROUP BY sup.id, sup.name, sup.code
            ORDER BY gross_profit DESC, purchase_orders DESC, sup.name ASC
            LIMIT 25
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        $rows = [];

        foreach ($stmt->fetchAll() as $row) {
            $revenue = (float) ($row['revenue'] ?? 0);
            $profit = (float) ($row['gross_profit'] ?? 0);
            $row['margin_percent'] =
                $revenue > 0
                    ? round(($profit / $revenue) * 100, 3)
                    : 0.0;
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function operations(array $filters): array
    {
        return [
            [
                'metric' => 'Open Fulfillment Exceptions',
                'value' => $this->openExceptions($filters),
                'detail' => 'Dropshipping exceptions with open status',
                'url' => '/admin/dropshipping',
            ],
            [
                'metric' => 'Failed Supplier Submissions',
                'value' => $this->failedSubmissions($filters),
                'detail' => 'Supplier order submissions that failed',
                'url' => '/admin/supplier-submissions',
            ],
            [
                'metric' => 'Tracking Gaps',
                'value' => $this->trackingGaps($filters),
                'detail' => 'Shipped or delivered purchase orders missing tracking',
                'url' => '/admin/tracking-reconciliation',
            ],
            [
                'metric' => 'Open Returns',
                'value' => $this->openReturns($filters),
                'detail' => 'Returns not completed or cancelled',
                'url' => '/admin/returns',
            ],
            [
                'metric' => 'Blocked Stores',
                'value' => $this->blockedStores($filters),
                'detail' => 'Stores with launch health below threshold',
                'url' => '/admin/multi-store-automation',
            ],
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function returns(array $filters): array
    {
        if (! $this->tableExists('returns')) {
            return [];
        }

        $valueColumn = $this->firstExistingColumn(
            'returns',
            [
                'approved_refund_amount',
                'approved_amount',
                'refund_amount',
                'total_refund_amount',
                'approved_merchandise_value',
            ]
        );

        [$where, $params] = $this->returnWhere($filters, 'r');

        $sql = "
            SELECT
                r.status,
                COUNT(*) AS return_count,
                SUM(" . ($valueColumn ? "COALESCE(r.`{$valueColumn}`, 0)" : '0') . ") AS approved_value
            FROM returns r
            WHERE {$where}
            GROUP BY r.status
            ORDER BY return_count DESC, r.status ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function storeReadiness(array $filters): array
    {
        if (! $this->tableExists('stores')) {
            return [];
        }

        $score = $this->columnExists('stores', 'automation_health_score')
            ? 's.automation_health_score'
            : 'NULL';
        $launch = $this->columnExists('stores', 'automation_launch_status')
            ? 's.automation_launch_status'
            : "'planning'";
        $audited = $this->columnExists('stores', 'last_automation_audit_at')
            ? 's.last_automation_audit_at'
            : 'NULL';

        $sql = "
            SELECT
                s.id AS store_id,
                s.name AS store_name,
                s.status AS store_status,
                {$launch} AS automation_launch_status,
                {$score} AS automation_health_score,
                {$audited} AS last_automation_audit_at
            FROM stores s
            WHERE 1 = 1
        ";

        $params = [];

        if ((int) $filters['store_id'] > 0) {
            $sql .= ' AND s.id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $sql .= "
            ORDER BY
                automation_health_score IS NULL ASC,
                automation_health_score ASC,
                s.name ASC
            LIMIT 25
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function sourcing(array $filters): array
    {
        if (! $this->tableExists('supplier_products')) {
            return [];
        }

        $statusColumn = $this->firstExistingColumn(
            'supplier_products',
            ['sourcing_status', 'status']
        );
        $scoreColumn = $this->firstExistingColumn(
            'supplier_products',
            ['sourcing_score', 'score']
        );

        if (! $statusColumn) {
            return [];
        }

        $sql = "
            SELECT
                sp.`{$statusColumn}` AS status,
                COUNT(*) AS product_count,
                AVG(" . ($scoreColumn ? "COALESCE(sp.`{$scoreColumn}`, 0)" : '0') . ") AS average_score
            FROM supplier_products sp
            WHERE 1 = 1
        ";

        $params = [];

        if ((int) $filters['store_id'] > 0 && $this->columnExists('supplier_products', 'store_id')) {
            $sql .= ' AND sp.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) $filters['supplier_id'] > 0 && $this->columnExists('supplier_products', 'supplier_id')) {
            $sql .= ' AND sp.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $sql .= "
            GROUP BY sp.`{$statusColumn}`
            ORDER BY product_count DESC, status ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(array $filters): array
    {
        if (! $this->tableExists('orders')) {
            return [];
        }

        $totalColumn = $this->firstExistingColumn(
            'orders',
            ['total_amount', 'grand_total', 'order_total', 'total']
        );

        [$where, $params] = $this->orderWhere($filters, 'o', false);

        $storeJoin = $this->tableExists('stores')
            ? 'LEFT JOIN stores s ON s.id = o.store_id'
            : '';

        $storeName = $this->tableExists('stores')
            ? 's.name'
            : "''";

        $sql = "
            SELECT
                o.id,
                o.order_number,
                {$storeName} AS store_name,
                o.payment_status,
                " . ($this->columnExists('orders', 'dropship_status') ? 'o.dropship_status' : "NULL") . " AS dropship_status,
                " . ($totalColumn ? "o.`{$totalColumn}`" : '0') . " AS order_total,
                o.created_at
            FROM orders o
            {$storeJoin}
            WHERE {$where}
            ORDER BY o.created_at DESC, o.id DESC
            LIMIT 15
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    private function refundTotal(array $filters): float
    {
        if ($this->tableExists('payment_transactions')) {
            $amountColumn = $this->firstExistingColumn(
                'payment_transactions',
                ['amount', 'transaction_amount', 'refund_amount']
            );

            if ($amountColumn) {
                $dateColumn = $this->firstExistingColumn(
                    'payment_transactions',
                    ['created_at', 'processed_at']
                );

                $sql = "
                    SELECT SUM(COALESCE(`{$amountColumn}`, 0))
                    FROM payment_transactions
                    WHERE type IN ('refund', 'partial_refund')
                ";
                $params = [];

                if ($dateColumn) {
                    $this->appendDateRange(
                        $sql,
                        $params,
                        $dateColumn,
                        $filters
                    );
                }

                $stmt = $this->db->prepare($sql);
                $stmt->execute($params);

                return round(abs((float) $stmt->fetchColumn()), 2);
            }
        }

        if ($this->tableExists('orders')) {
            $refundColumn = $this->firstExistingColumn(
                'orders',
                ['refunded_amount', 'refund_amount', 'total_refunded']
            );

            if ($refundColumn) {
                [$where, $params] = $this->orderWhere($filters, 'o', false);

                $stmt = $this->db->prepare("
                    SELECT SUM(COALESCE(o.`{$refundColumn}`, 0))
                    FROM orders o
                    WHERE {$where}
                ");
                $stmt->execute($params);

                return round((float) $stmt->fetchColumn(), 2);
            }
        }

        return 0.0;
    }

    private function openReturns(array $filters): int
    {
        if (! $this->tableExists('returns')) {
            return 0;
        }

        [$where, $params] = $this->returnWhere($filters, 'r');
        $where .= " AND r.status NOT IN ('completed', 'cancelled')";

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM returns r
            WHERE {$where}
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function storeCreditIssued(array $filters): float
    {
        return $this->storeCreditAmount(
            $filters,
            ['issue', 'issued', 'return_credit', 'manual_credit']
        );
    }

    private function storeCreditRedeemed(array $filters): float
    {
        return $this->storeCreditAmount(
            $filters,
            ['checkout_redemption', 'redeemed', 'debit']
        );
    }

    private function storeCreditAmount(
        array $filters,
        array $types
    ): float {
        if (! $this->tableExists('store_credit_transactions')) {
            return 0.0;
        }

        $amountColumn = $this->firstExistingColumn(
            'store_credit_transactions',
            ['amount', 'credit_amount', 'transaction_amount']
        );
        $typeColumn = $this->firstExistingColumn(
            'store_credit_transactions',
            ['type', 'transaction_type']
        );

        if (! $amountColumn || ! $typeColumn) {
            return 0.0;
        }

        $dateColumn = $this->firstExistingColumn(
            'store_credit_transactions',
            ['created_at', 'issued_at']
        );

        $placeholders = [];
        $params = [];

        foreach ($types as $index => $type) {
            $key = 'type_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $type;
        }

        $sql = "
            SELECT SUM(COALESCE(`{$amountColumn}`, 0))
            FROM store_credit_transactions
            WHERE `{$typeColumn}` IN (" . implode(',', $placeholders) . ")
        ";

        if ((int) $filters['store_id'] > 0 && $this->columnExists('store_credit_transactions', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ($dateColumn) {
            $this->appendDateRange(
                $sql,
                $params,
                $dateColumn,
                $filters
            );
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return round(abs((float) $stmt->fetchColumn()), 2);
    }

    private function trackingGaps(array $filters): int
    {
        if (! $this->tableExists('purchase_orders')) {
            return 0;
        }

        [$where, $params] = $this->purchaseOrderWhere($filters, 'po', false);
        $where .= "
            AND po.status IN ('shipped','partially_shipped','delivered')
            AND (
                po.tracking_number IS NULL
                OR po.tracking_number = ''
            )
        ";

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM purchase_orders po
            WHERE {$where}
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function openExceptions(array $filters): int
    {
        if (! $this->tableExists('dropship_exceptions')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM dropship_exceptions de
            WHERE de.status = 'open'
        ";
        $params = [];

        if ((int) $filters['store_id'] > 0 && $this->columnExists('dropship_exceptions', 'store_id')) {
            $sql .= ' AND de.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function failedSubmissions(array $filters): int
    {
        if (! $this->tableExists('supplier_order_submissions')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM supplier_order_submissions sos
            WHERE sos.status = 'failed'
        ";
        $params = [];

        if ((int) $filters['store_id'] > 0 && $this->columnExists('supplier_order_submissions', 'store_id')) {
            $sql .= ' AND sos.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) $filters['supplier_id'] > 0 && $this->columnExists('supplier_order_submissions', 'supplier_id')) {
            $sql .= ' AND sos.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function blockedStores(array $filters): int
    {
        if (! $this->tableExists('stores') || ! $this->columnExists('stores', 'automation_health_score')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM stores s
            WHERE s.automation_health_score IS NOT NULL
            AND s.automation_health_score < 70
        ";
        $params = [];

        if ((int) $filters['store_id'] > 0) {
            $sql .= ' AND s.id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function approvedSourcing(array $filters): int
    {
        if (! $this->tableExists('supplier_products') || ! $this->columnExists('supplier_products', 'sourcing_status')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM supplier_products sp
            WHERE sp.sourcing_status = 'approved'
        ";
        $params = [];

        if ((int) $filters['store_id'] > 0 && $this->columnExists('supplier_products', 'store_id')) {
            $sql .= ' AND sp.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) $filters['supplier_id'] > 0 && $this->columnExists('supplier_products', 'supplier_id')) {
            $sql .= ' AND sp.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function stores(): array
    {
        if (! $this->tableExists('stores')) {
            return [];
        }

        return $this->db->query("
            SELECT id, name
            FROM stores
            ORDER BY name ASC
        ")->fetchAll();
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function suppliers(int $storeId = 0): array
    {
        if (! $this->tableExists('suppliers')) {
            return [];
        }

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
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $dateTo = trim((string) ($filters['date_to'] ?? date('Y-m-d')));
        $dateFrom = trim((string) ($filters['date_from'] ?? date('Y-m-01')));

        if (! $this->validDate($dateFrom)) {
            $dateFrom = date('Y-m-01');
        }

        if (! $this->validDate($dateTo)) {
            $dateTo = date('Y-m-d');
        }

        if ($dateFrom > $dateTo) {
            [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
        }

        return [
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'store_id' => max(0, (int) ($filters['store_id'] ?? 0)),
            'supplier_id' => max(0, (int) ($filters['supplier_id'] ?? 0)),
        ];
    }

    private function validDate(string $date): bool
    {
        $value = \DateTime::createFromFormat('Y-m-d', $date);

        return $value !== false && $value->format('Y-m-d') === $date;
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function orderWhere(
        array $filters,
        string $alias,
        bool $paidOnly = true
    ): array {
        $where = ['1 = 1'];
        $params = [];

        if ($paidOnly && $this->columnExists('orders', 'payment_status')) {
            $where[] = "{$alias}.payment_status = 'paid'";
        }

        if ((int) $filters['store_id'] > 0 && $this->columnExists('orders', 'store_id')) {
            $where[] = "{$alias}.store_id = :store_id";
            $params['store_id'] = (int) $filters['store_id'];
        }

        $dateColumn = $this->firstExistingColumn(
            'orders',
            ['created_at', 'paid_at', 'ordered_at']
        );

        if ($dateColumn) {
            $where[] = "{$alias}.`{$dateColumn}` >= :date_from";
            $where[] = "{$alias}.`{$dateColumn}` < DATE_ADD(:date_to, INTERVAL 1 DAY)";
            $params['date_from'] = $filters['date_from'];
            $params['date_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function purchaseOrderWhere(
        array $filters,
        string $alias,
        bool $joinOrders = true
    ): array {
        $where = ['1 = 1'];
        $params = [];

        if ((int) $filters['store_id'] > 0 && $this->columnExists('purchase_orders', 'store_id')) {
            $where[] = "{$alias}.store_id = :store_id";
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) $filters['supplier_id'] > 0 && $this->columnExists('purchase_orders', 'supplier_id')) {
            $where[] = "{$alias}.supplier_id = :supplier_id";
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $dateColumn = $this->firstExistingColumn(
            'purchase_orders',
            ['created_at', 'submitted_at', 'shipped_at']
        );

        if ($dateColumn) {
            $where[] = "{$alias}.`{$dateColumn}` >= :date_from";
            $where[] = "{$alias}.`{$dateColumn}` < DATE_ADD(:date_to, INTERVAL 1 DAY)";
            $params['date_from'] = $filters['date_from'];
            $params['date_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $params];
    }

    /**
     * @return array{0:string,1:array<string,mixed>}
     */
    private function returnWhere(
        array $filters,
        string $alias
    ): array {
        $where = ['1 = 1'];
        $params = [];

        if ((int) $filters['store_id'] > 0 && $this->columnExists('returns', 'store_id')) {
            $where[] = "{$alias}.store_id = :store_id";
            $params['store_id'] = (int) $filters['store_id'];
        }

        $dateColumn = $this->firstExistingColumn(
            'returns',
            ['created_at', 'requested_at', 'completed_at']
        );

        if ($dateColumn) {
            $where[] = "{$alias}.`{$dateColumn}` >= :date_from";
            $where[] = "{$alias}.`{$dateColumn}` < DATE_ADD(:date_to, INTERVAL 1 DAY)";
            $params['date_from'] = $filters['date_from'];
            $params['date_to'] = $filters['date_to'];
        }

        return [implode(' AND ', $where), $params];
    }

    private function appendDateRange(
        string &$sql,
        array &$params,
        string $column,
        array $filters
    ): void {
        $sql .= " AND `{$column}` >= :date_from
                  AND `{$column}` < DATE_ADD(:date_to, INTERVAL 1 DAY)";
        $params['date_from'] = $filters['date_from'];
        $params['date_to'] = $filters['date_to'];
    }

    private function firstExistingColumn(
        string $table,
        array $columns
    ): ?string {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return $column;
            }
        }

        return null;
    }

    private function tableExists(string $table): bool
    {
        static $cache = [];

        if (array_key_exists($table, $cache)) {
            return $cache[$table];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute(['table_name' => $table]);

        $cache[$table] = (int) $stmt->fetchColumn() > 0;

        return $cache[$table];
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        static $cache = [];

        $key = $table . '.' . $column;

        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        $cache[$key] = (int) $stmt->fetchColumn() > 0;

        return $cache[$key];
    }
}

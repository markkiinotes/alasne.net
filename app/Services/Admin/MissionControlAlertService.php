<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlAlertRepository;
use PDO;

class MissionControlAlertService
{
    public function __construct(
        private PDO $db,
        private MissionControlAlertRepository $alerts
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function scan(array $filters = []): array
    {
        $createdOrUpdated = 0;
        $skipped = 0;
        $metrics = $this->metrics($filters);

        foreach ($this->alerts->enabledRules($filters) as $rule) {
            $metricKey = (string) $rule['metric_key'];
            $value = (float) ($metrics[$metricKey] ?? 0.0);

            if ($this->triggered($rule, $value)) {
                $this->alerts->upsertAlert($rule, $value);
                $createdOrUpdated++;
            } else {
                $skipped++;
            }
        }

        return [
            'created_or_updated' => $createdOrUpdated,
            'skipped' => $skipped,
            'metrics' => $metrics,
        ];
    }

    /**
     * @return array<string, float>
     */
    public function metrics(array $filters = []): array
    {
        return [
            'tracking_gaps' => (float) $this->trackingGaps($filters),
            'open_exceptions' => (float) $this->openExceptions($filters),
            'failed_submissions' => (float) $this->failedSubmissions($filters),
            'open_returns' => (float) $this->openReturns($filters),
            'blocked_stores' => (float) $this->blockedStores($filters),
            'estimated_margin_percent' => (float) $this->estimatedMargin($filters),
            'paid_orders' => (float) $this->paidOrders($filters),
            'sales_revenue' => (float) $this->salesRevenue($filters),
            'gross_profit' => (float) $this->grossProfit($filters),
            'store_credit_redeemed' => (float) $this->storeCreditRedeemed($filters),
        ];
    }

    private function triggered(array $rule, float $value): bool
    {
        $threshold = (float) $rule['threshold_value'];

        return match ((string) $rule['operator']) {
            'greater_than' => $value > $threshold,
            'greater_than_or_equal' => $value >= $threshold,
            'less_than' => $value < $threshold,
            'less_than_or_equal' => $value <= $threshold,
            'equal' => abs($value - $threshold) < 0.0001,
            default => false,
        };
    }

    private function trackingGaps(array $filters): int
    {
        if (! $this->tableExists('purchase_orders')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM purchase_orders
            WHERE status IN ('shipped','partially_shipped','delivered')
            AND (
                tracking_number IS NULL
                OR tracking_number = ''
            )
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('purchase_orders', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0 && $this->columnExists('purchase_orders', 'supplier_id')) {
            $sql .= ' AND supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $stmt = $this->db->prepare($sql);
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
            FROM dropship_exceptions
            WHERE status = 'open'
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('dropship_exceptions', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
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
            FROM supplier_order_submissions
            WHERE status = 'failed'
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('supplier_order_submissions', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0 && $this->columnExists('supplier_order_submissions', 'supplier_id')) {
            $sql .= ' AND supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function openReturns(array $filters): int
    {
        if (! $this->tableExists('returns')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM returns
            WHERE status NOT IN ('completed', 'cancelled')
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('returns', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
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
            FROM stores
            WHERE automation_health_score IS NOT NULL
            AND automation_health_score < 70
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function estimatedMargin(array $filters): float
    {
        $revenue = $this->salesRevenue($filters);

        if ($revenue <= 0) {
            return 0.0;
        }

        return round(($this->grossProfit($filters) / $revenue) * 100, 3);
    }

    private function paidOrders(array $filters): int
    {
        if (! $this->tableExists('orders')) {
            return 0;
        }

        $sql = "
            SELECT COUNT(*)
            FROM orders
            WHERE payment_status = 'paid'
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('orders', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function salesRevenue(array $filters): float
    {
        if (! $this->tableExists('orders')) {
            return 0.0;
        }

        $column = $this->firstExistingColumn(
            'orders',
            ['total_amount', 'grand_total', 'order_total', 'total']
        );

        if (! $column) {
            return 0.0;
        }

        $sql = "
            SELECT SUM(COALESCE(`{$column}`, 0))
            FROM orders
            WHERE payment_status = 'paid'
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('orders', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return round((float) $stmt->fetchColumn(), 2);
    }

    private function grossProfit(array $filters): float
    {
        if (! $this->tableExists('orders')) {
            return 0.0;
        }

        $profit = $this->firstExistingColumn(
            'orders',
            ['estimated_gross_profit', 'gross_profit']
        );

        if ($profit) {
            $sql = "
                SELECT SUM(COALESCE(`{$profit}`, 0))
                FROM orders
                WHERE payment_status = 'paid'
            ";
            $params = [];

            if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('orders', 'store_id')) {
                $sql .= ' AND store_id = :store_id';
                $params['store_id'] = (int) $filters['store_id'];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return round((float) $stmt->fetchColumn(), 2);
        }

        $revenue = $this->salesRevenue($filters);
        $costColumn = $this->firstExistingColumn(
            'orders',
            ['supplier_cost_total', 'supplier_cost', 'cost_total']
        );

        if (! $costColumn) {
            return $revenue;
        }

        $sql = "
            SELECT SUM(COALESCE(`{$costColumn}`, 0))
            FROM orders
            WHERE payment_status = 'paid'
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('orders', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return round($revenue - (float) $stmt->fetchColumn(), 2);
    }

    private function storeCreditRedeemed(array $filters): float
    {
        if (! $this->tableExists('store_credit_transactions')) {
            return 0.0;
        }

        $amount = $this->firstExistingColumn(
            'store_credit_transactions',
            ['amount', 'transaction_amount', 'credit_amount']
        );
        $type = $this->firstExistingColumn(
            'store_credit_transactions',
            ['type', 'transaction_type']
        );

        if (! $amount || ! $type) {
            return 0.0;
        }

        $sql = "
            SELECT SUM(COALESCE(`{$amount}`, 0))
            FROM store_credit_transactions
            WHERE `{$type}` IN ('checkout_redemption', 'redeemed', 'debit')
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists('store_credit_transactions', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return round(abs((float) $stmt->fetchColumn()), 2);
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

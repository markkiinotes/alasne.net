<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlBriefingRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function stores(): array
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

    public function suppliers(int $storeId = 0): array
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

        if ($storeId > 0 && $this->columnExists('suppliers', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function recentBriefings(array $filters = [], int $limit = 25): array
    {
        if (! $this->tableExists('mission_control_briefings')) {
            return [];
        }

        $sql = "
            SELECT
                b.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_briefings b
            LEFT JOIN stores s
                ON s.id = b.store_id
            LEFT JOIN suppliers sup
                ON sup.id = b.supplier_id
            WHERE 1 = 1
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND b.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND b.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $sql .= '
            ORDER BY b.created_at DESC, b.id DESC
            LIMIT ' . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                b.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_briefings b
            LEFT JOIN stores s
                ON s.id = b.store_id
            LEFT JOIN suppliers sup
                ON sup.id = b.supplier_id
            WHERE b.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function items(int $briefingId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_briefing_items
            WHERE briefing_id = :briefing_id
            ORDER BY sort_order ASC, id ASC
        ");

        $stmt->execute(['briefing_id' => $briefingId]);

        return $stmt->fetchAll();
    }

    public function save(array $payload, ?int $userId = null): int
    {
        $this->db->beginTransaction();

        try {
            $briefing = $payload['briefing'];
            $items = $payload['items'];

            $stmt = $this->db->prepare("
                INSERT INTO mission_control_briefings (
                    briefing_number,
                    period_start,
                    period_end,
                    store_id,
                    supplier_id,
                    status,
                    subject,
                    executive_summary,
                    open_alert_count,
                    critical_alert_count,
                    warning_alert_count,
                    acknowledged_alert_count,
                    resolved_alert_count,
                    tracking_gaps,
                    open_exceptions,
                    failed_submissions,
                    open_returns,
                    blocked_stores,
                    sales_revenue,
                    gross_profit,
                    margin_percent,
                    created_by,
                    created_at,
                    updated_at
                ) VALUES (
                    :briefing_number,
                    :period_start,
                    :period_end,
                    :store_id,
                    :supplier_id,
                    'saved',
                    :subject,
                    :executive_summary,
                    :open_alert_count,
                    :critical_alert_count,
                    :warning_alert_count,
                    :acknowledged_alert_count,
                    :resolved_alert_count,
                    :tracking_gaps,
                    :open_exceptions,
                    :failed_submissions,
                    :open_returns,
                    :blocked_stores,
                    :sales_revenue,
                    :gross_profit,
                    :margin_percent,
                    :created_by,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                'briefing_number' => $this->nextBriefingNumber(),
                'period_start' => $briefing['period_start'],
                'period_end' => $briefing['period_end'],
                'store_id' => ((int) $briefing['store_id'] > 0)
                    ? (int) $briefing['store_id']
                    : null,
                'supplier_id' => ((int) $briefing['supplier_id'] > 0)
                    ? (int) $briefing['supplier_id']
                    : null,
                'subject' => mb_substr((string) $briefing['subject'], 0, 255),
                'executive_summary' => $briefing['executive_summary'],
                'open_alert_count' => (int) $briefing['open_alert_count'],
                'critical_alert_count' => (int) $briefing['critical_alert_count'],
                'warning_alert_count' => (int) $briefing['warning_alert_count'],
                'acknowledged_alert_count' => (int) $briefing['acknowledged_alert_count'],
                'resolved_alert_count' => (int) $briefing['resolved_alert_count'],
                'tracking_gaps' => (int) $briefing['tracking_gaps'],
                'open_exceptions' => (int) $briefing['open_exceptions'],
                'failed_submissions' => (int) $briefing['failed_submissions'],
                'open_returns' => (int) $briefing['open_returns'],
                'blocked_stores' => (int) $briefing['blocked_stores'],
                'sales_revenue' => number_format((float) $briefing['sales_revenue'], 2, '.', ''),
                'gross_profit' => number_format((float) $briefing['gross_profit'], 2, '.', ''),
                'margin_percent' => number_format((float) $briefing['margin_percent'], 3, '.', ''),
                'created_by' => $userId,
            ]);

            $briefingId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare("
                INSERT INTO mission_control_briefing_items (
                    briefing_id,
                    alert_id,
                    item_type,
                    severity,
                    title,
                    message,
                    action_url,
                    sort_order,
                    created_at
                ) VALUES (
                    :briefing_id,
                    :alert_id,
                    :item_type,
                    :severity,
                    :title,
                    :message,
                    :action_url,
                    :sort_order,
                    NOW()
                )
            ");

            $sort = 1;

            foreach ($items as $item) {
                $itemStmt->execute([
                    'briefing_id' => $briefingId,
                    'alert_id' => ! empty($item['alert_id'])
                        ? (int) $item['alert_id']
                        : null,
                    'item_type' => mb_substr((string) ($item['item_type'] ?? 'alert'), 0, 60),
                    'severity' => mb_substr((string) ($item['severity'] ?? 'info'), 0, 30),
                    'title' => mb_substr((string) $item['title'], 0, 255),
                    'message' => mb_substr((string) ($item['message'] ?? ''), 0, 1000),
                    'action_url' => $this->nullable($item['action_url'] ?? null, 500),
                    'sort_order' => $sort++,
                ]);
            }

            $this->db->commit();

            return $briefingId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();

            throw $exception;
        }
    }

    public function delete(int $id): void
    {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                DELETE FROM mission_control_briefing_items
                WHERE briefing_id = :id
            ");
            $stmt->execute(['id' => $id]);

            $stmt = $this->db->prepare("
                DELETE FROM mission_control_briefings
                WHERE id = :id
            ");
            $stmt->execute(['id' => $id]);

            $this->db->commit();
        } catch (\Throwable $exception) {
            $this->db->rollBack();

            throw $exception;
        }
    }

    public function alertItems(array $filters, int $limit = 15): array
    {
        if (! $this->tableExists('mission_control_alerts')) {
            return [];
        }

        $sql = "
            SELECT
                a.id AS alert_id,
                'alert' AS item_type,
                a.severity,
                a.title,
                a.message,
                a.action_url,
                a.metric_key,
                a.metric_value,
                a.threshold_value,
                a.status,
                a.last_seen_at,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_alerts a
            LEFT JOIN stores s
                ON s.id = a.store_id
            LEFT JOIN suppliers sup
                ON sup.id = a.supplier_id
            WHERE a.status IN ('open', 'acknowledged')
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND a.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND a.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $sql .= "
            ORDER BY
                FIELD(a.severity, 'critical', 'warning', 'info'),
                FIELD(a.status, 'open', 'acknowledged', 'resolved'),
                a.last_seen_at DESC
            LIMIT " . max(1, min(100, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function alertSummary(array $filters): array
    {
        if (! $this->tableExists('mission_control_alerts')) {
            return [
                'open_alert_count' => 0,
                'critical_alert_count' => 0,
                'warning_alert_count' => 0,
                'acknowledged_alert_count' => 0,
                'resolved_alert_count' => 0,
            ];
        }

        $sql = "
            SELECT
                SUM(status = 'open') AS open_alert_count,
                SUM(status IN ('open','acknowledged') AND severity = 'critical') AS critical_alert_count,
                SUM(status IN ('open','acknowledged') AND severity = 'warning') AS warning_alert_count,
                SUM(status = 'acknowledged') AS acknowledged_alert_count,
                SUM(status = 'resolved') AS resolved_alert_count
            FROM mission_control_alerts a
            WHERE 1 = 1
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND a.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND a.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'open_alert_count' => (int) ($row['open_alert_count'] ?? 0),
            'critical_alert_count' => (int) ($row['critical_alert_count'] ?? 0),
            'warning_alert_count' => (int) ($row['warning_alert_count'] ?? 0),
            'acknowledged_alert_count' => (int) ($row['acknowledged_alert_count'] ?? 0),
            'resolved_alert_count' => (int) ($row['resolved_alert_count'] ?? 0),
        ];
    }

    public function operationalMetrics(array $filters): array
    {
        return [
            'tracking_gaps' => $this->trackingGaps($filters),
            'open_exceptions' => $this->openExceptions($filters),
            'failed_submissions' => $this->failedSubmissions($filters),
            'open_returns' => $this->openReturns($filters),
            'blocked_stores' => $this->blockedStores($filters),
        ];
    }

    public function financialMetrics(array $filters): array
    {
        $revenue = $this->salesRevenue($filters);
        $grossProfit = $this->grossProfit($filters);
        $margin = $revenue > 0
            ? round(($grossProfit / $revenue) * 100, 3)
            : 0.0;

        return [
            'sales_revenue' => $revenue,
            'gross_profit' => $grossProfit,
            'margin_percent' => $margin,
        ];
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

        $this->appendScope($sql, $params, 'purchase_orders', $filters);

        return $this->count($sql, $params);
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

        $this->appendScope($sql, $params, 'dropship_exceptions', $filters, false);

        return $this->count($sql, $params);
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

        $this->appendScope($sql, $params, 'supplier_order_submissions', $filters);

        return $this->count($sql, $params);
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

        $this->appendScope($sql, $params, 'returns', $filters, false);

        return $this->count($sql, $params);
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

        return $this->count($sql, $params);
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

        $this->appendScope($sql, $params, 'orders', $filters, false);
        $this->appendDateRange($sql, $params, 'orders', $filters);

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

            $this->appendScope($sql, $params, 'orders', $filters, false);
            $this->appendDateRange($sql, $params, 'orders', $filters);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            return round((float) $stmt->fetchColumn(), 2);
        }

        $revenue = $this->salesRevenue($filters);
        $cost = $this->firstExistingColumn(
            'orders',
            ['supplier_cost_total', 'supplier_cost', 'cost_total']
        );

        if (! $cost) {
            return $revenue;
        }

        $sql = "
            SELECT SUM(COALESCE(`{$cost}`, 0))
            FROM orders
            WHERE payment_status = 'paid'
        ";
        $params = [];

        $this->appendScope($sql, $params, 'orders', $filters, false);
        $this->appendDateRange($sql, $params, 'orders', $filters);

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return round($revenue - (float) $stmt->fetchColumn(), 2);
    }

    private function appendScope(
        string &$sql,
        array &$params,
        string $table,
        array $filters,
        bool $includeSupplier = true
    ): void {
        if ((int) ($filters['store_id'] ?? 0) > 0 && $this->columnExists($table, 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if (
            $includeSupplier
            && (int) ($filters['supplier_id'] ?? 0) > 0
            && $this->columnExists($table, 'supplier_id')
        ) {
            $sql .= ' AND supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }
    }

    private function appendDateRange(
        string &$sql,
        array &$params,
        string $table,
        array $filters
    ): void {
        $column = $this->firstExistingColumn(
            $table,
            ['created_at', 'paid_at', 'ordered_at']
        );

        if (! $column) {
            return;
        }

        $sql .= "
            AND `{$column}` >= :period_start
            AND `{$column}` < DATE_ADD(:period_end, INTERVAL 1 DAY)
        ";
        $params['period_start'] = $filters['period_start'];
        $params['period_end'] = $filters['period_end'];
    }

    private function count(string $sql, array $params): int
    {
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function nextBriefingNumber(): string
    {
        return 'MCB-' . date('Ymd-His') . '-' . strtoupper(bin2hex(random_bytes(2)));
    }

    private function firstExistingColumn(string $table, array $columns): ?string
    {
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

    private function columnExists(string $table, string $column): bool
    {
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

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}

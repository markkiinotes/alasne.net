<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlAlertRepository
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
            'alerts' => $this->alerts($filters, 100),
            'rules' => $this->rules($filters),
            'events' => $this->recentEvents($filters, 30),
        ];
    }

    public function summary(array $filters): array
    {
        $where = $this->alertWhere($filters);
        $params = $where['params'];

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_alerts,
                SUM(status = 'open') AS open_alerts,
                SUM(status = 'acknowledged') AS acknowledged_alerts,
                SUM(status = 'resolved') AS resolved_alerts,
                SUM(status IN ('open','acknowledged') AND severity = 'critical') AS critical_alerts,
                SUM(status IN ('open','acknowledged') AND severity = 'warning') AS warning_alerts,
                SUM(status IN ('open','acknowledged') AND severity = 'info') AS info_alerts
            FROM mission_control_alerts a
            WHERE {$where['sql']}
        ");

        $stmt->execute($params);
        $row = $stmt->fetch() ?: [];

        return [
            'total_alerts' => (int) ($row['total_alerts'] ?? 0),
            'open_alerts' => (int) ($row['open_alerts'] ?? 0),
            'acknowledged_alerts' => (int) ($row['acknowledged_alerts'] ?? 0),
            'resolved_alerts' => (int) ($row['resolved_alerts'] ?? 0),
            'critical_alerts' => (int) ($row['critical_alerts'] ?? 0),
            'warning_alerts' => (int) ($row['warning_alerts'] ?? 0),
            'info_alerts' => (int) ($row['info_alerts'] ?? 0),
        ];
    }

    public function rules(array $filters = []): array
    {
        $sql = "
            SELECT
                r.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_alert_rules r
            LEFT JOIN stores s
                ON s.id = r.store_id
            LEFT JOIN suppliers sup
                ON sup.id = r.supplier_id
            WHERE 1 = 1
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND (r.store_id = :store_id OR r.store_id IS NULL)';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND (r.supplier_id = :supplier_id OR r.supplier_id IS NULL)';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $sql .= '
            ORDER BY
                r.is_enabled DESC,
                FIELD(r.severity, "critical", "warning", "info"),
                r.name ASC
        ';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function rule(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_alert_rules
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $rule = $stmt->fetch();

        return $rule ?: null;
    }

    public function saveRule(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);

        $ruleKey = trim((string) ($data['rule_key'] ?? ''));
        if ($ruleKey === '') {
            $ruleKey = 'custom_' . bin2hex(random_bytes(6));
        }

        $ruleKey = strtolower(
            preg_replace('/[^a-z0-9_]+/', '_', $ruleKey)
            ?? $ruleKey
        );
        $ruleKey = trim($ruleKey, '_');

        if ($ruleKey === '') {
            throw new RuntimeException('Rule key is required.');
        }

        $name = trim((string) ($data['name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Rule name is required.');
        }

        $metricKey = $this->allowed(
            (string) ($data['metric_key'] ?? ''),
            $this->metricKeys(),
            'metric'
        );

        $operator = $this->allowed(
            (string) ($data['operator'] ?? 'greater_than'),
            ['greater_than', 'greater_than_or_equal', 'less_than', 'less_than_or_equal', 'equal'],
            'operator'
        );

        $severity = $this->allowed(
            (string) ($data['severity'] ?? 'warning'),
            ['critical', 'warning', 'info'],
            'severity'
        );

        $storeId = (int) ($data['store_id'] ?? 0);
        $supplierId = (int) ($data['supplier_id'] ?? 0);

        if ($id > 0) {
            $stmt = $this->db->prepare("
                UPDATE mission_control_alert_rules
                SET
                    name = :name,
                    description = :description,
                    metric_key = :metric_key,
                    operator = :operator,
                    threshold_value = :threshold_value,
                    severity = :severity,
                    store_id = :store_id,
                    supplier_id = :supplier_id,
                    is_enabled = :is_enabled,
                    cooldown_minutes = :cooldown_minutes,
                    action_url = :action_url,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $id,
                'name' => mb_substr($name, 0, 191),
                'description' => $this->nullable($data['description'] ?? null, 1000),
                'metric_key' => $metricKey,
                'operator' => $operator,
                'threshold_value' => number_format((float) ($data['threshold_value'] ?? 0), 4, '.', ''),
                'severity' => $severity,
                'store_id' => $storeId > 0 ? $storeId : null,
                'supplier_id' => $supplierId > 0 ? $supplierId : null,
                'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
                'cooldown_minutes' => max(0, (int) ($data['cooldown_minutes'] ?? 60)),
                'action_url' => $this->nullable($data['action_url'] ?? null, 500),
            ]);

            return $id;
        }

        $stmt = $this->db->prepare("
            INSERT INTO mission_control_alert_rules (
                rule_key,
                name,
                description,
                metric_key,
                operator,
                threshold_value,
                severity,
                store_id,
                supplier_id,
                is_enabled,
                cooldown_minutes,
                action_url,
                created_at,
                updated_at
            ) VALUES (
                :rule_key,
                :name,
                :description,
                :metric_key,
                :operator,
                :threshold_value,
                :severity,
                :store_id,
                :supplier_id,
                :is_enabled,
                :cooldown_minutes,
                :action_url,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'rule_key' => $ruleKey,
            'name' => mb_substr($name, 0, 191),
            'description' => $this->nullable($data['description'] ?? null, 1000),
            'metric_key' => $metricKey,
            'operator' => $operator,
            'threshold_value' => number_format((float) ($data['threshold_value'] ?? 0), 4, '.', ''),
            'severity' => $severity,
            'store_id' => $storeId > 0 ? $storeId : null,
            'supplier_id' => $supplierId > 0 ? $supplierId : null,
            'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
            'cooldown_minutes' => max(0, (int) ($data['cooldown_minutes'] ?? 60)),
            'action_url' => $this->nullable($data['action_url'] ?? null, 500),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function deleteRule(int $id): void
    {
        $stmt = $this->db->prepare("
            DELETE FROM mission_control_alert_rules
            WHERE id = :id
        ");

        $stmt->execute(['id' => $id]);
    }

    public function enabledRules(array $filters = []): array
    {
        $sql = "
            SELECT *
            FROM mission_control_alert_rules
            WHERE is_enabled = 1
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND (store_id = :store_id OR store_id IS NULL)';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND (supplier_id = :supplier_id OR supplier_id IS NULL)';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $sql .= ' ORDER BY id ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function alerts(array $filters = [], int $limit = 100): array
    {
        $where = $this->alertWhere($filters);

        $sql = "
            SELECT
                a.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_alerts a
            LEFT JOIN stores s
                ON s.id = a.store_id
            LEFT JOIN suppliers sup
                ON sup.id = a.supplier_id
            WHERE {$where['sql']}
            ORDER BY
                FIELD(a.status, 'open', 'acknowledged', 'resolved'),
                FIELD(a.severity, 'critical', 'warning', 'info'),
                a.last_seen_at DESC
            LIMIT " . max(1, min(500, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($where['params']);

        return $stmt->fetchAll();
    }

    public function alert(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                a.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_alerts a
            LEFT JOIN stores s
                ON s.id = a.store_id
            LEFT JOIN suppliers sup
                ON sup.id = a.supplier_id
            WHERE a.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $alert = $stmt->fetch();

        return $alert ?: null;
    }

    public function upsertAlert(array $rule, float $value): int
    {
        $storeId = (int) ($rule['store_id'] ?? 0) ?: null;
        $supplierId = (int) ($rule['supplier_id'] ?? 0) ?: null;

        $existing = $this->openAlertForRule(
            (string) $rule['rule_key'],
            $storeId,
            $supplierId
        );

        $title = (string) $rule['name'];
        $message =
            $this->messageForRule($rule, $value);

        if ($existing) {
            $stmt = $this->db->prepare("
                UPDATE mission_control_alerts
                SET
                    metric_value = :metric_value,
                    threshold_value = :threshold_value,
                    message = :message,
                    last_seen_at = NOW(),
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => (int) $existing['id'],
                'metric_value' => number_format($value, 4, '.', ''),
                'threshold_value' => number_format((float) $rule['threshold_value'], 4, '.', ''),
                'message' => mb_substr($message, 0, 1000),
            ]);

            $this->event(
                (int) $existing['id'],
                'refreshed',
                $existing['status'] ?? null,
                $existing['status'] ?? null,
                'Alert refreshed during scan.',
                $value,
                null
            );

            return (int) $existing['id'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO mission_control_alerts (
                rule_id,
                rule_key,
                metric_key,
                store_id,
                supplier_id,
                severity,
                status,
                title,
                message,
                metric_value,
                threshold_value,
                action_url,
                first_seen_at,
                last_seen_at,
                created_at,
                updated_at
            ) VALUES (
                :rule_id,
                :rule_key,
                :metric_key,
                :store_id,
                :supplier_id,
                :severity,
                'open',
                :title,
                :message,
                :metric_value,
                :threshold_value,
                :action_url,
                NOW(),
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'rule_id' => (int) $rule['id'],
            'rule_key' => (string) $rule['rule_key'],
            'metric_key' => (string) $rule['metric_key'],
            'store_id' => $storeId,
            'supplier_id' => $supplierId,
            'severity' => (string) $rule['severity'],
            'title' => mb_substr($title, 0, 255),
            'message' => mb_substr($message, 0, 1000),
            'metric_value' => number_format($value, 4, '.', ''),
            'threshold_value' => number_format((float) $rule['threshold_value'], 4, '.', ''),
            'action_url' => $this->nullable($rule['action_url'] ?? null, 500),
        ]);

        $alertId = (int) $this->db->lastInsertId();

        $this->event(
            $alertId,
            'opened',
            null,
            'open',
            'Alert opened during scan.',
            $value,
            null
        );

        return $alertId;
    }

    public function resolveAlert(
        int $id,
        string $status,
        ?string $note,
        ?int $userId = null
    ): void {
        $status = $this->allowed(
            $status,
            ['open', 'acknowledged', 'resolved'],
            'alert status'
        );

        $alert = $this->alert($id);

        if (! $alert) {
            throw new RuntimeException('Alert not found.');
        }

        $resolvedAtSql = $status === 'resolved'
            ? 'COALESCE(resolved_at, NOW())'
            : 'NULL';

        $resolvedByValue = $status === 'resolved'
            ? $userId
            : null;

        $stmt = $this->db->prepare("
            UPDATE mission_control_alerts
            SET
                status = :status,
                resolved_at = {$resolvedAtSql},
                resolved_by = :resolved_by,
                resolution_note = :resolution_note,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'resolved_by' => $resolvedByValue,
            'resolution_note' => $this->nullable($note, 1000),
        ]);

        $this->event(
            $id,
            $status === 'resolved' ? 'resolved' : 'status_changed',
            (string) $alert['status'],
            $status,
            $note ?: 'Alert status updated.',
            (float) $alert['metric_value'],
            $userId
        );
    }

    public function recentEvents(array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT
                e.*,
                a.title,
                a.severity,
                a.status,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_alert_events e
            INNER JOIN mission_control_alerts a
                ON a.id = e.alert_id
            LEFT JOIN stores s
                ON s.id = a.store_id
            LEFT JOIN suppliers sup
                ON sup.id = a.supplier_id
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

        $sql .= '
            ORDER BY e.id DESC
            LIMIT ' . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function metricKeys(): array
    {
        return [
            'tracking_gaps',
            'open_exceptions',
            'failed_submissions',
            'open_returns',
            'blocked_stores',
            'estimated_margin_percent',
            'paid_orders',
            'sales_revenue',
            'gross_profit',
            'store_credit_redeemed',
        ];
    }

    private function openAlertForRule(
        string $ruleKey,
        ?int $storeId,
        ?int $supplierId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_alerts
            WHERE rule_key = :rule_key
            AND status IN ('open', 'acknowledged')
            AND store_id <=> :store_id
            AND supplier_id <=> :supplier_id
            ORDER BY id DESC
            LIMIT 1
        ");

        $stmt->execute([
            'rule_key' => $ruleKey,
            'store_id' => $storeId,
            'supplier_id' => $supplierId,
        ]);

        $alert = $stmt->fetch();

        return $alert ?: null;
    }

    private function messageForRule(array $rule, float $value): string
    {
        return sprintf(
            '%s: metric %s is %.4f and threshold is %.4f.',
            (string) $rule['name'],
            (string) $rule['metric_key'],
            $value,
            (float) $rule['threshold_value']
        );
    }

    private function event(
        int $alertId,
        string $eventType,
        ?string $oldStatus,
        ?string $newStatus,
        ?string $message,
        ?float $metricValue,
        ?int $userId
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_alert_events (
                alert_id,
                event_type,
                old_status,
                new_status,
                message,
                metric_value,
                created_by,
                created_at
            ) VALUES (
                :alert_id,
                :event_type,
                :old_status,
                :new_status,
                :message,
                :metric_value,
                :created_by,
                NOW()
            )
        ");

        $stmt->execute([
            'alert_id' => $alertId,
            'event_type' => $eventType,
            'old_status' => $oldStatus,
            'new_status' => $newStatus,
            'message' => $this->nullable($message, 1000),
            'metric_value' => $metricValue !== null
                ? number_format($metricValue, 4, '.', '')
                : null,
            'created_by' => $userId,
        ]);
    }

    private function alertWhere(array $filters): array
    {
        $where = ['1 = 1'];
        $params = [];

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $where[] = 'a.status = :status';
            $params['status'] = $status;
        }

        $severity = trim((string) ($filters['severity'] ?? ''));
        if ($severity !== '') {
            $where[] = 'a.severity = :severity';
            $params['severity'] = $severity;
        }

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $where[] = 'a.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $where[] = 'a.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        return [
            'sql' => implode(' AND ', $where),
            'params' => $params,
        ];
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    private function allowed(
        string $value,
        array $allowed,
        string $label
    ): string {
        $value = trim($value);

        if (! in_array($value, $allowed, true)) {
            throw new RuntimeException('Invalid ' . $label . '.');
        }

        return $value;
    }
}

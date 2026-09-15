<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class MissionControlNotificationEventBridgeRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboard(array $filters = []): array
    {
        return [
            'summary' => $this->summary(),
            'runs' => $this->runs($filters, 50),
            'items' => $this->items(75),
            'rules' => $this->eventRules(),
            'event_keys' => $this->eventKeys(),
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_runs,
                SUM(status = 'completed') AS completed_runs,
                SUM(status = 'duplicate') AS duplicate_runs,
                SUM(status = 'failed') AS failed_runs,
                SUM(queued_dispatches) AS queued_dispatches,
                SUM(dry_run_events) AS dry_run_events,
                MAX(created_at) AS last_run_at
            FROM mission_control_notification_event_bridge_runs
        ");

        $row = $stmt->fetch() ?: [];

        return [
            'total_runs' => (int) ($row['total_runs'] ?? 0),
            'completed_runs' => (int) ($row['completed_runs'] ?? 0),
            'duplicate_runs' => (int) ($row['duplicate_runs'] ?? 0),
            'failed_runs' => (int) ($row['failed_runs'] ?? 0),
            'queued_dispatches' => (int) ($row['queued_dispatches'] ?? 0),
            'dry_run_events' => (int) ($row['dry_run_events'] ?? 0),
            'last_run_at' => $row['last_run_at'] ?? null,
        ];
    }

    public function createRun(
        string $eventKey,
        string $eventSource,
        ?string $idempotencyKey,
        array $payload,
        ?int $userId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_event_bridge_runs (
                event_key,
                event_source,
                status,
                idempotency_key,
                payload_json,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :event_key,
                :event_source,
                'received',
                :idempotency_key,
                :payload_json,
                :created_by,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'event_key' => mb_substr($eventKey, 0, 120),
            'event_source' => mb_substr($eventSource, 0, 120),
            'idempotency_key' => $idempotencyKey,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'created_by' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function markDuplicateRun(
        string $eventKey,
        string $eventSource,
        string $idempotencyKey,
        array $payload,
        ?int $userId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_event_bridge_runs (
                event_key,
                event_source,
                status,
                idempotency_key,
                payload_json,
                message,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :event_key,
                :event_source,
                'duplicate',
                :idempotency_key,
                :payload_json,
                'Duplicate event ignored by idempotency key.',
                :created_by,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                updated_at = NOW()
        ");

        $stmt->execute([
            'event_key' => mb_substr($eventKey, 0, 120),
            'event_source' => mb_substr($eventSource, 0, 120),
            'idempotency_key' => $idempotencyKey,
            'payload_json' => json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
            'created_by' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function completeRun(int $runId, array $stats, string $status, string $message): void
    {
        $stmt = $this->db->prepare("
            UPDATE mission_control_notification_event_bridge_runs
            SET
                status = :status,
                matched_rules = :matched_rules,
                queued_dispatches = :queued_dispatches,
                dry_run_events = :dry_run_events,
                skipped_rules = :skipped_rules,
                failed_rules = :failed_rules,
                message = :message,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $runId,
            'status' => mb_substr($status, 0, 40),
            'matched_rules' => (int) ($stats['matched_rules'] ?? 0),
            'queued_dispatches' => (int) ($stats['queued_dispatches'] ?? 0),
            'dry_run_events' => (int) ($stats['dry_run_events'] ?? 0),
            'skipped_rules' => (int) ($stats['skipped_rules'] ?? 0),
            'failed_rules' => (int) ($stats['failed_rules'] ?? 0),
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    public function createItem(
        int $runId,
        ?array $rule,
        string $eventKey,
        string $status,
        ?string $recipient,
        ?string $templateKey,
        ?int $dispatchId,
        ?int $emailOutboxId,
        ?string $idempotencyKey,
        ?string $message,
        ?string $errorMessage
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_event_bridge_items (
                run_id,
                rule_id,
                rule_key,
                event_key,
                template_key,
                status,
                recipient,
                dispatch_id,
                email_outbox_id,
                idempotency_key,
                message,
                error_message,
                created_at
            ) VALUES (
                :run_id,
                :rule_id,
                :rule_key,
                :event_key,
                :template_key,
                :status,
                :recipient,
                :dispatch_id,
                :email_outbox_id,
                :idempotency_key,
                :message,
                :error_message,
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                message = VALUES(message),
                error_message = VALUES(error_message)
        ");

        $stmt->execute([
            'run_id' => $runId,
            'rule_id' => $rule['id'] ?? null,
            'rule_key' => $this->nullable($rule['rule_key'] ?? null, 140),
            'event_key' => mb_substr($eventKey, 0, 120),
            'template_key' => $this->nullable($templateKey, 120),
            'status' => mb_substr($status, 0, 40),
            'recipient' => $this->nullable($recipient, 255),
            'dispatch_id' => $dispatchId,
            'email_outbox_id' => $emailOutboxId,
            'idempotency_key' => $idempotencyKey,
            'message' => $this->nullable($message, 1000),
            'error_message' => $this->nullable($errorMessage, 1000),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function enabledRulesForEvent(string $eventKey): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_automation_rules
            WHERE event_key = :event_key
            AND is_enabled = 1
            ORDER BY id ASC
        ");

        $stmt->execute([
            'event_key' => $eventKey,
        ]);

        return $stmt->fetchAll();
    }

    public function allRulesForEvent(string $eventKey): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_automation_rules
            WHERE event_key = :event_key
            ORDER BY is_enabled DESC, id ASC
        ");

        $stmt->execute([
            'event_key' => $eventKey,
        ]);

        return $stmt->fetchAll();
    }

    public function runs(array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT *
            FROM mission_control_notification_event_bridge_runs
            WHERE 1 = 1
        ";
        $params = [];

        if (trim((string) ($filters['event_key'] ?? '')) !== '') {
            $sql .= ' AND event_key = :event_key';
            $params['event_key'] = trim((string) $filters['event_key']);
        }

        if (trim((string) ($filters['status'] ?? '')) !== '') {
            $sql .= ' AND status = :status';
            $params['status'] = trim((string) $filters['status']);
        }

        $sql .= "
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function items(int $limit = 75): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_event_bridge_items
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit))
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function eventRules(): array
    {
        if (! $this->tableExists('mission_control_notification_automation_rules')) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT
                event_key,
                COUNT(*) AS total_rules,
                SUM(is_enabled = 1) AS enabled_rules,
                SUM(dry_run_only = 1) AS dry_run_rules
            FROM mission_control_notification_automation_rules
            GROUP BY event_key
            ORDER BY event_key ASC
        ");

        return $stmt->fetchAll();
    }

    public function eventKeys(): array
    {
        if (! $this->tableExists('mission_control_notification_automation_rules')) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT DISTINCT event_key
            FROM mission_control_notification_automation_rules
            ORDER BY event_key ASC
        ");

        return array_column($stmt->fetchAll(), 'event_key');
    }

    public function exportRows(): array
    {
        return $this->runs([], 1000);
    }


    public function runIdByKey(
        string $idempotencyKey
    ): ?int {
        $stmt = $this->db->prepare("
            SELECT id
            FROM mission_control_notification_event_bridge_runs
            WHERE idempotency_key = :idempotency_key
            LIMIT 1
        ");

        $stmt->execute([
            'idempotency_key' => $idempotencyKey,
        ]);

        $value = $stmt->fetchColumn();

        return $value !== false
            ? (int) $value
            : null;
    }

    public function runExistsByKey(string $idempotencyKey): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM mission_control_notification_event_bridge_runs
            WHERE idempotency_key = :idempotency_key
        ");

        $stmt->execute([
            'idempotency_key' => $idempotencyKey,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function itemExistsByKey(string $idempotencyKey): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM mission_control_notification_event_bridge_items
            WHERE idempotency_key = :idempotency_key
        ");

        $stmt->execute([
            'idempotency_key' => $idempotencyKey,
        ]);

        return (int) $stmt->fetchColumn() > 0;
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

        $stmt->execute([
            'table_name' => $table,
        ]);

        $cache[$table] = (int) $stmt->fetchColumn() > 0;

        return $cache[$table];
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

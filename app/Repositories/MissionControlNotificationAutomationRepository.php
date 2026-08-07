<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlNotificationAutomationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboard(array $filters = []): array
    {
        return [
            'summary' => $this->summary(),
            'rules' => $this->rules($filters),
            'templates' => $this->enabledTemplates(),
            'events' => $this->recentEvents(50),
            'categories' => $this->categories(),
            'audiences' => $this->audiences(),
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_rules,
                SUM(is_enabled = 1) AS enabled_rules,
                SUM(is_enabled = 0) AS disabled_rules,
                SUM(dry_run_only = 1) AS dry_run_rules,
                SUM(run_count) AS total_runs,
                MAX(last_run_at) AS last_run_at
            FROM mission_control_notification_automation_rules
        ");

        $row = $stmt->fetch() ?: [];

        return [
            'total_rules' => (int) ($row['total_rules'] ?? 0),
            'enabled_rules' => (int) ($row['enabled_rules'] ?? 0),
            'disabled_rules' => (int) ($row['disabled_rules'] ?? 0),
            'dry_run_rules' => (int) ($row['dry_run_rules'] ?? 0),
            'total_runs' => (int) ($row['total_runs'] ?? 0),
            'last_run_at' => $row['last_run_at'] ?? null,
        ];
    }

    public function rules(array $filters = []): array
    {
        $sql = "
            SELECT *
            FROM mission_control_notification_automation_rules
            WHERE 1 = 1
        ";
        $params = [];

        if (trim((string) ($filters['category'] ?? '')) !== '') {
            $sql .= ' AND category = :category';
            $params['category'] = trim((string) $filters['category']);
        }

        if (trim((string) ($filters['audience'] ?? '')) !== '') {
            $sql .= ' AND audience = :audience';
            $params['audience'] = trim((string) $filters['audience']);
        }

        if (trim((string) ($filters['search'] ?? '')) !== '') {
            $sql .= " AND (
                rule_key LIKE :search_rule_key
                OR name LIKE :search_name
                OR event_key LIKE :search_event_key
                OR template_key LIKE :search_template_key
                OR description LIKE :search_description
            )";

            $search = '%' . trim((string) $filters['search']) . '%';

            $params['search_rule_key'] = $search;
            $params['search_name'] = $search;
            $params['search_event_key'] = $search;
            $params['search_template_key'] = $search;
            $params['search_description'] = $search;
        }

        $sql .= "
            ORDER BY
                category ASC,
                audience ASC,
                event_key ASC,
                name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_automation_rules
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function update(int $id, array $data, ?int $userId = null): void
    {
        $rule = $this->find($id);

        if (! $rule) {
            throw new RuntimeException('Notification automation rule not found.');
        }

        $stmt = $this->db->prepare("
            UPDATE mission_control_notification_automation_rules
            SET
                name = :name,
                template_id = :template_id,
                template_key = :template_key,
                category = :category,
                audience = :audience,
                recipient_source = :recipient_source,
                default_recipient = :default_recipient,
                payload_strategy = :payload_strategy,
                description = :description,
                guardrails_json = :guardrails_json,
                is_enabled = :is_enabled,
                dry_run_only = :dry_run_only,
                updated_at = NOW()
            WHERE id = :id
        ");

        $template = $this->templateById((int) ($data['template_id'] ?? 0));

        $stmt->execute([
            'id' => $id,
            'name' => mb_substr(trim((string) ($data['name'] ?? $rule['name'])), 0, 191),
            'template_id' => $template['id'] ?? null,
            'template_key' => $template['template_key'] ?? $rule['template_key'],
            'category' => mb_substr(trim((string) ($data['category'] ?? $rule['category'])), 0, 80),
            'audience' => mb_substr(trim((string) ($data['audience'] ?? $rule['audience'])), 0, 80),
            'recipient_source' => mb_substr(trim((string) ($data['recipient_source'] ?? $rule['recipient_source'])), 0, 80),
            'default_recipient' => $this->nullable($data['default_recipient'] ?? null, 255),
            'payload_strategy' => mb_substr(trim((string) ($data['payload_strategy'] ?? $rule['payload_strategy'])), 0, 80),
            'description' => $this->nullable($data['description'] ?? null, 1000),
            'guardrails_json' => $this->jsonOrNull($data['guardrails_json'] ?? null),
            'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
            'dry_run_only' => ! empty($data['dry_run_only']) ? 1 : 0,
        ]);

        $updated = $this->find($id);

        $this->recordEvent(
            $id,
            (string) ($updated['rule_key'] ?? $rule['rule_key']),
            (string) ($updated['event_key'] ?? $rule['event_key']),
            'updated',
            'logged',
            null,
            (string) ($updated['template_key'] ?? $rule['template_key']),
            null,
            null,
            null,
            'Automation rule updated.',
            null,
            $userId
        );
    }

    public function markRun(int $ruleId): void
    {
        $stmt = $this->db->prepare("
            UPDATE mission_control_notification_automation_rules
            SET
                run_count = run_count + 1,
                last_run_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $ruleId,
        ]);
    }

    public function recordEvent(
        ?int $ruleId,
        ?string $ruleKey,
        string $eventKey,
        string $eventType,
        string $status,
        ?string $recipient,
        ?string $templateKey,
        ?int $dispatchId,
        ?int $emailOutboxId,
        ?string $payloadJson,
        ?string $message,
        ?string $errorMessage,
        ?int $userId
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_automation_events (
                rule_id,
                rule_key,
                event_key,
                event_type,
                status,
                recipient,
                template_key,
                dispatch_id,
                email_outbox_id,
                payload_json,
                message,
                error_message,
                created_by,
                created_at
            ) VALUES (
                :rule_id,
                :rule_key,
                :event_key,
                :event_type,
                :status,
                :recipient,
                :template_key,
                :dispatch_id,
                :email_outbox_id,
                :payload_json,
                :message,
                :error_message,
                :created_by,
                NOW()
            )
        ");

        $stmt->execute([
            'rule_id' => $ruleId,
            'rule_key' => $this->nullable($ruleKey, 140),
            'event_key' => mb_substr($eventKey, 0, 120),
            'event_type' => mb_substr($eventType, 0, 60),
            'status' => mb_substr($status, 0, 40),
            'recipient' => $this->nullable($recipient, 255),
            'template_key' => $this->nullable($templateKey, 120),
            'dispatch_id' => $dispatchId,
            'email_outbox_id' => $emailOutboxId,
            'payload_json' => $payloadJson,
            'message' => $this->nullable($message, 1000),
            'error_message' => $this->nullable($errorMessage, 1000),
            'created_by' => $userId,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function recentEvents(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_automation_events
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit))
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function enabledTemplates(): array
    {
        if (! $this->tableExists('mission_control_notification_templates')) {
            return [];
        }

        $stmt = $this->db->query("
            SELECT
                id,
                template_key,
                name,
                category,
                audience,
                sample_payload_json
            FROM mission_control_notification_templates
            WHERE is_enabled = 1
            ORDER BY category ASC, audience ASC, name ASC
        ");

        return $stmt->fetchAll();
    }

    public function templateById(int $id): ?array
    {
        if ($id <= 0 || ! $this->tableExists('mission_control_notification_templates')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_templates
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function exportRows(): array
    {
        return $this->rules([]);
    }

    public function categories(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT category
            FROM mission_control_notification_automation_rules
            ORDER BY category ASC
        ");

        return array_column($stmt->fetchAll(), 'category');
    }

    public function audiences(): array
    {
        $stmt = $this->db->query("
            SELECT DISTINCT audience
            FROM mission_control_notification_automation_rules
            ORDER BY audience ASC
        ");

        return array_column($stmt->fetchAll(), 'audience');
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

    private function jsonOrNull(mixed $value): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException(
                'Invalid JSON: ' . json_last_error_msg()
            );
        }

        return $value;
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

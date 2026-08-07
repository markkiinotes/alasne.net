<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlNotificationDispatchRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboard(): array
    {
        return [
            'summary' => $this->summary(),
            'templates' => $this->enabledTemplates(),
            'dispatches' => $this->recentDispatches(50),
            'events' => $this->recentEvents(25),
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_dispatches,
                SUM(status = 'queued') AS queued_dispatches,
                SUM(status = 'failed') AS failed_dispatches,
                SUM(status = 'sent') AS sent_dispatches,
                MAX(created_at) AS last_dispatch_at
            FROM mission_control_notification_dispatches
        ");

        $row = $stmt->fetch() ?: [];

        return [
            'total_dispatches' => (int) ($row['total_dispatches'] ?? 0),
            'queued_dispatches' => (int) ($row['queued_dispatches'] ?? 0),
            'failed_dispatches' => (int) ($row['failed_dispatches'] ?? 0),
            'sent_dispatches' => (int) ($row['sent_dispatches'] ?? 0),
            'last_dispatch_at' => $row['last_dispatch_at'] ?? null,
            'pending_outbox' => $this->pendingOutboxCount(),
        ];
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
                subject_template,
                sample_payload_json
            FROM mission_control_notification_templates
            WHERE is_enabled = 1
            ORDER BY category ASC, audience ASC, name ASC
        ");

        return $stmt->fetchAll();
    }

    public function recentDispatches(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_dispatches
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit))
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function recentEvents(int $limit = 25): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_notification_dispatch_events
            ORDER BY created_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit))
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function createOutboxMessage(array $message): int
    {
        if (! $this->tableExists('email_outbox')) {
            throw new RuntimeException('The email_outbox table does not exist.');
        }

        $recipientColumn = $this->firstExistingColumn(
            'email_outbox',
            ['to_email', 'recipient_email', 'email', 'recipient']
        );

        $subjectColumn = $this->firstExistingColumn(
            'email_outbox',
            ['subject', 'email_subject']
        );

        $bodyColumn = $this->firstExistingColumn(
            'email_outbox',
            ['body', 'body_text', 'text_body', 'message']
        );

        $bodyHtmlColumn = $this->firstExistingColumn(
            'email_outbox',
            ['body_html', 'html_body', 'html', 'message_html']
        );

        if (! $recipientColumn || ! $subjectColumn) {
            throw new RuntimeException(
                'email_outbox must have recipient and subject columns.'
            );
        }

        if (! $bodyColumn && ! $bodyHtmlColumn) {
            throw new RuntimeException(
                'email_outbox must have a text or HTML body column.'
            );
        }

        $columns = [];
        $values = [];
        $params = [];

        $this->addInsertValue(
            $columns,
            $values,
            $params,
            $recipientColumn,
            'recipient',
            (string) $message['recipient']
        );

        $this->addInsertValue(
            $columns,
            $values,
            $params,
            $subjectColumn,
            'subject',
            (string) $message['subject']
        );

        if ($bodyColumn) {
            $this->addInsertValue(
                $columns,
                $values,
                $params,
                $bodyColumn,
                'body_text',
                (string) ($message['body_text'] ?? '')
            );
        }

        if ($bodyHtmlColumn) {
            $this->addInsertValue(
                $columns,
                $values,
                $params,
                $bodyHtmlColumn,
                'body_html',
                (string) ($message['body_html'] ?? '')
            );
        }

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            ['status', 'delivery_status', 'send_status']
        );

        if ($statusColumn) {
            $this->addInsertValue(
                $columns,
                $values,
                $params,
                $statusColumn,
                'status',
                'pending'
            );
        }

        $attemptsColumn = $this->firstExistingColumn(
            'email_outbox',
            ['attempts', 'send_attempts', 'delivery_attempts']
        );

        if ($attemptsColumn) {
            $this->addInsertValue(
                $columns,
                $values,
                $params,
                $attemptsColumn,
                'attempts',
                0
            );
        }

        foreach (['created_at', 'queued_at'] as $column) {
            if ($this->columnExists('email_outbox', $column)) {
                $columns[] = "`{$column}`";
                $values[] = 'NOW()';
                break;
            }
        }

        if ($this->columnExists('email_outbox', 'updated_at')) {
            $columns[] = '`updated_at`';
            $values[] = 'NOW()';
        }

        $stmt = $this->db->prepare("
            INSERT INTO email_outbox (
                " . implode(",\n                ", $columns) . "
            ) VALUES (
                " . implode(",\n                ", $values) . "
            )
        ");

        $stmt->execute($params);

        return (int) $this->db->lastInsertId();
    }

    public function recordDispatch(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_dispatches (
                template_id,
                template_key,
                recipient,
                subject,
                status,
                email_outbox_id,
                payload_json,
                error_message,
                queued_at,
                created_by,
                created_at,
                updated_at
            ) VALUES (
                :template_id,
                :template_key,
                :recipient,
                :subject,
                :status,
                :email_outbox_id,
                :payload_json,
                :error_message,
                :queued_at,
                :created_by,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'template_id' => $data['template_id'] ?? null,
            'template_key' => $this->nullable($data['template_key'] ?? null, 120),
            'recipient' => mb_substr((string) ($data['recipient'] ?? ''), 0, 255),
            'subject' => mb_substr((string) ($data['subject'] ?? ''), 0, 255),
            'status' => mb_substr((string) ($data['status'] ?? 'queued'), 0, 40),
            'email_outbox_id' => $data['email_outbox_id'] ?? null,
            'payload_json' => $data['payload_json'] ?? null,
            'error_message' => $this->nullable($data['error_message'] ?? null, 1000),
            'queued_at' => ($data['status'] ?? 'queued') === 'queued'
                ? date('Y-m-d H:i:s')
                : null,
            'created_by' => $data['created_by'] ?? null,
        ]);

        $id = (int) $this->db->lastInsertId();

        $this->recordEvent(
            $id,
            $data['email_outbox_id'] ?? null,
            (string) ($data['status'] ?? 'queued'),
            (string) ($data['message'] ?? 'Notification dispatch recorded.'),
            $data['created_by'] ?? null
        );

        return $id;
    }

    public function recordEvent(
        ?int $dispatchId,
        ?int $emailOutboxId,
        string $eventType,
        string $message,
        ?int $userId = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_notification_dispatch_events (
                dispatch_id,
                email_outbox_id,
                event_type,
                message,
                created_by,
                created_at
            ) VALUES (
                :dispatch_id,
                :email_outbox_id,
                :event_type,
                :message,
                :created_by,
                NOW()
            )
        ");

        $stmt->execute([
            'dispatch_id' => $dispatchId,
            'email_outbox_id' => $emailOutboxId,
            'event_type' => mb_substr($eventType, 0, 60),
            'message' => mb_substr($message, 0, 1000),
            'created_by' => $userId,
        ]);
    }

    public function exportRows(): array
    {
        return $this->recentDispatches(1000);
    }

    private function pendingOutboxCount(): int
    {
        if (! $this->tableExists('email_outbox')) {
            return 0;
        }

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            ['status', 'delivery_status', 'send_status']
        );

        if (! $statusColumn) {
            return 0;
        }

        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM email_outbox
            WHERE `{$statusColumn}` IN ('pending', 'queued', 'ready')
        ");

        return (int) $stmt->fetchColumn();
    }

    private function addInsertValue(
        array &$columns,
        array &$values,
        array &$params,
        string $column,
        string $key,
        mixed $value
    ): void {
        $placeholder = ':' . $key;

        $columns[] = "`{$column}`";
        $values[] = $placeholder;
        $params[$key] = $value;
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

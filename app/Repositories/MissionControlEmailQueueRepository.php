<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlEmailQueueRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function dashboard(): array
    {
        return [
            'summary' => $this->summary(),
            'pending' => $this->pendingMessages(25),
            'attempts' => $this->recentAttempts(50),
        ];
    }

    public function summary(): array
    {
        if (! $this->tableExists('email_outbox')) {
            return [
                'outbox_exists' => false,
                'pending' => 0,
                'sent' => 0,
                'failed' => 0,
                'attempts' => $this->attemptCount(),
                'last_attempt_at' => $this->lastAttemptAt(),
            ];
        }

        return [
            'outbox_exists' => true,
            'pending' => $this->countByStatus(['pending', 'queued', 'ready']),
            'sent' => $this->countByStatus(['sent', 'delivered', 'logged']),
            'failed' => $this->countByStatus(['failed', 'error']),
            'attempts' => $this->attemptCount(),
            'last_attempt_at' => $this->lastAttemptAt(),
        ];
    }

    public function pendingMessages(int $limit = 25): array
    {
        if (! $this->tableExists('email_outbox')) {
            return [];
        }

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            ['status', 'delivery_status', 'send_status']
        );

        $createdColumn = $this->firstExistingColumn(
            'email_outbox',
            ['created_at', 'queued_at', 'send_after', 'scheduled_at']
        );

        $where = '1 = 1';
        $params = [];

        if ($statusColumn) {
            $where .= " AND (
                `{$statusColumn}` IN ('pending','queued','ready','failed')
                OR `{$statusColumn}` IS NULL
                OR `{$statusColumn}` = ''
            )";
        } elseif ($this->columnExists('email_outbox', 'sent_at')) {
            $where .= ' AND sent_at IS NULL';
        }

        $orderColumn = $createdColumn ?: 'id';

        $select = $this->emailSelectSql();

        $stmt = $this->db->prepare("
            SELECT {$select}
            FROM email_outbox
            WHERE {$where}
            ORDER BY `{$orderColumn}` ASC, id ASC
            LIMIT " . max(1, min(100, $limit))
        );

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    /**
     * Atomically claim one outbox row so overlapping Mission Control
     * workers do not send the same pending message concurrently.
     *
     * Legacy outbox schemas without a status column remain supported,
     * but cannot provide an atomic claim.
     */
    public function claimForProcessing(
        int $id
    ): bool {
        if (! $this->tableExists('email_outbox')) {
            return false;
        }

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            [
                'status',
                'delivery_status',
                'send_status',
            ]
        );

        if (! $statusColumn) {
            return true;
        }

        $sets = [
            "`{$statusColumn}` = 'processing'",
        ];

        $updatedColumn = $this->firstExistingColumn(
            'email_outbox',
            [
                'updated_at',
                'processed_at',
            ]
        );

        if ($updatedColumn) {
            $sets[] = "`{$updatedColumn}` = NOW()";
        }

        $stmt = $this->db->prepare("
            UPDATE email_outbox
            SET " . implode(', ', $sets) . "
            WHERE id = :id
            AND (
                `{$statusColumn}` IN (
                    'pending',
                    'queued',
                    'ready',
                    'failed'
                )
                OR `{$statusColumn}` IS NULL
                OR `{$statusColumn}` = ''
            )
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        return $stmt->rowCount() === 1;
    }

    /**
     * Recover rows left in processing if a worker terminated before
     * it could mark the message sent/logged/failed.
     */
    public function releaseStaleProcessing(
        int $timeoutMinutes = 30
    ): int {
        if (! $this->tableExists('email_outbox')) {
            return 0;
        }

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            [
                'status',
                'delivery_status',
                'send_status',
            ]
        );

        $updatedColumn = $this->firstExistingColumn(
            'email_outbox',
            [
                'updated_at',
                'processed_at',
            ]
        );

        if (! $statusColumn || ! $updatedColumn) {
            return 0;
        }

        $timeoutMinutes = max(
            5,
            min(
                1440,
                $timeoutMinutes
            )
        );

        $cutoff = date(
            'Y-m-d H:i:s',
            time() - ($timeoutMinutes * 60)
        );

        $sets = [
            "`{$statusColumn}` = 'failed'",
            "`{$updatedColumn}` = NOW()",
        ];

        $errorColumn = $this->firstExistingColumn(
            'email_outbox',
            [
                'last_error',
                'error_message',
                'failure_reason',
            ]
        );

        $params = [
            'cutoff' => $cutoff,
        ];

        if ($errorColumn) {
            $sets[] =
                "`{$errorColumn}` = :lease_error";

            $params['lease_error'] =
                'Processing lease expired before completion; message is eligible for retry.';
        }

        $stmt = $this->db->prepare("
            UPDATE email_outbox
            SET " . implode(', ', $sets) . "
            WHERE `{$statusColumn}` = 'processing'
            AND `{$updatedColumn}` < :cutoff
        ");

        $stmt->execute($params);

        return $stmt->rowCount();
    }

    public function findOutboxMessage(int $id): ?array
    {
        if (! $this->tableExists('email_outbox')) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT {$this->emailSelectSql()}
            FROM email_outbox
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function markSent(
        int $id,
        string $transport,
        string $message = 'Email processed successfully.'
    ): void {
        $this->updateOutboxStatus($id, 'sent', null);

        $email = $this->findOutboxMessage($id);

        $this->recordAttempt([
            'email_outbox_id' => $id,
            'status' => 'sent',
            'transport' => $transport,
            'recipient' => $email['recipient'] ?? null,
            'subject' => $email['subject'] ?? null,
            'message' => $message,
            'error_message' => null,
        ]);
    }

    public function markLogged(
        int $id,
        string $transport,
        string $message = 'Email logged only. No external send attempted.'
    ): void {
        $this->updateOutboxStatus($id, 'logged', null);

        $email = $this->findOutboxMessage($id);

        $this->recordAttempt([
            'email_outbox_id' => $id,
            'status' => 'logged',
            'transport' => $transport,
            'recipient' => $email['recipient'] ?? null,
            'subject' => $email['subject'] ?? null,
            'message' => $message,
            'error_message' => null,
        ]);
    }

    public function markFailed(
        int $id,
        string $transport,
        string $error
    ): void {
        $this->updateOutboxStatus($id, 'failed', $error);

        $email = $this->findOutboxMessage($id);

        $this->recordAttempt([
            'email_outbox_id' => $id,
            'status' => 'failed',
            'transport' => $transport,
            'recipient' => $email['recipient'] ?? null,
            'subject' => $email['subject'] ?? null,
            'message' => 'Email processing failed.',
            'error_message' => $error,
        ]);
    }

    public function recordAttempt(array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_email_queue_attempts (
                email_outbox_id,
                status,
                transport,
                recipient,
                subject,
                message,
                error_message,
                attempted_at,
                created_at
            ) VALUES (
                :email_outbox_id,
                :status,
                :transport,
                :recipient,
                :subject,
                :message,
                :error_message,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'email_outbox_id' => ! empty($data['email_outbox_id'])
                ? (int) $data['email_outbox_id']
                : null,
            'status' => mb_substr((string) ($data['status'] ?? 'logged'), 0, 30),
            'transport' => mb_substr((string) ($data['transport'] ?? 'log'), 0, 60),
            'recipient' => $this->nullable($data['recipient'] ?? null, 255),
            'subject' => $this->nullable($data['subject'] ?? null, 255),
            'message' => $this->nullable($data['message'] ?? null, 1000),
            'error_message' => $this->nullable($data['error_message'] ?? null, 1000),
        ]);
    }

    public function recentAttempts(int $limit = 50): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_email_queue_attempts
            ORDER BY attempted_at DESC, id DESC
            LIMIT " . max(1, min(250, $limit))
        );

        $stmt->execute();

        return $stmt->fetchAll();
    }

    private function updateOutboxStatus(
        int $id,
        string $status,
        ?string $error
    ): void {
        if (! $this->tableExists('email_outbox')) {
            throw new RuntimeException('The email_outbox table does not exist.');
        }

        $sets = [];
        $params = [
            'id' => $id,
        ];

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            ['status', 'delivery_status', 'send_status']
        );

        if ($statusColumn) {
            $sets[] = "`{$statusColumn}` = :status";
            $params['status'] = $status;
        }

        if ($status === 'sent' || $status === 'logged') {
            $sentColumn = $this->firstExistingColumn(
                'email_outbox',
                ['sent_at', 'delivered_at', 'processed_at']
            );

            if ($sentColumn) {
                $sets[] = "`{$sentColumn}` = NOW()";
            }
        }

        $attemptColumn = $this->firstExistingColumn(
            'email_outbox',
            ['attempts', 'send_attempts', 'delivery_attempts']
        );

        if ($attemptColumn) {
            $sets[] = "`{$attemptColumn}` = COALESCE(`{$attemptColumn}`, 0) + 1";
        }

        $errorColumn = $this->firstExistingColumn(
            'email_outbox',
            ['last_error', 'error_message', 'failure_reason']
        );

        if ($errorColumn) {
            $sets[] = "`{$errorColumn}` = :error_message";
            $params['error_message'] = $error;
        }

        $updatedColumn = $this->firstExistingColumn(
            'email_outbox',
            ['updated_at', 'processed_at']
        );

        if ($updatedColumn) {
            $sets[] = "`{$updatedColumn}` = NOW()";
        }

        if (empty($sets)) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE email_outbox
            SET " . implode(', ', $sets) . "
            WHERE id = :id
        ");

        $stmt->execute($params);
    }

    private function countByStatus(array $statuses): int
    {
        if (! $this->tableExists('email_outbox')) {
            return 0;
        }

        $statusColumn = $this->firstExistingColumn(
            'email_outbox',
            ['status', 'delivery_status', 'send_status']
        );

        if (! $statusColumn) {
            if (in_array('pending', $statuses, true) && $this->columnExists('email_outbox', 'sent_at')) {
                return $this->countWhere('email_outbox', 'sent_at IS NULL');
            }

            return 0;
        }

        $placeholders = [];
        $params = [];

        foreach ($statuses as $index => $status) {
            $key = 'status_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $status;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM email_outbox
            WHERE `{$statusColumn}` IN (" . implode(',', $placeholders) . ")
        ");

        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    private function countWhere(string $table, string $where): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM `{$table}`
            WHERE {$where}
        ");

        return (int) $stmt->fetchColumn();
    }

    private function attemptCount(): int
    {
        if (! $this->tableExists('mission_control_email_queue_attempts')) {
            return 0;
        }

        return $this->countWhere(
            'mission_control_email_queue_attempts',
            '1 = 1'
        );
    }

    private function lastAttemptAt(): ?string
    {
        if (! $this->tableExists('mission_control_email_queue_attempts')) {
            return null;
        }

        $stmt = $this->db->query("
            SELECT MAX(attempted_at)
            FROM mission_control_email_queue_attempts
        ");

        $value = $stmt->fetchColumn();

        return $value ? (string) $value : null;
    }

    private function emailSelectSql(): string
    {
        return implode(', ', [
            'id',
            $this->aliasColumn('email_outbox', ['to_email', 'recipient_email', 'email', 'recipient'], 'recipient'),
            $this->aliasColumn('email_outbox', ['subject', 'email_subject'], 'subject'),
            $this->aliasColumn('email_outbox', ['body', 'body_text', 'text_body', 'message'], 'body_text'),
            $this->aliasColumn('email_outbox', ['body_html', 'html_body', 'html', 'message_html'], 'body_html'),
            $this->aliasColumn('email_outbox', ['status', 'delivery_status', 'send_status'], 'status'),
            $this->aliasColumn('email_outbox', ['created_at', 'queued_at', 'send_after', 'scheduled_at'], 'created_at'),
        ]);
    }

    private function aliasColumn(
        string $table,
        array $columns,
        string $alias
    ): string {
        foreach ($columns as $column) {
            if ($this->columnExists($table, $column)) {
                return "`{$column}` AS `{$alias}`";
            }
        }

        return "NULL AS `{$alias}`";
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

        $stmt->execute([
            'table_name' => $table,
        ]);

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

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }
}

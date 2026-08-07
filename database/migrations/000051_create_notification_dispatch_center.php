<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->createEmailOutboxIfMissing();

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_dispatches (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                template_id BIGINT UNSIGNED NULL,
                template_key VARCHAR(120) NULL,
                recipient VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'queued',
                email_outbox_id BIGINT UNSIGNED NULL,
                payload_json LONGTEXT NULL,
                error_message VARCHAR(1000) NULL,
                queued_at DATETIME NULL,
                sent_at DATETIME NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_mc_notification_dispatches_template (
                    template_id,
                    created_at
                ),
                KEY idx_mc_notification_dispatches_status (
                    status,
                    created_at
                ),
                KEY idx_mc_notification_dispatches_recipient (
                    recipient,
                    created_at
                ),
                KEY idx_mc_notification_dispatches_outbox (
                    email_outbox_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_dispatch_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                dispatch_id BIGINT UNSIGNED NULL,
                email_outbox_id BIGINT UNSIGNED NULL,
                event_type VARCHAR(60) NOT NULL,
                message VARCHAR(1000) NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mc_notification_dispatch_events_dispatch (
                    dispatch_id,
                    created_at
                ),
                KEY idx_mc_notification_dispatch_events_outbox (
                    email_outbox_id,
                    created_at
                ),
                KEY idx_mc_notification_dispatch_events_type (
                    event_type,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        foreach ([
            'mission_control_notification_dispatch_events',
            'mission_control_notification_dispatches',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function createEmailOutboxIfMissing(): void
    {
        if ($this->tableExists('email_outbox')) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_outbox (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                to_email VARCHAR(255) NOT NULL,
                subject VARCHAR(255) NOT NULL,
                body LONGTEXT NULL,
                body_html LONGTEXT NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error VARCHAR(1000) NULL,
                sent_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_email_outbox_status (
                    status,
                    created_at
                ),
                KEY idx_email_outbox_to_email (
                    to_email,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute([
            'table_name' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

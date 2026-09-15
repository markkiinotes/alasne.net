<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_event_bridge_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_key VARCHAR(120) NOT NULL,
                event_source VARCHAR(120) NOT NULL DEFAULT 'manual',
                status VARCHAR(40) NOT NULL DEFAULT 'received',
                idempotency_key VARCHAR(191) NULL,
                payload_json LONGTEXT NULL,
                matched_rules INT UNSIGNED NOT NULL DEFAULT 0,
                queued_dispatches INT UNSIGNED NOT NULL DEFAULT 0,
                dry_run_events INT UNSIGNED NOT NULL DEFAULT 0,
                skipped_rules INT UNSIGNED NOT NULL DEFAULT 0,
                failed_rules INT UNSIGNED NOT NULL DEFAULT 0,
                message VARCHAR(1000) NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_mc_notification_event_bridge_idempotency (
                    idempotency_key
                ),
                KEY idx_mc_notification_event_bridge_event (
                    event_key,
                    created_at
                ),
                KEY idx_mc_notification_event_bridge_status (
                    status,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_notification_event_bridge_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id BIGINT UNSIGNED NOT NULL,
                rule_id BIGINT UNSIGNED NULL,
                rule_key VARCHAR(140) NULL,
                event_key VARCHAR(120) NOT NULL,
                template_key VARCHAR(120) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'logged',
                recipient VARCHAR(255) NULL,
                dispatch_id BIGINT UNSIGNED NULL,
                email_outbox_id BIGINT UNSIGNED NULL,
                idempotency_key VARCHAR(191) NULL,
                message VARCHAR(1000) NULL,
                error_message VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_mc_notification_event_bridge_item_key (
                    idempotency_key
                ),
                KEY idx_mc_notification_event_bridge_items_run (
                    run_id,
                    created_at
                ),
                KEY idx_mc_notification_event_bridge_items_rule (
                    rule_id,
                    created_at
                ),
                KEY idx_mc_notification_event_bridge_items_status (
                    status,
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
            'mission_control_notification_event_bridge_items',
            'mission_control_notification_event_bridge_runs',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }
};

<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_briefings (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                briefing_number VARCHAR(60) NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                store_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'saved',
                subject VARCHAR(255) NOT NULL,
                executive_summary TEXT NULL,
                open_alert_count INT UNSIGNED NOT NULL DEFAULT 0,
                critical_alert_count INT UNSIGNED NOT NULL DEFAULT 0,
                warning_alert_count INT UNSIGNED NOT NULL DEFAULT 0,
                acknowledged_alert_count INT UNSIGNED NOT NULL DEFAULT 0,
                resolved_alert_count INT UNSIGNED NOT NULL DEFAULT 0,
                tracking_gaps INT UNSIGNED NOT NULL DEFAULT 0,
                open_exceptions INT UNSIGNED NOT NULL DEFAULT 0,
                failed_submissions INT UNSIGNED NOT NULL DEFAULT 0,
                open_returns INT UNSIGNED NOT NULL DEFAULT 0,
                blocked_stores INT UNSIGNED NOT NULL DEFAULT 0,
                sales_revenue DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                gross_profit DECIMAL(14,2) NOT NULL DEFAULT 0.00,
                margin_percent DECIMAL(8,3) NOT NULL DEFAULT 0.000,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_mission_control_briefings_number (
                    briefing_number
                ),
                KEY idx_mission_control_briefings_period (
                    period_start,
                    period_end
                ),
                KEY idx_mission_control_briefings_scope (
                    store_id,
                    supplier_id
                ),
                KEY idx_mission_control_briefings_status (
                    status,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_briefing_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                briefing_id BIGINT UNSIGNED NOT NULL,
                alert_id BIGINT UNSIGNED NULL,
                item_type VARCHAR(60) NOT NULL DEFAULT 'alert',
                severity VARCHAR(30) NOT NULL DEFAULT 'info',
                title VARCHAR(255) NOT NULL,
                message VARCHAR(1000) NULL,
                action_url VARCHAR(500) NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_mission_control_briefing_items_briefing (
                    briefing_id,
                    sort_order
                ),
                KEY idx_mission_control_briefing_items_alert (
                    alert_id
                ),
                KEY idx_mission_control_briefing_items_type (
                    item_type,
                    severity
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        foreach ([
            'mission_control_briefing_items',
            'mission_control_briefings',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }
};

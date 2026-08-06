<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_alert_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rule_key VARCHAR(100) NOT NULL,
                name VARCHAR(191) NOT NULL,
                description VARCHAR(1000) NULL,
                metric_key VARCHAR(100) NOT NULL,
                operator VARCHAR(20) NOT NULL DEFAULT 'greater_than',
                threshold_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
                severity VARCHAR(30) NOT NULL DEFAULT 'warning',
                store_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                cooldown_minutes INT UNSIGNED NOT NULL DEFAULT 60,
                action_url VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_mission_control_alert_rules_key_scope (
                    rule_key,
                    store_id,
                    supplier_id
                ),
                KEY idx_mission_control_alert_rules_enabled (
                    is_enabled,
                    metric_key,
                    severity
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_alerts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                rule_id BIGINT UNSIGNED NULL,
                rule_key VARCHAR(100) NOT NULL,
                metric_key VARCHAR(100) NOT NULL,
                store_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                severity VARCHAR(30) NOT NULL DEFAULT 'warning',
                status VARCHAR(30) NOT NULL DEFAULT 'open',
                title VARCHAR(255) NOT NULL,
                message VARCHAR(1000) NULL,
                metric_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
                threshold_value DECIMAL(14,4) NOT NULL DEFAULT 0.0000,
                action_url VARCHAR(500) NULL,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                resolved_at DATETIME NULL,
                resolved_by BIGINT UNSIGNED NULL,
                resolution_note VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_mission_control_alert_rule_scope_status (
                    rule_key,
                    store_id,
                    supplier_id,
                    status
                ),
                KEY idx_mission_control_alerts_status (
                    status,
                    severity,
                    last_seen_at
                ),
                KEY idx_mission_control_alerts_scope (
                    store_id,
                    supplier_id,
                    status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_alert_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                alert_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(60) NOT NULL,
                old_status VARCHAR(30) NULL,
                new_status VARCHAR(30) NULL,
                message VARCHAR(1000) NULL,
                metric_value DECIMAL(14,4) NULL,
                created_by BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mission_control_alert_events_alert (
                    alert_id,
                    created_at
                ),
                KEY idx_mission_control_alert_events_type (
                    event_type,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedDefaultRules();
    }

    public function down(): void
    {
        foreach ([
            'mission_control_alert_events',
            'mission_control_alerts',
            'mission_control_alert_rules',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function seedDefaultRules(): void
    {
        $rules = [
            [
                'rule_key' => 'tracking_gaps',
                'name' => 'Tracking gaps detected',
                'description' => 'Flags purchase orders that are shipped or delivered but missing tracking.',
                'metric_key' => 'tracking_gaps',
                'operator' => 'greater_than',
                'threshold_value' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 60,
                'action_url' => '/admin/tracking-reconciliation',
            ],
            [
                'rule_key' => 'open_fulfillment_exceptions',
                'name' => 'Open fulfillment exceptions',
                'description' => 'Flags open dropshipping fulfillment exceptions.',
                'metric_key' => 'open_exceptions',
                'operator' => 'greater_than',
                'threshold_value' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 60,
                'action_url' => '/admin/dropshipping',
            ],
            [
                'rule_key' => 'failed_supplier_submissions',
                'name' => 'Failed supplier submissions',
                'description' => 'Flags failed supplier order submissions.',
                'metric_key' => 'failed_submissions',
                'operator' => 'greater_than',
                'threshold_value' => 0,
                'severity' => 'critical',
                'cooldown_minutes' => 60,
                'action_url' => '/admin/supplier-submissions',
            ],
            [
                'rule_key' => 'open_returns',
                'name' => 'Open returns need review',
                'description' => 'Flags returns that are not completed or cancelled.',
                'metric_key' => 'open_returns',
                'operator' => 'greater_than',
                'threshold_value' => 0,
                'severity' => 'warning',
                'cooldown_minutes' => 240,
                'action_url' => '/admin/returns',
            ],
            [
                'rule_key' => 'blocked_store_launches',
                'name' => 'Blocked store launches',
                'description' => 'Flags stores with automation health score below launch threshold.',
                'metric_key' => 'blocked_stores',
                'operator' => 'greater_than',
                'threshold_value' => 0,
                'severity' => 'warning',
                'cooldown_minutes' => 240,
                'action_url' => '/admin/multi-store-automation',
            ],
            [
                'rule_key' => 'low_estimated_margin',
                'name' => 'Estimated margin below target',
                'description' => 'Flags total estimated margin below the configured report threshold.',
                'metric_key' => 'estimated_margin_percent',
                'operator' => 'less_than',
                'threshold_value' => 20,
                'severity' => 'warning',
                'cooldown_minutes' => 360,
                'action_url' => '/admin/reports',
            ],
        ];

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
                NULL,
                NULL,
                1,
                :cooldown_minutes,
                :action_url,
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                metric_key = VALUES(metric_key),
                operator = VALUES(operator),
                threshold_value = VALUES(threshold_value),
                severity = VALUES(severity),
                cooldown_minutes = VALUES(cooldown_minutes),
                action_url = VALUES(action_url),
                updated_at = NOW()
        ");

        foreach ($rules as $rule) {
            $stmt->execute([
                'rule_key' => $rule['rule_key'],
                'name' => $rule['name'],
                'description' => $rule['description'],
                'metric_key' => $rule['metric_key'],
                'operator' => $rule['operator'],
                'threshold_value' => $rule['threshold_value'],
                'severity' => $rule['severity'],
                'cooldown_minutes' => $rule['cooldown_minutes'],
                'action_url' => $rule['action_url'],
            ]);
        }
    }
};

<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS multi_store_launch_profiles (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                launch_status VARCHAR(30) NOT NULL DEFAULT 'planning',
                automation_status VARCHAR(30) NOT NULL DEFAULT 'paused',
                target_launch_date DATE NULL,
                niche_summary VARCHAR(255) NULL,
                primary_supplier_id BIGINT UNSIGNED NULL,
                margin_target_percent DECIMAL(7,3) NOT NULL DEFAULT 25.000,
                minimum_approved_products INT UNSIGNED NOT NULL DEFAULT 10,
                require_return_policy TINYINT(1) NOT NULL DEFAULT 1,
                require_supplier_mapping TINYINT(1) NOT NULL DEFAULT 1,
                require_store_credit_ready TINYINT(1) NOT NULL DEFAULT 0,
                require_tracking_ready TINYINT(1) NOT NULL DEFAULT 1,
                notes TEXT NULL,
                last_audited_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_multi_store_launch_profiles_store (
                    store_id
                ),
                KEY idx_multi_store_launch_profiles_status (
                    launch_status,
                    automation_status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS multi_store_launch_audit_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NULL,
                scope VARCHAR(30) NOT NULL DEFAULT 'all_stores',
                status VARCHAR(30) NOT NULL DEFAULT 'completed',
                stores_checked INT UNSIGNED NOT NULL DEFAULT 0,
                ready_count INT UNSIGNED NOT NULL DEFAULT 0,
                warning_count INT UNSIGNED NOT NULL DEFAULT 0,
                blocked_count INT UNSIGNED NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_multi_store_launch_audit_runs_store (
                    store_id,
                    created_at
                ),
                KEY idx_multi_store_launch_audit_runs_status (
                    status,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS multi_store_launch_audit_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                category VARCHAR(60) NOT NULL,
                check_key VARCHAR(100) NOT NULL,
                severity VARCHAR(30) NOT NULL,
                title VARCHAR(255) NOT NULL,
                message VARCHAR(1000) NULL,
                action_url VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_multi_store_launch_audit_items_run (
                    run_id,
                    severity,
                    store_id
                ),
                KEY idx_multi_store_launch_audit_items_store (
                    store_id,
                    severity,
                    category
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS multi_store_catalog_candidates (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                source_store_id BIGINT UNSIGNED NULL,
                target_store_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                supplier_product_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                candidate_status VARCHAR(30) NOT NULL DEFAULT 'candidate',
                candidate_reason VARCHAR(255) NULL,
                score DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                retail_price_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                supplier_cost_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                net_profit_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                margin_percent_snapshot DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                risk_notes TEXT NULL,
                reviewed_at DATETIME NULL,
                review_note TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_multi_store_catalog_candidate (
                    target_store_id,
                    product_id,
                    supplier_product_id
                ),
                KEY idx_multi_store_catalog_candidates_status (
                    target_store_id,
                    candidate_status,
                    score
                ),
                KEY idx_multi_store_catalog_candidates_supplier (
                    supplier_id,
                    candidate_status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumn(
            'stores',
            'automation_launch_status',
            "VARCHAR(30) NOT NULL DEFAULT 'planning' AFTER status"
        );

        $this->addColumn(
            'stores',
            'automation_health_score',
            "DECIMAL(7,3) NULL AFTER automation_launch_status"
        );

        $this->addColumn(
            'stores',
            'last_automation_audit_at',
            "DATETIME NULL AFTER automation_health_score"
        );
    }

    public function down(): void
    {
        foreach ([
            'last_automation_audit_at',
            'automation_health_score',
            'automation_launch_status',
        ] as $column) {
            $this->dropColumnIfExists(
                'stores',
                $column
            );
        }

        foreach ([
            'multi_store_catalog_candidates',
            'multi_store_launch_audit_items',
            'multi_store_launch_audit_runs',
            'multi_store_launch_profiles',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function addColumn(
        string $table,
        string $column,
        string $definition
    ): void {
        if (! $this->columnExists($table, $column)) {
            $this->db->exec(
                "ALTER TABLE `{$table}`
                 ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    private function dropColumnIfExists(
        string $table,
        string $column
    ): void {
        if ($this->columnExists($table, $column)) {
            $this->db->exec(
                "ALTER TABLE `{$table}`
                 DROP COLUMN `{$column}`"
            );
        }
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
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

        return (int) $stmt->fetchColumn() > 0;
    }
};

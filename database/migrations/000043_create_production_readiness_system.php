<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS production_readiness_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                status VARCHAR(30) NOT NULL DEFAULT 'running',
                environment_name VARCHAR(80) NULL,
                app_url VARCHAR(500) NULL,
                php_version VARCHAR(80) NULL,
                database_version VARCHAR(191) NULL,
                checks_total INT UNSIGNED NOT NULL DEFAULT 0,
                checks_passed INT UNSIGNED NOT NULL DEFAULT 0,
                checks_warned INT UNSIGNED NOT NULL DEFAULT 0,
                checks_failed INT UNSIGNED NOT NULL DEFAULT 0,
                checks_info INT UNSIGNED NOT NULL DEFAULT 0,
                summary TEXT NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_production_readiness_runs_status (
                    status,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS production_readiness_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id BIGINT UNSIGNED NOT NULL,
                category VARCHAR(80) NOT NULL,
                check_code VARCHAR(120) NOT NULL,
                status VARCHAR(30) NOT NULL,
                title VARCHAR(191) NOT NULL,
                message VARCHAR(1000) NOT NULL,
                remediation VARCHAR(1000) NULL,
                evidence TEXT NULL,
                sort_order INT UNSIGNED NOT NULL DEFAULT 100,
                created_at DATETIME NOT NULL,
                KEY idx_production_readiness_items_run (
                    run_id,
                    sort_order,
                    id
                ),
                KEY idx_production_readiness_items_status (
                    status,
                    category
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS production_readiness_items");
        $this->db->exec("DROP TABLE IF EXISTS production_readiness_runs");
    }
};

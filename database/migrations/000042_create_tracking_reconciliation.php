<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tracking_reconciliation_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                source_type VARCHAR(30) NOT NULL DEFAULT 'csv_upload',
                source_file_name VARCHAR(255) NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'running',
                rows_received INT UNSIGNED NOT NULL DEFAULT 0,
                rows_matched INT UNSIGNED NOT NULL DEFAULT 0,
                rows_updated INT UNSIGNED NOT NULL DEFAULT 0,
                rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
                rows_failed INT UNSIGNED NOT NULL DEFAULT 0,
                error_message VARCHAR(1000) NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_tracking_reconciliation_runs_status (
                    status,
                    created_at
                ),
                KEY idx_tracking_reconciliation_runs_store_supplier (
                    store_id,
                    supplier_id,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tracking_reconciliation_rows (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                run_id BIGINT UNSIGNED NOT NULL,
                row_number INT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL,
                match_strategy VARCHAR(60) NULL,
                purchase_order_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,
                store_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                purchase_order_number VARCHAR(80) NULL,
                supplier_order_id VARCHAR(191) NULL,
                carrier VARCHAR(100) NULL,
                tracking_number VARCHAR(191) NULL,
                tracking_url VARCHAR(1000) NULL,
                shipment_status VARCHAR(60) NULL,
                normalized_status VARCHAR(60) NULL,
                message VARCHAR(1000) NULL,
                raw_data LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_tracking_reconciliation_rows_run (
                    run_id,
                    row_number,
                    id
                ),
                KEY idx_tracking_reconciliation_rows_status (
                    status,
                    created_at
                ),
                KEY idx_tracking_reconciliation_rows_tracking (
                    carrier,
                    tracking_number
                ),
                KEY idx_tracking_reconciliation_rows_po (
                    purchase_order_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_tracking_records (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                source_run_id BIGINT UNSIGNED NULL,
                source_row_id BIGINT UNSIGNED NULL,
                source_type VARCHAR(30) NOT NULL DEFAULT 'csv_upload',
                carrier VARCHAR(100) NULL,
                tracking_number VARCHAR(191) NOT NULL,
                tracking_url VARCHAR(1000) NULL,
                shipment_status VARCHAR(60) NOT NULL DEFAULT 'unknown',
                normalized_status VARCHAR(60) NOT NULL DEFAULT 'unknown',
                shipped_at DATETIME NULL,
                delivered_at DATETIME NULL,
                first_seen_at DATETIME NOT NULL,
                last_seen_at DATETIME NOT NULL,
                raw_data LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_supplier_tracking_po_tracking (
                    purchase_order_id,
                    tracking_number
                ),
                KEY idx_supplier_tracking_records_tracking (
                    carrier,
                    tracking_number
                ),
                KEY idx_supplier_tracking_records_order (
                    order_id,
                    normalized_status
                ),
                KEY idx_supplier_tracking_records_supplier (
                    supplier_id,
                    last_seen_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumn(
            'purchase_orders',
            'tracking_status',
            "VARCHAR(60) NOT NULL DEFAULT 'unknown' AFTER tracking_url"
        );

        $this->addColumn(
            'purchase_orders',
            'tracking_source',
            "VARCHAR(60) NULL AFTER tracking_status"
        );

        $this->addColumn(
            'purchase_orders',
            'last_tracking_reconciled_at',
            "DATETIME NULL AFTER tracking_source"
        );
    }

    public function down(): void
    {
        foreach ([
            'last_tracking_reconciled_at',
            'tracking_source',
            'tracking_status',
        ] as $column) {
            $this->dropColumnIfExists(
                'purchase_orders',
                $column
            );
        }

        foreach ([
            'supplier_tracking_records',
            'tracking_reconciliation_rows',
            'tracking_reconciliation_runs',
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

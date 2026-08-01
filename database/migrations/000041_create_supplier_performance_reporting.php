<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_performance_reviews (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                period_start DATE NOT NULL,
                period_end DATE NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'watch',
                score DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                supplier_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                gross_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                purchase_order_count INT UNSIGNED NOT NULL DEFAULT 0,
                late_purchase_order_count INT UNSIGNED NOT NULL DEFAULT 0,
                failed_submission_count INT UNSIGNED NOT NULL DEFAULT 0,
                missing_tracking_count INT UNSIGNED NOT NULL DEFAULT 0,
                open_exception_count INT UNSIGNED NOT NULL DEFAULT 0,
                return_count INT UNSIGNED NOT NULL DEFAULT 0,
                review_note TEXT NULL,
                reviewed_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_supplier_performance_review_period (
                    supplier_id,
                    period_start,
                    period_end
                ),
                KEY idx_supplier_performance_reviews_store (
                    store_id,
                    status,
                    reviewed_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumn(
            'suppliers',
            'performance_status',
            "VARCHAR(30) NOT NULL DEFAULT 'unreviewed' AFTER status"
        );

        $this->addColumn(
            'suppliers',
            'performance_score',
            "DECIMAL(7,3) NULL AFTER performance_status"
        );

        $this->addColumn(
            'suppliers',
            'last_performance_reviewed_at',
            "DATETIME NULL AFTER performance_score"
        );

        $this->addColumn(
            'suppliers',
            'performance_review_note',
            "TEXT NULL AFTER last_performance_reviewed_at"
        );
    }

    public function down(): void
    {
        foreach ([
            'performance_review_note',
            'last_performance_reviewed_at',
            'performance_score',
            'performance_status',
        ] as $column) {
            $this->dropColumnIfExists(
                'suppliers',
                $column
            );
        }

        $this->db->exec("
            DROP TABLE IF EXISTS supplier_performance_reviews
        ");
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

<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS product_sourcing_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                min_gross_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 45.000,
                min_net_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 18.000,
                target_net_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 25.000,
                minimum_profit_amount DECIMAL(12,2) NOT NULL DEFAULT 8.00,
                payment_fee_percent DECIMAL(7,3) NOT NULL DEFAULT 2.900,
                payment_fixed_fee DECIMAL(12,2) NOT NULL DEFAULT 0.30,
                return_allowance_percent DECIMAL(7,3) NOT NULL DEFAULT 5.000,
                discount_allowance_percent DECIMAL(7,3) NOT NULL DEFAULT 10.000,
                ad_spend_percent DECIMAL(7,3) NOT NULL DEFAULT 15.000,
                shipping_allowance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                target_markup_percent DECIMAL(7,3) NOT NULL DEFAULT 100.000,
                high_risk_shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 12.00,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_product_sourcing_rules_store (store_id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS product_sourcing_reviews (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                supplier_product_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'unreviewed',
                recommendation VARCHAR(30) NOT NULL DEFAULT 'watch',
                score DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                product_name_snapshot VARCHAR(191) NOT NULL,
                store_sku_snapshot VARCHAR(191) NULL,
                supplier_name_snapshot VARCHAR(191) NOT NULL,
                supplier_sku_snapshot VARCHAR(191) NOT NULL,
                retail_price_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                supplier_cost_snapshot DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                estimated_shipping DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payment_fee DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                return_allowance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                discount_allowance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                ad_spend_target DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                gross_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                gross_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                net_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                net_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                break_even_ad_spend DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                suggested_min_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                suggested_target_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                risk_notes TEXT NULL,
                review_note TEXT NULL,
                reviewed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_product_sourcing_review_mapping (
                    supplier_product_id
                ),
                KEY idx_product_sourcing_reviews_store (
                    store_id,
                    status,
                    recommendation
                ),
                KEY idx_product_sourcing_reviews_supplier (
                    supplier_id,
                    status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumn(
            'supplier_products',
            'sourcing_status',
            "VARCHAR(30) NOT NULL DEFAULT 'unreviewed' AFTER stock_status"
        );

        $this->addColumn(
            'supplier_products',
            'sourcing_score',
            "DECIMAL(7,3) NULL AFTER sourcing_status"
        );

        $this->addColumn(
            'supplier_products',
            'sourcing_recommendation',
            "VARCHAR(30) NULL AFTER sourcing_score"
        );

        $this->addColumn(
            'supplier_products',
            'sourcing_reviewed_at',
            "DATETIME NULL AFTER sourcing_recommendation"
        );

        $this->addColumn(
            'supplier_products',
            'sourcing_review_note',
            "TEXT NULL AFTER sourcing_reviewed_at"
        );
    }

    public function down(): void
    {
        foreach ([
            'sourcing_review_note',
            'sourcing_reviewed_at',
            'sourcing_recommendation',
            'sourcing_score',
            'sourcing_status',
        ] as $column) {
            $this->dropColumnIfExists(
                'supplier_products',
                $column
            );
        }

        foreach ([
            'product_sourcing_reviews',
            'product_sourcing_rules',
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

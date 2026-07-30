<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS tax_rules (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(100) NOT NULL,
                country_code CHAR(2) NOT NULL DEFAULT 'US',
                state_region VARCHAR(100) NULL,
                postal_code_prefix VARCHAR(20) NULL,
                rate DECIMAL(8,5) NOT NULL DEFAULT 0.00000,
                tax_shipping TINYINT(1) NOT NULL DEFAULT 0,
                priority INT NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_tax_rules_store_code (
                    store_id,
                    code
                ),
                KEY idx_tax_rules_store_active (
                    store_id,
                    is_active
                ),
                KEY idx_tax_rules_destination (
                    store_id,
                    country_code,
                    state_region,
                    postal_code_prefix,
                    is_active
                ),
                KEY idx_tax_rules_priority (
                    store_id,
                    priority
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addOrderColumn(
            'tax_rule_id',
            "BIGINT UNSIGNED NULL AFTER tax_total"
        );

        $this->addOrderColumn(
            'tax_rule_name',
            "VARCHAR(150) NULL AFTER tax_rule_id"
        );

        $this->addOrderColumn(
            'tax_rule_code',
            "VARCHAR(100) NULL AFTER tax_rule_name"
        );

        $this->addOrderColumn(
            'tax_rate',
            "DECIMAL(8,5) NOT NULL DEFAULT 0.00000 AFTER tax_rule_code"
        );

        $this->addOrderColumn(
            'taxable_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER tax_rate"
        );

        $this->addOrderColumn(
            'tax_shipping',
            "TINYINT(1) NOT NULL DEFAULT 0 AFTER taxable_amount"
        );

        $this->addOrderColumn(
            'tax_country_code',
            "CHAR(2) NULL AFTER tax_shipping"
        );

        $this->addOrderColumn(
            'tax_state_region',
            "VARCHAR(100) NULL AFTER tax_country_code"
        );

        $this->addOrderColumn(
            'tax_postal_code',
            "VARCHAR(32) NULL AFTER tax_state_region"
        );

        if (! $this->indexExists(
            'orders',
            'idx_orders_tax_rule_id'
        )) {
            $this->db->exec("
                ALTER TABLE orders
                ADD KEY idx_orders_tax_rule_id (
                    tax_rule_id
                )
            ");
        }
    }

    public function down(): void
    {
        if ($this->indexExists(
            'orders',
            'idx_orders_tax_rule_id'
        )) {
            $this->db->exec("
                ALTER TABLE orders
                DROP INDEX idx_orders_tax_rule_id
            ");
        }

        $columns = [
            'tax_postal_code',
            'tax_state_region',
            'tax_country_code',
            'tax_shipping',
            'taxable_amount',
            'tax_rate',
            'tax_rule_code',
            'tax_rule_name',
            'tax_rule_id',
        ];

        foreach ($columns as $column) {
            if ($this->columnExists(
                'orders',
                $column
            )) {
                $this->db->exec(
                    "ALTER TABLE orders "
                    . "DROP COLUMN `{$column}`"
                );
            }
        }

        $this->db->exec("
            DROP TABLE IF EXISTS tax_rules
        ");
    }

    private function addOrderColumn(
        string $column,
        string $definition
    ): void {
        if ($this->columnExists(
            'orders',
            $column
        )) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE orders "
            . "ADD COLUMN `{$column}` {$definition}"
        );
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

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

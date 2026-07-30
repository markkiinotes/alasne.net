<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS payment_methods (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(150) NOT NULL,
                code VARCHAR(100) NOT NULL,
                provider VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,
                instructions TEXT NULL,
                is_test_mode TINYINT(1) NOT NULL DEFAULT 0,
                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,
                config_json LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_payment_methods_store_code (
                    store_id,
                    code
                ),
                KEY idx_payment_methods_store_active (
                    store_id,
                    is_active
                ),
                KEY idx_payment_methods_provider (
                    provider
                ),
                KEY idx_payment_methods_sort (
                    store_id,
                    sort_order
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS payment_transactions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                payment_method_id BIGINT UNSIGNED NULL,
                parent_transaction_id BIGINT UNSIGNED NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'charge',
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                provider VARCHAR(100) NOT NULL,
                provider_transaction_id VARCHAR(191) NULL,
                idempotency_key VARCHAR(191) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                refunded_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                payment_method_name VARCHAR(150) NULL,
                payment_method_code VARCHAR(100) NULL,
                customer_email VARCHAR(191) NULL,
                request_json LONGTEXT NULL,
                response_json LONGTEXT NULL,
                failure_code VARCHAR(100) NULL,
                failure_message VARCHAR(500) NULL,
                processed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_payment_transactions_idempotency (
                    idempotency_key
                ),
                KEY idx_payment_transactions_order (
                    order_id,
                    created_at
                ),
                KEY idx_payment_transactions_store (
                    store_id,
                    status,
                    created_at
                ),
                KEY idx_payment_transactions_method (
                    payment_method_id
                ),
                KEY idx_payment_transactions_parent (
                    parent_transaction_id
                ),
                KEY idx_payment_transactions_provider_id (
                    provider,
                    provider_transaction_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addOrderColumn(
            'payment_status',
            "VARCHAR(30) NOT NULL DEFAULT 'unpaid' AFTER status"
        );

        $this->addOrderColumn(
            'payment_method_id',
            "BIGINT UNSIGNED NULL AFTER payment_status"
        );

        $this->addOrderColumn(
            'payment_method_name',
            "VARCHAR(150) NULL AFTER payment_method_id"
        );

        $this->addOrderColumn(
            'payment_method_code',
            "VARCHAR(100) NULL AFTER payment_method_name"
        );

        $this->addOrderColumn(
            'payment_provider',
            "VARCHAR(100) NULL AFTER payment_method_code"
        );

        $this->addOrderColumn(
            'payment_transaction_id',
            "BIGINT UNSIGNED NULL AFTER payment_provider"
        );

        $this->addOrderColumn(
            'currency',
            "CHAR(3) NOT NULL DEFAULT 'USD' AFTER payment_transaction_id"
        );

        $this->addOrderColumn(
            'amount_paid',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER currency"
        );

        $this->addOrderColumn(
            'amount_refunded',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount_paid"
        );

        $this->addOrderColumn(
            'paid_at',
            "DATETIME NULL AFTER amount_refunded"
        );

        $this->addOrderColumn(
            'payment_failed_at',
            "DATETIME NULL AFTER paid_at"
        );

        $this->addOrderIndex(
            'idx_orders_payment_status',
            'payment_status'
        );

        $this->addOrderIndex(
            'idx_orders_payment_method_id',
            'payment_method_id'
        );

        $this->addOrderIndex(
            'idx_orders_payment_transaction_id',
            'payment_transaction_id'
        );

        $this->db->exec("
            UPDATE orders
            SET
                payment_status = CASE
                    WHEN status = 'paid'
                    THEN 'paid'
                    ELSE 'unpaid'
                END,
                amount_paid = CASE
                    WHEN status = 'paid'
                    THEN grand_total
                    ELSE 0.00
                END,
                paid_at = CASE
                    WHEN status = 'paid'
                    THEN COALESCE(
                        placed_at,
                        created_at
                    )
                    ELSE NULL
                END
            WHERE
                payment_status = 'unpaid'
                AND amount_paid = 0.00
        ");

        /*
         * Seed a development-only payment method for every
         * current store. It performs no external network call.
         */
        $this->db->exec("
            INSERT INTO payment_methods (
                store_id,
                name,
                code,
                provider,
                description,
                instructions,
                is_test_mode,
                is_active,
                sort_order,
                config_json,
                created_at,
                updated_at
            )
            SELECT
                s.id,
                'Test Payment',
                'test-payment',
                'test',
                'Development-only simulated payment provider.',
                'Use the test scenarios approved, declined, or error.',
                1,
                1,
                0,
                '{\"default_scenario\":\"approved\"}',
                NOW(),
                NOW()
            FROM stores s
            WHERE NOT EXISTS (
                SELECT 1
                FROM payment_methods pm
                WHERE pm.store_id = s.id
                AND pm.code = 'test-payment'
            )
        ");
    }

    public function down(): void
    {
        $indexes = [
            'idx_orders_payment_transaction_id',
            'idx_orders_payment_method_id',
            'idx_orders_payment_status',
        ];

        foreach ($indexes as $index) {
            if ($this->indexExists(
                'orders',
                $index
            )) {
                $this->db->exec(
                    "ALTER TABLE orders "
                    . "DROP INDEX `{$index}`"
                );
            }
        }

        $columns = [
            'payment_failed_at',
            'paid_at',
            'amount_refunded',
            'amount_paid',
            'currency',
            'payment_transaction_id',
            'payment_provider',
            'payment_method_code',
            'payment_method_name',
            'payment_method_id',
            'payment_status',
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
            DROP TABLE IF EXISTS payment_transactions
        ");

        $this->db->exec("
            DROP TABLE IF EXISTS payment_methods
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

    private function addOrderIndex(
        string $index,
        string $column
    ): void {
        if ($this->indexExists(
            'orders',
            $index
        )) {
            return;
        }

        $this->db->exec(
            "ALTER TABLE orders "
            . "ADD KEY `{$index}` (`{$column}`)"
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

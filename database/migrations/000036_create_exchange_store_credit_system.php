<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->addReturnResolutionColumns();
        $this->addExchangeOrderColumns();

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS store_credit_accounts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                balance DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_store_credit_account (
                    store_id,
                    customer_id,
                    currency
                ),
                KEY idx_store_credit_customer (
                    customer_id,
                    store_id
                ),
                KEY idx_store_credit_status (status)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS store_credit_transactions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                return_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,
                type VARCHAR(30) NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                balance_after DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                idempotency_key VARCHAR(191) NOT NULL,
                notes VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_store_credit_idempotency (
                    idempotency_key
                ),
                KEY idx_store_credit_transactions_account (
                    account_id,
                    created_at
                ),
                KEY idx_store_credit_transactions_return (
                    return_id
                ),
                KEY idx_store_credit_transactions_order (
                    order_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_exchanges (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                original_order_id BIGINT UNSIGNED NOT NULL,
                exchange_order_id BIGINT UNSIGNED NOT NULL,
                exchange_number VARCHAR(60) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'processing',
                merchandise_value DECIMAL(12,2)
                    NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                notes VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_return_exchange_return (
                    return_id
                ),
                UNIQUE KEY uq_return_exchange_number (
                    exchange_number
                ),
                UNIQUE KEY uq_return_exchange_order (
                    exchange_order_id
                ),
                KEY idx_return_exchange_original_order (
                    original_order_id
                ),
                KEY idx_return_exchange_store (
                    store_id,
                    status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_exchange_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                exchange_id BIGINT UNSIGNED NOT NULL,
                return_item_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                product_sku VARCHAR(191) NULL,
                quantity INT UNSIGNED NOT NULL,
                unit_price DECIMAL(12,2) NOT NULL,
                line_total DECIMAL(12,2) NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_return_exchange_items_exchange (
                    exchange_id
                ),
                KEY idx_return_exchange_items_return_item (
                    return_item_id
                ),
                KEY idx_return_exchange_items_product (
                    product_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            UPDATE returns
            SET
                resolution_type = CASE
                    WHEN refunded_amount > 0
                    THEN 'refund'
                    ELSE 'none'
                END,
                resolution_status = CASE
                    WHEN refund_status = 'failed'
                    THEN 'partial_failed'
                    WHEN status = 'completed'
                    THEN 'completed'
                    ELSE 'none'
                END,
                cash_refund_amount =
                    refunded_amount
            WHERE status = 'completed'
            AND resolution_status = 'none'
        ");
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS return_exchange_items'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS return_exchanges'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS store_credit_transactions'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS store_credit_accounts'
        );

        foreach (
            [
                'original_order_id',
                'source_return_id',
                'order_type',
            ]
            as $column
        ) {
            if ($this->columnExists('orders', $column)) {
                $this->db->exec(
                    'ALTER TABLE orders DROP COLUMN '
                    . $column
                );
            }
        }

        foreach (
            [
                'resolution_notes',
                'exchange_order_id',
                'exchange_value',
                'store_credit_amount',
                'cash_refund_amount',
                'resolution_status',
                'resolution_type',
            ]
            as $column
        ) {
            if ($this->columnExists('returns', $column)) {
                $this->db->exec(
                    'ALTER TABLE returns DROP COLUMN '
                    . $column
                );
            }
        }
    }

    private function addReturnResolutionColumns(): void
    {
        $columns = [
            'resolution_type' => "
                ADD COLUMN resolution_type VARCHAR(30)
                    NOT NULL DEFAULT 'none'
                AFTER refund_status
            ",
            'resolution_status' => "
                ADD COLUMN resolution_status VARCHAR(30)
                    NOT NULL DEFAULT 'none'
                AFTER resolution_type
            ",
            'cash_refund_amount' => "
                ADD COLUMN cash_refund_amount DECIMAL(12,2)
                    NOT NULL DEFAULT 0.00
                AFTER resolution_status
            ",
            'store_credit_amount' => "
                ADD COLUMN store_credit_amount DECIMAL(12,2)
                    NOT NULL DEFAULT 0.00
                AFTER cash_refund_amount
            ",
            'exchange_value' => "
                ADD COLUMN exchange_value DECIMAL(12,2)
                    NOT NULL DEFAULT 0.00
                AFTER store_credit_amount
            ",
            'exchange_order_id' => "
                ADD COLUMN exchange_order_id BIGINT UNSIGNED NULL
                AFTER exchange_value
            ",
            'resolution_notes' => "
                ADD COLUMN resolution_notes VARCHAR(1000) NULL
                AFTER exchange_order_id
            ",
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->columnExists('returns', $name)) {
                $this->db->exec(
                    'ALTER TABLE returns ' . $definition
                );
            }
        }

        if (! $this->indexExists(
            'returns',
            'idx_returns_resolution'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD KEY idx_returns_resolution (
                    resolution_type,
                    resolution_status,
                    completed_at
                )
            ");
        }

        if (! $this->indexExists(
            'returns',
            'idx_returns_exchange_order'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD KEY idx_returns_exchange_order (
                    exchange_order_id
                )
            ");
        }
    }

    private function addExchangeOrderColumns(): void
    {
        $columns = [
            'order_type' => "
                ADD COLUMN order_type VARCHAR(30)
                    NOT NULL DEFAULT 'sale'
                AFTER status
            ",
            'source_return_id' => "
                ADD COLUMN source_return_id BIGINT UNSIGNED NULL
                AFTER order_type
            ",
            'original_order_id' => "
                ADD COLUMN original_order_id BIGINT UNSIGNED NULL
                AFTER source_return_id
            ",
        ];

        foreach ($columns as $name => $definition) {
            if (! $this->columnExists('orders', $name)) {
                $this->db->exec(
                    'ALTER TABLE orders ' . $definition
                );
            }
        }

        if (! $this->indexExists(
            'orders',
            'idx_orders_source_return'
        )) {
            $this->db->exec("
                ALTER TABLE orders
                ADD KEY idx_orders_source_return (
                    source_return_id
                )
            ");
        }

        if (! $this->indexExists(
            'orders',
            'idx_orders_original_order'
        )) {
            $this->db->exec("
                ALTER TABLE orders
                ADD KEY idx_orders_original_order (
                    original_order_id
                )
            ");
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

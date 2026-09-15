<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS returns (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_number VARCHAR(60) NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'requested',
                reason_code VARCHAR(50) NOT NULL DEFAULT 'other',
                reason_details VARCHAR(500) NULL,
                customer_notes TEXT NULL,
                internal_notes TEXT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                requested_refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                approved_refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                refunded_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                refund_status VARCHAR(30) NOT NULL DEFAULT 'none',
                refund_transaction_id BIGINT UNSIGNED NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                requested_at DATETIME NOT NULL,
                approved_at DATETIME NULL,
                received_at DATETIME NULL,
                completed_at DATETIME NULL,
                cancelled_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_returns_return_number (return_number),
                KEY idx_returns_order (order_id, created_at),
                KEY idx_returns_store_status (store_id, status, created_at),
                KEY idx_returns_refund_transaction (refund_transaction_id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_id BIGINT UNSIGNED NOT NULL,
                order_item_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NULL,
                product_name VARCHAR(255) NOT NULL,
                product_sku VARCHAR(191) NULL,
                quantity_ordered INT UNSIGNED NOT NULL,
                quantity_requested INT UNSIGNED NOT NULL,
                quantity_received INT UNSIGNED NOT NULL DEFAULT 0,
                quantity_restocked INT UNSIGNED NOT NULL DEFAULT 0,
                quantity_discarded INT UNSIGNED NOT NULL DEFAULT 0,
                unit_price DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                requested_refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                approved_refund_amount DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                reason_code VARCHAR(50) NULL,
                condition_code VARCHAR(50) NULL,
                resolution_code VARCHAR(50) NOT NULL DEFAULT 'refund',
                notes VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_return_items_return_order_item (
                    return_id,
                    order_item_id
                ),
                KEY idx_return_items_order_item (order_item_id),
                KEY idx_return_items_product (product_id),
                KEY idx_return_items_return (return_id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(50) NOT NULL,
                title VARCHAR(191) NOT NULL,
                description TEXT NULL,
                old_value VARCHAR(191) NULL,
                new_value VARCHAR(191) NULL,
                created_by_user_id BIGINT UNSIGNED NULL,
                created_at DATETIME NOT NULL,
                KEY idx_return_events_return (return_id, created_at),
                KEY idx_return_events_type (type)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        if (! $this->columnExists(
            'inventory_movements',
            'return_id'
        )) {
            $this->db->exec("
                ALTER TABLE inventory_movements
                ADD COLUMN return_id BIGINT UNSIGNED NULL
                AFTER order_id
            ");
        }

        if (! $this->indexExists(
            'inventory_movements',
            'idx_inventory_movements_return_id'
        )) {
            $this->db->exec("
                ALTER TABLE inventory_movements
                ADD KEY idx_inventory_movements_return_id (
                    return_id
                )
            ");
        }
    }

    public function down(): void
    {
        if ($this->indexExists(
            'inventory_movements',
            'idx_inventory_movements_return_id'
        )) {
            $this->db->exec("
                ALTER TABLE inventory_movements
                DROP INDEX idx_inventory_movements_return_id
            ");
        }

        if ($this->columnExists(
            'inventory_movements',
            'return_id'
        )) {
            $this->db->exec("
                ALTER TABLE inventory_movements
                DROP COLUMN return_id
            ");
        }

        $this->db->exec(
            'DROP TABLE IF EXISTS return_events'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS return_items'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS returns'
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

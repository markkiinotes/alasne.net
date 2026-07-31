<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->addAccountColumn(
            'reserved_balance',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER balance"
        );

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS store_credit_reservations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                amount DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                reservation_key VARCHAR(191) NOT NULL,
                expires_at DATETIME NOT NULL,
                consumed_at DATETIME NULL,
                released_at DATETIME NULL,
                release_reason VARCHAR(255) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_store_credit_reservation_order (
                    order_id
                ),
                UNIQUE KEY uq_store_credit_reservation_key (
                    reservation_key
                ),
                KEY idx_store_credit_reservation_account (
                    account_id,
                    status,
                    expires_at
                ),
                KEY idx_store_credit_reservation_customer (
                    store_id,
                    customer_id,
                    currency,
                    status
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addOrderColumn(
            'store_credit_reserved_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER amount_paid"
        );

        $this->addOrderColumn(
            'store_credit_applied_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER store_credit_reserved_amount"
        );

        $this->addOrderColumn(
            'store_credit_restored_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER store_credit_applied_amount"
        );

        $this->addOrderColumn(
            'external_payment_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER store_credit_restored_amount"
        );

        $this->addOrderColumn(
            'store_credit_reservation_id',
            "BIGINT UNSIGNED NULL AFTER external_payment_amount"
        );

        $this->addOrderColumn(
            'store_credit_transaction_id',
            "BIGINT UNSIGNED NULL AFTER store_credit_reservation_id"
        );

        $this->addReturnColumn(
            'redeemed_credit_restored_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER cash_refund_amount"
        );

        $this->addReturnColumn(
            'external_refund_amount',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER redeemed_credit_restored_amount"
        );

        $this->addOrderIndex(
            'idx_orders_store_credit_reservation',
            'store_credit_reservation_id'
        );

        $this->addOrderIndex(
            'idx_orders_store_credit_transaction',
            'store_credit_transaction_id'
        );

        $this->db->exec("
            UPDATE orders
            SET external_payment_amount = amount_paid
            WHERE external_payment_amount = 0.00
            AND store_credit_applied_amount = 0.00
            AND amount_paid > 0.00
        ");
    }

    public function down(): void
    {
        foreach (
            [
                'idx_orders_store_credit_transaction',
                'idx_orders_store_credit_reservation',
            ]
            as $index
        ) {
            if ($this->indexExists('orders', $index)) {
                $this->db->exec(
                    "ALTER TABLE orders DROP INDEX `{$index}`"
                );
            }
        }

        foreach (
            [
                'store_credit_transaction_id',
                'store_credit_reservation_id',
                'external_payment_amount',
                'store_credit_restored_amount',
                'store_credit_applied_amount',
                'store_credit_reserved_amount',
            ]
            as $column
        ) {
            if ($this->columnExists('orders', $column)) {
                $this->db->exec(
                    "ALTER TABLE orders DROP COLUMN `{$column}`"
                );
            }
        }

        foreach (
            [
                'external_refund_amount',
                'redeemed_credit_restored_amount',
            ]
            as $column
        ) {
            if ($this->columnExists('returns', $column)) {
                $this->db->exec(
                    "ALTER TABLE returns DROP COLUMN `{$column}`"
                );
            }
        }

        $this->db->exec(
            'DROP TABLE IF EXISTS store_credit_reservations'
        );

        if (
            $this->columnExists(
                'store_credit_accounts',
                'reserved_balance'
            )
        ) {
            $this->db->exec("
                ALTER TABLE store_credit_accounts
                DROP COLUMN reserved_balance
            ");
        }
    }

    private function addAccountColumn(
        string $column,
        string $definition
    ): void {
        if (
            ! $this->columnExists(
                'store_credit_accounts',
                $column
            )
        ) {
            $this->db->exec(
                "ALTER TABLE store_credit_accounts "
                . "ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    private function addOrderColumn(
        string $column,
        string $definition
    ): void {
        if (! $this->columnExists('orders', $column)) {
            $this->db->exec(
                "ALTER TABLE orders "
                . "ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    private function addReturnColumn(
        string $column,
        string $definition
    ): void {
        if (! $this->columnExists('returns', $column)) {
            $this->db->exec(
                "ALTER TABLE returns "
                . "ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    private function addOrderIndex(
        string $index,
        string $column
    ): void {
        if (! $this->indexExists('orders', $index)) {
            $this->db->exec(
                "ALTER TABLE orders "
                . "ADD KEY `{$index}` (`{$column}`)"
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

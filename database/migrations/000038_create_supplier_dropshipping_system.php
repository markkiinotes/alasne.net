<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS suppliers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(191) NOT NULL,
                code VARCHAR(80) NOT NULL,
                supplier_type VARCHAR(50) NOT NULL DEFAULT 'manual',
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                contact_name VARCHAR(191) NULL,
                email VARCHAR(191) NULL,
                phone VARCHAR(80) NULL,
                website VARCHAR(1000) NULL,
                account_reference VARCHAR(191) NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                default_lead_time_min SMALLINT UNSIGNED NULL,
                default_lead_time_max SMALLINT UNSIGNED NULL,
                priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
                auto_submit TINYINT(1) NOT NULL DEFAULT 0,
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_suppliers_store_code (
                    store_id,
                    code
                ),
                KEY idx_suppliers_store_status (
                    store_id,
                    status,
                    priority
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                supplier_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                supplier_sku VARCHAR(191) NOT NULL,
                wholesale_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                available_quantity INT UNSIGNED NULL,
                stock_status VARCHAR(30) NOT NULL DEFAULT 'unknown',
                lead_time_min SMALLINT UNSIGNED NULL,
                lead_time_max SMALLINT UNSIGNED NULL,
                minimum_order_quantity INT UNSIGNED NOT NULL DEFAULT 1,
                pack_size INT UNSIGNED NOT NULL DEFAULT 1,
                is_preferred TINYINT(1) NOT NULL DEFAULT 0,
                priority SMALLINT UNSIGNED NOT NULL DEFAULT 100,
                last_synced_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_supplier_products_mapping (
                    supplier_id,
                    product_id
                ),
                KEY idx_supplier_products_product (
                    store_id,
                    product_id,
                    is_preferred,
                    priority
                ),
                KEY idx_supplier_products_stock (
                    stock_status,
                    available_quantity
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS purchase_orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                purchase_order_number VARCHAR(80) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                items_subtotal DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                shipping_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                tax_total DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                total_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                customer_revenue DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                estimated_profit DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                estimated_margin_percent DECIMAL(7,3) NOT NULL DEFAULT 0.000,
                external_order_id VARCHAR(191) NULL,
                supplier_reference VARCHAR(191) NULL,
                shipping_carrier VARCHAR(100) NULL,
                tracking_number VARCHAR(191) NULL,
                tracking_url VARCHAR(1000) NULL,
                expected_ship_at DATETIME NULL,
                submitted_at DATETIME NULL,
                accepted_at DATETIME NULL,
                shipped_at DATETIME NULL,
                delivered_at DATETIME NULL,
                notes TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_purchase_orders_number (
                    purchase_order_number
                ),
                UNIQUE KEY uq_purchase_orders_order_supplier (
                    order_id,
                    supplier_id
                ),
                KEY idx_purchase_orders_status (
                    store_id,
                    status,
                    updated_at
                ),
                KEY idx_purchase_orders_order (order_id),
                KEY idx_purchase_orders_supplier (supplier_id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS purchase_order_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id BIGINT UNSIGNED NOT NULL,
                order_item_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                supplier_product_id BIGINT UNSIGNED NOT NULL,
                supplier_sku VARCHAR(191) NOT NULL,
                product_name VARCHAR(191) NOT NULL,
                quantity INT UNSIGNED NOT NULL,
                unit_cost DECIMAL(12,2) NOT NULL,
                line_cost DECIMAL(12,2) NOT NULL,
                customer_unit_price DECIMAL(12,2) NOT NULL,
                customer_line_revenue DECIMAL(12,2) NOT NULL,
                estimated_profit DECIMAL(12,2) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_purchase_order_items_order_item (
                    order_item_id
                ),
                KEY idx_purchase_order_items_po (
                    purchase_order_id,
                    status
                ),
                KEY idx_purchase_order_items_product (product_id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS purchase_order_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(60) NOT NULL,
                title VARCHAR(191) NOT NULL,
                description VARCHAR(1000) NULL,
                old_value VARCHAR(191) NULL,
                new_value VARCHAR(191) NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 0,
                created_at DATETIME NOT NULL,
                KEY idx_purchase_order_events_po (
                    purchase_order_id,
                    created_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS dropship_exceptions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                order_item_id BIGINT UNSIGNED NULL,
                product_id BIGINT UNSIGNED NULL,
                exception_code VARCHAR(80) NOT NULL,
                message VARCHAR(1000) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'open',
                resolution_note VARCHAR(1000) NULL,
                resolved_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                KEY idx_dropship_exceptions_order (
                    order_id,
                    status
                ),
                KEY idx_dropship_exceptions_store (
                    store_id,
                    status,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addOrderColumn(
            'dropship_status',
            "VARCHAR(30) NOT NULL DEFAULT 'unrouted' AFTER status"
        );
        $this->addOrderColumn(
            'supplier_cost_total',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER grand_total"
        );
        $this->addOrderColumn(
            'estimated_gross_profit',
            "DECIMAL(12,2) NOT NULL DEFAULT 0.00 AFTER supplier_cost_total"
        );
        $this->addOrderColumn(
            'estimated_margin_percent',
            "DECIMAL(7,3) NOT NULL DEFAULT 0.000 AFTER estimated_gross_profit"
        );
        $this->addOrderColumn(
            'dropship_routed_at',
            "DATETIME NULL AFTER estimated_margin_percent"
        );
    }

    public function down(): void
    {
        foreach ([
            'dropship_routed_at',
            'estimated_margin_percent',
            'estimated_gross_profit',
            'supplier_cost_total',
            'dropship_status',
        ] as $column) {
            if ($this->columnExists('orders', $column)) {
                $this->db->exec(
                    "ALTER TABLE orders DROP COLUMN `{$column}`"
                );
            }
        }

        foreach ([
            'dropship_exceptions',
            'purchase_order_events',
            'purchase_order_items',
            'purchase_orders',
            'supplier_products',
            'suppliers',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function addOrderColumn(
        string $column,
        string $definition
    ): void {
        if (! $this->columnExists('orders', $column)) {
            $this->db->exec(
                "ALTER TABLE orders ADD COLUMN `{$column}` {$definition}"
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

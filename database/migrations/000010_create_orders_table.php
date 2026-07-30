<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS orders (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_number VARCHAR(100) NOT NULL UNIQUE,
                store_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                subtotal DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                tax_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                shipping_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                discount_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                grand_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                placed_at DATETIME NULL,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                INDEX orders_store_id_index (store_id),
                INDEX orders_customer_id_index (customer_id),
                INDEX orders_status_index (status),

                CONSTRAINT orders_store_id_foreign
                    FOREIGN KEY (store_id)
                    REFERENCES stores(id)
                    ON DELETE CASCADE,

                CONSTRAINT orders_customer_id_foreign
                    FOREIGN KEY (customer_id)
                    REFERENCES customers(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS orders");
    }
};
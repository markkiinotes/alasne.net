<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS order_items (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                product_name VARCHAR(255) NOT NULL,
                sku VARCHAR(100) NULL,
                quantity INT NOT NULL DEFAULT 1,
                unit_price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                line_total DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                INDEX order_items_order_id_index (order_id),
                INDEX order_items_product_id_index (product_id),

                CONSTRAINT order_items_order_id_foreign
                    FOREIGN KEY (order_id)
                    REFERENCES orders(id)
                    ON DELETE CASCADE,

                CONSTRAINT order_items_product_id_foreign
                    FOREIGN KEY (product_id)
                    REFERENCES products(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS order_items");
    }
};
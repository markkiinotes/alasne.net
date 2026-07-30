<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS inventory_movements (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                product_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NULL,
                type VARCHAR(50) NOT NULL,
                quantity INT NOT NULL,
                balance_after INT NOT NULL,
                note VARCHAR(255) NULL,
                created_at DATETIME NULL,

                INDEX inventory_movements_product_id_index (product_id),
                INDEX inventory_movements_order_id_index (order_id),
                INDEX inventory_movements_type_index (type),

                CONSTRAINT inventory_movements_product_id_foreign
                    FOREIGN KEY (product_id)
                    REFERENCES products(id)
                    ON DELETE CASCADE,

                CONSTRAINT inventory_movements_order_id_foreign
                    FOREIGN KEY (order_id)
                    REFERENCES orders(id)
                    ON DELETE SET NULL
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS inventory_movements");
    }
};
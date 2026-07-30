<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS category_product (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                category_id BIGINT UNSIGNED NOT NULL,
                product_id BIGINT UNSIGNED NOT NULL,
                created_at DATETIME NULL,

                UNIQUE KEY category_product_unique (category_id, product_id),
                INDEX category_product_category_id_index (category_id),
                INDEX category_product_product_id_index (product_id),

                CONSTRAINT category_product_category_id_foreign
                    FOREIGN KEY (category_id)
                    REFERENCES categories(id)
                    ON DELETE CASCADE,

                CONSTRAINT category_product_product_id_foreign
                    FOREIGN KEY (product_id)
                    REFERENCES products(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS category_product");
    }
};
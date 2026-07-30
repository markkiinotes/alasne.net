<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS products (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                sku VARCHAR(100) NULL,
                description TEXT NULL,
                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                cost DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
                inventory_quantity INT NOT NULL DEFAULT 0,
                status VARCHAR(50) NOT NULL DEFAULT 'draft',
                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                UNIQUE KEY products_store_slug_unique (store_id, slug),
                INDEX products_store_id_index (store_id),
                INDEX products_status_index (status),

                CONSTRAINT products_store_id_foreign
                    FOREIGN KEY (store_id)
                    REFERENCES stores(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS products");
    }
};
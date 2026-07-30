<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS categories (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL,
                description TEXT NULL,
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                UNIQUE KEY categories_store_slug_unique (store_id, slug),
                INDEX categories_store_id_index (store_id),
                INDEX categories_status_index (status),

                CONSTRAINT categories_store_id_foreign
                    FOREIGN KEY (store_id)
                    REFERENCES stores(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS categories");
    }
};
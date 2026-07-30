<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS customers (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                first_name VARCHAR(100) NOT NULL,
                last_name VARCHAR(100) NOT NULL,
                email VARCHAR(255) NULL,
                phone VARCHAR(50) NULL,
                address_line_1 VARCHAR(255) NULL,
                address_line_2 VARCHAR(255) NULL,
                city VARCHAR(100) NULL,
                state VARCHAR(100) NULL,
                postal_code VARCHAR(50) NULL,
                country VARCHAR(100) NULL DEFAULT 'United States',
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                INDEX customers_store_id_index (store_id),
                INDEX customers_email_index (email),
                INDEX customers_status_index (status),

                CONSTRAINT customers_store_id_foreign
                    FOREIGN KEY (store_id)
                    REFERENCES stores(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS customers");
    }
};
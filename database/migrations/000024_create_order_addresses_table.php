<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS order_addresses (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                order_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(30) NOT NULL DEFAULT 'shipping',

                full_name VARCHAR(255) NOT NULL,
                company VARCHAR(255) NULL,

                address_line_1 VARCHAR(255) NOT NULL,
                address_line_2 VARCHAR(255) NULL,

                city VARCHAR(100) NOT NULL,
                state_region VARCHAR(100) NOT NULL,
                postal_code VARCHAR(30) NOT NULL,
                country_code CHAR(2) NOT NULL DEFAULT 'US',

                phone VARCHAR(50) NULL,

                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                UNIQUE KEY order_addresses_order_type_unique (
                    order_id,
                    type
                ),

                INDEX order_addresses_order_id_index (
                    order_id
                ),

                INDEX order_addresses_postal_code_index (
                    postal_code
                ),

                CONSTRAINT order_addresses_order_id_foreign
                    FOREIGN KEY (order_id)
                    REFERENCES orders(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec(
            "DROP TABLE IF EXISTS order_addresses"
        );
    }
};
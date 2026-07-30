<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS shipping_methods (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,

                name VARCHAR(150) NOT NULL,
                code VARCHAR(100) NOT NULL,
                description VARCHAR(255) NULL,

                price DECIMAL(10, 2) NOT NULL DEFAULT 0.00,

                estimated_days_min INT UNSIGNED NULL,
                estimated_days_max INT UNSIGNED NULL,

                is_active TINYINT(1) NOT NULL DEFAULT 1,
                sort_order INT NOT NULL DEFAULT 0,

                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                UNIQUE KEY shipping_methods_store_code_unique (
                    store_id,
                    code
                ),

                INDEX shipping_methods_store_id_index (
                    store_id
                ),

                INDEX shipping_methods_active_index (
                    is_active
                ),

                CONSTRAINT shipping_methods_store_id_foreign
                    FOREIGN KEY (store_id)
                    REFERENCES stores(id)
                    ON DELETE CASCADE
            )
        ");

        $this->db->exec("
            ALTER TABLE orders
                ADD COLUMN IF NOT EXISTS shipping_method_id
                    BIGINT UNSIGNED NULL
                    AFTER customer_id,

                ADD COLUMN IF NOT EXISTS shipping_method_name
                    VARCHAR(150) NULL
                    AFTER shipping_method_id,

                ADD COLUMN IF NOT EXISTS shipping_method_code
                    VARCHAR(100) NULL
                    AFTER shipping_method_name,

                ADD COLUMN IF NOT EXISTS shipping_estimated_days_min
                    INT UNSIGNED NULL
                    AFTER shipping_method_code,

                ADD COLUMN IF NOT EXISTS shipping_estimated_days_max
                    INT UNSIGNED NULL
                    AFTER shipping_estimated_days_min
        ");

        /*
         * Seed Standard Shipping for existing stores.
         */
        $this->db->exec("
            INSERT INTO shipping_methods (
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            )
            SELECT
                s.id,
                'Standard Shipping',
                'standard',
                'Standard delivery service',
                7.95,
                5,
                7,
                1,
                10,
                NOW(),
                NOW()
            FROM stores s
            WHERE NOT EXISTS (
                SELECT 1
                FROM shipping_methods sm
                WHERE sm.store_id = s.id
                AND sm.code = 'standard'
            )
        ");

        /*
         * Seed Express Shipping for existing stores.
         */
        $this->db->exec("
            INSERT INTO shipping_methods (
                store_id,
                name,
                code,
                description,
                price,
                estimated_days_min,
                estimated_days_max,
                is_active,
                sort_order,
                created_at,
                updated_at
            )
            SELECT
                s.id,
                'Express Shipping',
                'express',
                'Faster delivery service',
                19.95,
                2,
                3,
                1,
                20,
                NOW(),
                NOW()
            FROM stores s
            WHERE NOT EXISTS (
                SELECT 1
                FROM shipping_methods sm
                WHERE sm.store_id = s.id
                AND sm.code = 'express'
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("
            ALTER TABLE orders
                DROP COLUMN IF EXISTS shipping_estimated_days_max,
                DROP COLUMN IF EXISTS shipping_estimated_days_min,
                DROP COLUMN IF EXISTS shipping_method_code,
                DROP COLUMN IF EXISTS shipping_method_name,
                DROP COLUMN IF EXISTS shipping_method_id
        ");

        $this->db->exec(
            "DROP TABLE IF EXISTS shipping_methods"
        );
    }
};
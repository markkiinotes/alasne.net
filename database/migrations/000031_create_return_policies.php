<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_policies (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                return_window_days SMALLINT UNSIGNED
                    NOT NULL DEFAULT 30,
                require_fulfilled_status TINYINT(1)
                    NOT NULL DEFAULT 0,
                allow_changed_mind TINYINT(1)
                    NOT NULL DEFAULT 1,
                customer_pays_return_shipping TINYINT(1)
                    NOT NULL DEFAULT 1,
                auto_approve_customer_requests TINYINT(1)
                    NOT NULL DEFAULT 0,
                policy_title VARCHAR(191) NOT NULL
                    DEFAULT '30-Day Return Policy',
                policy_text TEXT NULL,
                return_instructions TEXT NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_return_policies_store (
                    store_id
                ),
                KEY idx_return_policies_enabled (
                    is_enabled
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            INSERT INTO return_policies (
                store_id,
                is_enabled,
                return_window_days,
                require_fulfilled_status,
                allow_changed_mind,
                customer_pays_return_shipping,
                auto_approve_customer_requests,
                policy_title,
                policy_text,
                return_instructions,
                created_at,
                updated_at
            )
            SELECT
                s.id,
                1,
                30,
                0,
                1,
                1,
                0,
                '30-Day Return Policy',
                'Eligible merchandise may be requested for return within 30 days of the order date.',
                'Submit a return request and wait for approval before sending merchandise back.',
                NOW(),
                NOW()
            FROM stores s
            LEFT JOIN return_policies rp
                ON rp.store_id = s.id
            WHERE rp.id IS NULL
        ");
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS return_policies'
        );
    }
};

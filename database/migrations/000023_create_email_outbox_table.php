<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS email_outbox (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

                store_id BIGINT UNSIGNED NULL,
                order_id BIGINT UNSIGNED NULL,

                to_email VARCHAR(255) NOT NULL,
                to_name VARCHAR(255) NULL,

                subject VARCHAR(255) NOT NULL,
                body_html LONGTEXT NOT NULL,
                body_text LONGTEXT NULL,

                status VARCHAR(50) NOT NULL DEFAULT 'pending',
                attempts INT UNSIGNED NOT NULL DEFAULT 0,
                last_error TEXT NULL,
                sent_at DATETIME NULL,

                created_at DATETIME NULL,
                updated_at DATETIME NULL,

                INDEX email_outbox_store_id_index (store_id),
                INDEX email_outbox_order_id_index (order_id),
                INDEX email_outbox_status_index (status),
                INDEX email_outbox_to_email_index (to_email),

                CONSTRAINT email_outbox_store_id_foreign
                    FOREIGN KEY (store_id)
                    REFERENCES stores(id)
                    ON DELETE SET NULL,

                CONSTRAINT email_outbox_order_id_foreign
                    FOREIGN KEY (order_id)
                    REFERENCES orders(id)
                    ON DELETE SET NULL
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS email_outbox");
    }
};
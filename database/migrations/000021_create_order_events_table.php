<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS order_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                order_id BIGINT UNSIGNED NOT NULL,
                type VARCHAR(100) NOT NULL,
                title VARCHAR(255) NOT NULL,
                description TEXT NULL,
                old_value VARCHAR(255) NULL,
                new_value VARCHAR(255) NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NULL,

                INDEX order_events_order_id_index (order_id),
                INDEX order_events_type_index (type),
                INDEX order_events_is_public_index (is_public),

                CONSTRAINT order_events_order_id_foreign
                    FOREIGN KEY (order_id)
                    REFERENCES orders(id)
                    ON DELETE CASCADE
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS order_events");
    }
};
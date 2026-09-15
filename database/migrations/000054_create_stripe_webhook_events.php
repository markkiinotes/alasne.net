<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS stripe_webhook_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                event_id VARCHAR(255) NOT NULL,
                event_type VARCHAR(191) NOT NULL,
                stripe_object_id VARCHAR(255) NULL,
                stripe_account_id VARCHAR(255) NULL,
                livemode TINYINT(1) NOT NULL DEFAULT 0,
                api_version VARCHAR(80) NULL,
                status VARCHAR(40) NOT NULL DEFAULT 'processing',
                attempts INT UNSIGNED NOT NULL DEFAULT 1,
                last_error TEXT NULL,
                received_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_stripe_webhook_event_id (event_id),
                KEY idx_stripe_webhook_type (
                    event_type,
                    received_at
                ),
                KEY idx_stripe_webhook_status (
                    status,
                    updated_at
                ),
                KEY idx_stripe_webhook_object (
                    stripe_object_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS stripe_webhook_events'
        );
    }
};

<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_shipments (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                order_id BIGINT UNSIGNED NOT NULL,
                rma_number VARCHAR(60) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'label_ready',
                carrier_code VARCHAR(30) NOT NULL,
                carrier_name VARCHAR(100) NOT NULL,
                service_name VARCHAR(100) NULL,
                tracking_number VARCHAR(191) NOT NULL,
                tracking_url VARCHAR(1000) NULL,
                label_source VARCHAR(30) NOT NULL DEFAULT 'manual',
                label_cost DECIMAL(12,2) NOT NULL DEFAULT 0.00,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                from_name VARCHAR(191) NULL,
                from_address_snapshot TEXT NULL,
                to_name VARCHAR(191) NULL,
                to_address_snapshot TEXT NOT NULL,
                public_note VARCHAR(1000) NULL,
                shipped_at DATETIME NULL,
                delivered_at DATETIME NULL,
                last_event_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_return_shipments_return (return_id),
                UNIQUE KEY uq_return_shipments_tracking (
                    carrier_code,
                    tracking_number
                ),
                KEY idx_return_shipments_status (
                    status,
                    updated_at
                ),
                KEY idx_return_shipments_rma (rma_number),
                KEY idx_return_shipments_order (order_id)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_shipment_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_shipment_id BIGINT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL,
                title VARCHAR(191) NOT NULL,
                description VARCHAR(1000) NULL,
                location VARCHAR(191) NULL,
                event_at DATETIME NOT NULL,
                is_public TINYINT(1) NOT NULL DEFAULT 1,
                created_at DATETIME NOT NULL,
                KEY idx_return_shipment_events_shipment (
                    return_shipment_id,
                    event_at,
                    id
                ),
                KEY idx_return_shipment_events_status (status)
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->exec(
            'DROP TABLE IF EXISTS return_shipment_events'
        );

        $this->db->exec(
            'DROP TABLE IF EXISTS return_shipments'
        );
    }
};

<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS carrier_integrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                provider VARCHAR(40) NOT NULL DEFAULT 'easypost',
                is_enabled TINYINT(1) NOT NULL DEFAULT 0,
                mode VARCHAR(20) NOT NULL DEFAULT 'test',
                api_key_env VARCHAR(191) NOT NULL DEFAULT 'EASYPOST_API_KEY',
                webhook_secret_env VARCHAR(191) NOT NULL DEFAULT 'EASYPOST_WEBHOOK_SECRET',
                return_name VARCHAR(191) NULL,
                return_company VARCHAR(191) NULL,
                return_street1 VARCHAR(191) NULL,
                return_street2 VARCHAR(191) NULL,
                return_city VARCHAR(120) NULL,
                return_state VARCHAR(120) NULL,
                return_postal_code VARCHAR(40) NULL,
                return_country CHAR(2) NOT NULL DEFAULT 'US',
                return_phone VARCHAR(60) NULL,
                return_email VARCHAR(191) NULL,
                default_length DECIMAL(10,2) NOT NULL DEFAULT 10.00,
                default_width DECIMAL(10,2) NOT NULL DEFAULT 8.00,
                default_height DECIMAL(10,2) NOT NULL DEFAULT 4.00,
                default_weight_oz DECIMAL(10,2) NOT NULL DEFAULT 16.00,
                label_format VARCHAR(10) NOT NULL DEFAULT 'PDF',
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_carrier_integrations_store_provider (store_id, provider),
                KEY idx_carrier_integrations_enabled (provider, is_enabled)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS return_shipping_quotes (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                return_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                provider VARCHAR(40) NOT NULL,
                provider_shipment_id VARCHAR(191) NOT NULL,
                provider_rate_id VARCHAR(191) NOT NULL,
                carrier VARCHAR(100) NOT NULL,
                service VARCHAR(100) NOT NULL,
                rate DECIMAL(12,2) NOT NULL,
                currency CHAR(3) NOT NULL DEFAULT 'USD',
                delivery_days SMALLINT UNSIGNED NULL,
                delivery_date DATETIME NULL,
                delivery_date_guaranteed TINYINT(1) NOT NULL DEFAULT 0,
                expires_at DATETIME NOT NULL,
                purchase_status VARCHAR(20) NOT NULL DEFAULT 'available',
                purchase_started_at DATETIME NULL,
                purchase_error VARCHAR(1000) NULL,
                purchased_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_return_shipping_quotes_return (return_id, expires_at),
                UNIQUE KEY uq_return_shipping_quotes_provider_rate (provider, provider_rate_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS carrier_webhook_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                provider VARCHAR(40) NOT NULL,
                provider_event_id VARCHAR(191) NOT NULL,
                event_type VARCHAR(100) NOT NULL,
                provider_object_id VARCHAR(191) NULL,
                processing_status VARCHAR(30) NOT NULL DEFAULT 'received',
                response_message VARCHAR(1000) NULL,
                payload LONGTEXT NOT NULL,
                received_at DATETIME NOT NULL,
                processed_at DATETIME NULL,
                UNIQUE KEY uq_carrier_webhook_event (provider, provider_event_id),
                KEY idx_carrier_webhook_status (processing_status, received_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->addShipmentColumn('provider', "VARCHAR(40) NULL AFTER label_source");
        $this->addShipmentColumn('provider_mode', "VARCHAR(20) NULL AFTER provider");
        $this->addShipmentColumn('provider_shipment_id', "VARCHAR(191) NULL AFTER provider_mode");
        $this->addShipmentColumn('provider_rate_id', "VARCHAR(191) NULL AFTER provider_shipment_id");
        $this->addShipmentColumn('provider_tracker_id', "VARCHAR(191) NULL AFTER provider_rate_id");
        $this->addShipmentColumn('provider_label_url', "VARCHAR(1000) NULL AFTER provider_tracker_id");
        $this->addShipmentColumn('provider_label_pdf_url', "VARCHAR(1000) NULL AFTER provider_label_url");
        $this->addShipmentColumn('provider_label_zpl_url', "VARCHAR(1000) NULL AFTER provider_label_pdf_url");
        $this->addShipmentColumn('provider_payload', "LONGTEXT NULL AFTER provider_label_zpl_url");
        $this->addShipmentColumn('purchased_at', "DATETIME NULL AFTER provider_payload");

        if (! $this->indexExists('return_shipments', 'idx_return_shipments_provider_tracker')) {
            $this->db->exec("ALTER TABLE return_shipments ADD KEY idx_return_shipments_provider_tracker (provider, provider_tracker_id)");
        }
    }

    public function down(): void
    {
        if ($this->indexExists('return_shipments', 'idx_return_shipments_provider_tracker')) {
            $this->db->exec("ALTER TABLE return_shipments DROP INDEX idx_return_shipments_provider_tracker");
        }
        foreach (['purchased_at','provider_payload','provider_label_zpl_url','provider_label_pdf_url','provider_label_url','provider_tracker_id','provider_rate_id','provider_shipment_id','provider_mode','provider'] as $column) {
            if ($this->columnExists('return_shipments', $column)) {
                $this->db->exec('ALTER TABLE return_shipments DROP COLUMN ' . $column);
            }
        }
        $this->db->exec('DROP TABLE IF EXISTS carrier_webhook_events');
        $this->db->exec('DROP TABLE IF EXISTS return_shipping_quotes');
        $this->db->exec('DROP TABLE IF EXISTS carrier_integrations');
    }

    private function addShipmentColumn(string $column, string $definition): void
    {
        if (! $this->columnExists('return_shipments', $column)) {
            $this->db->exec('ALTER TABLE return_shipments ADD COLUMN ' . $column . ' ' . $definition);
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND COLUMN_NAME = :column_name");
        $stmt->execute(['table_name'=>$table,'column_name'=>$column]);
        return (int) $stmt->fetchColumn() > 0;
    }

    private function indexExists(string $table, string $index): bool
    {
        $stmt = $this->db->prepare("SELECT COUNT(*) FROM information_schema.STATISTICS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :table_name AND INDEX_NAME = :index_name");
        $stmt->execute(['table_name'=>$table,'index_name'=>$index]);
        return (int) $stmt->fetchColumn() > 0;
    }
};

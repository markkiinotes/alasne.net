<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_integrations (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                supplier_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                provider_code VARCHAR(80) NOT NULL DEFAULT 'manual_direct',
                status VARCHAR(30) NOT NULL DEFAULT 'active',
                mode VARCHAR(20) NOT NULL DEFAULT 'test',
                auto_prepare_orders TINYINT(1) NOT NULL DEFAULT 1,
                auto_submit_orders TINYINT(1) NOT NULL DEFAULT 0,
                purchase_order_email VARCHAR(191) NULL,
                catalog_feed_url VARCHAR(1000) NULL,
                endpoint_url VARCHAR(1000) NULL,
                api_key_env VARCHAR(191) NULL,
                api_secret_env VARCHAR(191) NULL,
                account_id_env VARCHAR(191) NULL,
                default_order_notes TEXT NULL,
                last_catalog_sync_at DATETIME NULL,
                last_inventory_sync_at DATETIME NULL,
                last_connection_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_supplier_integrations_supplier (
                    supplier_id
                ),
                KEY idx_supplier_integrations_store (
                    store_id,
                    status,
                    provider_code
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_sync_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                supplier_integration_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                store_id BIGINT UNSIGNED NOT NULL,
                sync_type VARCHAR(30) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'running',
                source_name VARCHAR(191) NULL,
                source_file_name VARCHAR(255) NULL,
                rows_received INT UNSIGNED NOT NULL DEFAULT 0,
                rows_processed INT UNSIGNED NOT NULL DEFAULT 0,
                rows_created INT UNSIGNED NOT NULL DEFAULT 0,
                rows_updated INT UNSIGNED NOT NULL DEFAULT 0,
                rows_skipped INT UNSIGNED NOT NULL DEFAULT 0,
                rows_failed INT UNSIGNED NOT NULL DEFAULT 0,
                error_message VARCHAR(1000) NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_supplier_sync_runs_supplier (
                    supplier_id,
                    sync_type,
                    created_at
                ),
                KEY idx_supplier_sync_runs_status (
                    status,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_sync_errors (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                sync_run_id BIGINT UNSIGNED NOT NULL,
                row_number INT UNSIGNED NULL,
                supplier_sku VARCHAR(191) NULL,
                error_code VARCHAR(80) NOT NULL,
                message VARCHAR(1000) NOT NULL,
                raw_data LONGTEXT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_supplier_sync_errors_run (
                    sync_run_id,
                    row_number,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_order_submissions (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                purchase_order_id BIGINT UNSIGNED NOT NULL,
                supplier_id BIGINT UNSIGNED NOT NULL,
                supplier_integration_id BIGINT UNSIGNED NULL,
                provider_code VARCHAR(80) NOT NULL,
                channel VARCHAR(30) NOT NULL DEFAULT 'manual',
                status VARCHAR(30) NOT NULL DEFAULT 'prepared',
                idempotency_key VARCHAR(191) NOT NULL,
                attempt_count INT UNSIGNED NOT NULL DEFAULT 0,
                export_file_name VARCHAR(255) NULL,
                payload_json LONGTEXT NOT NULL,
                response_json LONGTEXT NULL,
                external_order_id VARCHAR(191) NULL,
                error_message VARCHAR(1000) NULL,
                prepared_at DATETIME NOT NULL,
                submitted_at DATETIME NULL,
                completed_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_supplier_order_submission_key (
                    idempotency_key
                ),
                UNIQUE KEY uq_supplier_order_submission_po (
                    purchase_order_id
                ),
                KEY idx_supplier_order_submissions_status (
                    status,
                    updated_at
                ),
                KEY idx_supplier_order_submissions_supplier (
                    supplier_id,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS supplier_submission_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                supplier_order_submission_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(60) NOT NULL,
                title VARCHAR(191) NOT NULL,
                description VARCHAR(1000) NULL,
                old_value VARCHAR(191) NULL,
                new_value VARCHAR(191) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_supplier_submission_events_submission (
                    supplier_order_submission_id,
                    created_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumn(
            'purchase_orders',
            'submission_status',
            "VARCHAR(30) NOT NULL DEFAULT 'not_prepared' AFTER status"
        );
        $this->addColumn(
            'purchase_orders',
            'supplier_order_submission_id',
            "BIGINT UNSIGNED NULL AFTER submission_status"
        );
        $this->addColumn(
            'purchase_orders',
            'provider_code',
            "VARCHAR(80) NULL AFTER supplier_order_submission_id"
        );
        $this->addColumn(
            'purchase_orders',
            'provider_order_id',
            "VARCHAR(191) NULL AFTER provider_code"
        );
        $this->addColumn(
            'purchase_orders',
            'last_submission_at',
            "DATETIME NULL AFTER provider_order_id"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_name',
            "VARCHAR(191) NULL AFTER last_submission_at"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_email',
            "VARCHAR(191) NULL AFTER ship_to_name"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_phone',
            "VARCHAR(80) NULL AFTER ship_to_email"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_address_line_1',
            "VARCHAR(255) NULL AFTER ship_to_phone"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_address_line_2',
            "VARCHAR(255) NULL AFTER ship_to_address_line_1"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_city',
            "VARCHAR(120) NULL AFTER ship_to_address_line_2"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_state',
            "VARCHAR(120) NULL AFTER ship_to_city"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_postal_code',
            "VARCHAR(40) NULL AFTER ship_to_state"
        );
        $this->addColumn(
            'purchase_orders',
            'ship_to_country',
            "VARCHAR(120) NULL AFTER ship_to_postal_code"
        );

        $this->db->exec("
            UPDATE purchase_orders po
            INNER JOIN orders o
                ON o.id = po.order_id
            LEFT JOIN customers c
                ON c.id = o.customer_id
            SET
                po.ship_to_name = COALESCE(
                    NULLIF(TRIM(po.ship_to_name), ''),
                    NULLIF(TRIM(CONCAT(
                        COALESCE(c.first_name, ''),
                        ' ',
                        COALESCE(c.last_name, '')
                    )), '')
                ),
                po.ship_to_email = COALESCE(
                    NULLIF(TRIM(po.ship_to_email), ''),
                    c.email
                ),
                po.ship_to_phone = COALESCE(
                    NULLIF(TRIM(po.ship_to_phone), ''),
                    c.phone
                ),
                po.ship_to_address_line_1 = COALESCE(
                    NULLIF(TRIM(po.ship_to_address_line_1), ''),
                    c.address_line_1
                ),
                po.ship_to_address_line_2 = COALESCE(
                    NULLIF(TRIM(po.ship_to_address_line_2), ''),
                    c.address_line_2
                ),
                po.ship_to_city = COALESCE(
                    NULLIF(TRIM(po.ship_to_city), ''),
                    c.city
                ),
                po.ship_to_state = COALESCE(
                    NULLIF(TRIM(po.ship_to_state), ''),
                    c.state
                ),
                po.ship_to_postal_code = COALESCE(
                    NULLIF(TRIM(po.ship_to_postal_code), ''),
                    c.postal_code
                ),
                po.ship_to_country = COALESCE(
                    NULLIF(TRIM(po.ship_to_country), ''),
                    c.country
                )
        " );

        $this->addColumn(
            'supplier_products',
            'provider_product_id',
            "VARCHAR(191) NULL AFTER supplier_sku"
        );
        $this->addColumn(
            'supplier_products',
            'source_hash',
            "CHAR(64) NULL AFTER last_synced_at"
        );
        $this->addColumn(
            'supplier_products',
            'source_updated_at',
            "DATETIME NULL AFTER source_hash"
        );
        $this->addColumn(
            'supplier_products',
            'last_cost_synced_at',
            "DATETIME NULL AFTER source_updated_at"
        );
        $this->addColumn(
            'supplier_products',
            'last_stock_synced_at',
            "DATETIME NULL AFTER last_cost_synced_at"
        );

        $this->db->exec("
            INSERT INTO supplier_integrations (
                supplier_id,
                store_id,
                provider_code,
                status,
                mode,
                auto_prepare_orders,
                auto_submit_orders,
                purchase_order_email,
                default_order_notes,
                created_at,
                updated_at
            )
            SELECT
                sup.id,
                sup.store_id,
                CASE
                    WHEN sup.supplier_type IN (
                        'manual',
                        'wholesaler',
                        'other'
                    ) THEN 'manual_direct'
                    ELSE 'csv_feed'
                END,
                CASE
                    WHEN sup.status = 'active'
                    THEN 'active'
                    ELSE 'inactive'
                END,
                'test',
                1,
                0,
                sup.email,
                sup.notes,
                NOW(),
                NOW()
            FROM suppliers sup
            LEFT JOIN supplier_integrations si
                ON si.supplier_id = sup.id
            WHERE si.id IS NULL
        ");
    }

    public function down(): void
    {
        foreach ([
            'last_stock_synced_at',
            'last_cost_synced_at',
            'source_updated_at',
            'source_hash',
            'provider_product_id',
        ] as $column) {
            $this->dropColumnIfExists(
                'supplier_products',
                $column
            );
        }

        foreach ([
            'ship_to_country',
            'ship_to_postal_code',
            'ship_to_state',
            'ship_to_city',
            'ship_to_address_line_2',
            'ship_to_address_line_1',
            'ship_to_phone',
            'ship_to_email',
            'ship_to_name',
            'last_submission_at',
            'provider_order_id',
            'provider_code',
            'supplier_order_submission_id',
            'submission_status',
        ] as $column) {
            $this->dropColumnIfExists(
                'purchase_orders',
                $column
            );
        }

        foreach ([
            'supplier_submission_events',
            'supplier_order_submissions',
            'supplier_sync_errors',
            'supplier_sync_runs',
            'supplier_integrations',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function addColumn(
        string $table,
        string $column,
        string $definition
    ): void {
        if (! $this->columnExists($table, $column)) {
            $this->db->exec(
                "ALTER TABLE `{$table}`
                 ADD COLUMN `{$column}` {$definition}"
            );
        }
    }

    private function dropColumnIfExists(
        string $table,
        string $column
    ): void {
        if ($this->columnExists($table, $column)) {
            $this->db->exec(
                "ALTER TABLE `{$table}`
                 DROP COLUMN `{$column}`"
            );
        }
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");
        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

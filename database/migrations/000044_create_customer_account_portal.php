<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS customer_portal_access_tokens (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                email VARCHAR(191) NOT NULL,
                token_hash CHAR(64) NOT NULL,
                purpose VARCHAR(40) NOT NULL DEFAULT 'login',
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(500) NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                UNIQUE KEY uq_customer_portal_token_hash (token_hash),
                KEY idx_customer_portal_access_customer (
                    store_id,
                    customer_id,
                    expires_at
                ),
                KEY idx_customer_portal_access_email (
                    store_id,
                    email,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS customer_portal_events (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                store_id BIGINT UNSIGNED NOT NULL,
                customer_id BIGINT UNSIGNED NOT NULL,
                event_type VARCHAR(80) NOT NULL,
                title VARCHAR(191) NOT NULL,
                description VARCHAR(1000) NULL,
                ip_address VARCHAR(64) NULL,
                user_agent VARCHAR(500) NULL,
                created_at DATETIME NOT NULL,
                KEY idx_customer_portal_events_customer (
                    store_id,
                    customer_id,
                    created_at
                ),
                KEY idx_customer_portal_events_type (
                    event_type,
                    created_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->addColumn(
            'customers',
            'portal_last_login_at',
            "DATETIME NULL AFTER status"
        );

        $this->addColumn(
            'customers',
            'portal_login_count',
            "INT UNSIGNED NOT NULL DEFAULT 0 AFTER portal_last_login_at"
        );

        $this->addColumn(
            'customers',
            'portal_profile_updated_at',
            "DATETIME NULL AFTER portal_login_count"
        );
    }

    public function down(): void
    {
        foreach ([
            'portal_profile_updated_at',
            'portal_login_count',
            'portal_last_login_at',
        ] as $column) {
            $this->dropColumnIfExists(
                'customers',
                $column
            );
        }

        foreach ([
            'customer_portal_events',
            'customer_portal_access_tokens',
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

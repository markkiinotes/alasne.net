<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists(
            'returns',
            'request_source'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD COLUMN request_source VARCHAR(30)
                    NOT NULL DEFAULT 'admin'
                AFTER status
            ");
        }

        if (! $this->indexExists(
            'returns',
            'idx_returns_request_source'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                ADD KEY idx_returns_request_source (
                    request_source,
                    created_at
                )
            ");
        }
    }

    public function down(): void
    {
        if ($this->indexExists(
            'returns',
            'idx_returns_request_source'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                DROP INDEX idx_returns_request_source
            ");
        }

        if ($this->columnExists(
            'returns',
            'request_source'
        )) {
            $this->db->exec("
                ALTER TABLE returns
                DROP COLUMN request_source
            ");
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

    private function indexExists(
        string $table,
        string $index
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND INDEX_NAME = :index_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'index_name' => $index,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

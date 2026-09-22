<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('ai_runs', 'context_store_id')) {
            $this->db->exec("
                ALTER TABLE ai_runs
                ADD COLUMN context_store_id BIGINT UNSIGNED NULL
                AFTER context_length
            ");
        }

        if (! $this->columnExists('ai_runs', 'context_date_from')) {
            $this->db->exec("
                ALTER TABLE ai_runs
                ADD COLUMN context_date_from DATE NULL
                AFTER context_store_id
            ");
        }

        if (! $this->columnExists('ai_runs', 'context_date_to')) {
            $this->db->exec("
                ALTER TABLE ai_runs
                ADD COLUMN context_date_to DATE NULL
                AFTER context_date_from
            ");
        }
    }

    public function down(): void
    {
        // Preserve immutable run-scope audit metadata.
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");
        $stmt->execute([$table, $column]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

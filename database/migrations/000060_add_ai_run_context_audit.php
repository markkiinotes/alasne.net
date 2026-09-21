<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists(
            'ai_runs',
            'context_type'
        )) {
            $this->db->exec("
                ALTER TABLE ai_runs
                ADD COLUMN context_type VARCHAR(80) NULL
                AFTER prompt_length
            ");
        }

        if (! $this->columnExists(
            'ai_runs',
            'context_sha256'
        )) {
            $this->db->exec("
                ALTER TABLE ai_runs
                ADD COLUMN context_sha256 CHAR(64) NULL
                AFTER context_type
            ");
        }

        if (! $this->columnExists(
            'ai_runs',
            'context_length'
        )) {
            $this->db->exec("
                ALTER TABLE ai_runs
                ADD COLUMN context_length INT UNSIGNED NOT NULL DEFAULT 0
                AFTER context_sha256
            ");
        }
    }

    public function down(): void
    {
        /*
         * AI run audit metadata is intentionally preserved on rollback.
         * Removing context hashes/type/length would weaken historical
         * traceability, so this migration is non-destructive.
         */
    }

    private function columnExists(
        string $table,
        string $column
    ): bool {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
              AND COLUMN_NAME = ?
        ");

        $stmt->execute([
            $table,
            $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

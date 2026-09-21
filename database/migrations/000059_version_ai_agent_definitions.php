<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists(
            'ai_agent_versions',
            'slug'
        )) {
            $this->db->exec("
                ALTER TABLE ai_agent_versions
                ADD COLUMN slug VARCHAR(150) NULL
                AFTER name
            ");
        }

        if (! $this->columnExists(
            'ai_agent_versions',
            'status'
        )) {
            $this->db->exec("
                ALTER TABLE ai_agent_versions
                ADD COLUMN status VARCHAR(30) NULL
                AFTER system_instructions
            ");
        }

        $this->db->exec("
            INSERT INTO ai_agent_versions (
                agent_id,
                version_number,
                name,
                slug,
                description,
                system_instructions,
                status,
                model_override,
                max_output_tokens,
                capabilities_json,
                changed_by_user_id,
                change_note,
                created_at
            )
            SELECT
                a.id,
                1,
                a.name,
                a.slug,
                a.description,
                a.system_instructions,
                a.status,
                a.model_override,
                a.max_output_tokens,
                a.capabilities_json,
                COALESCE(
                    a.updated_by_user_id,
                    a.created_by_user_id
                ),
                'Initial agent snapshot',
                a.created_at
            FROM ai_agents a
            WHERE NOT EXISTS (
                SELECT 1
                FROM ai_agent_versions v
                WHERE v.agent_id = a.id
            )
        ");
    }

    public function down(): void
    {
        /*
         * Version rows are intentionally preserved on rollback.
         * Removing immutable history would be more destructive
         * than leaving the added snapshot columns in place.
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

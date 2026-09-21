<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class AiAgentRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT
                a.*,
                creator.name AS created_by_name,
                updater.name AS updated_by_name,
                (
                    SELECT COUNT(*)
                    FROM ai_agent_versions v
                    WHERE v.agent_id = a.id
                ) AS version_count
            FROM ai_agents a
            LEFT JOIN users creator
                ON creator.id = a.created_by_user_id
            LEFT JOIN users updater
                ON updater.id = a.updated_by_user_id
            ORDER BY
                CASE a.status
                    WHEN 'active' THEN 0
                    WHEN 'draft' THEN 1
                    ELSE 2
                END,
                a.name,
                a.id
        ");

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ai_agents
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }
}

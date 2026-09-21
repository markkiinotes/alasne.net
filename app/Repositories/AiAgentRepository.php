<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

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
            WHERE a.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $row = $stmt->fetch();

        return $row ?: null;
    }

    public function slugExists(
        string $slug,
        ?int $exceptId = null
    ): bool {
        $sql = "
            SELECT COUNT(*)
            FROM ai_agents
            WHERE slug = :slug
        ";

        $params = [
            'slug' => $slug,
        ];

        if ($exceptId !== null) {
            $sql .= " AND id <> :except_id";
            $params['except_id'] = $exceptId;
        }

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn() > 0;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function versions(int $agentId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                v.*,
                u.name AS changed_by_name
            FROM ai_agent_versions v
            LEFT JOIN users u
                ON u.id = v.changed_by_user_id
            WHERE v.agent_id = :agent_id
            ORDER BY v.version_number DESC
        ");

        $stmt->execute([
            'agent_id' => $agentId,
        ]);

        return $stmt->fetchAll();
    }

    /**
     * @param array<string, mixed> $data
     * @return array{agent_id:int,version_number:int}
     */
    public function create(
        array $data,
        ?int $userId
    ): array {
        $this->db->beginTransaction();

        try {
            $stmt = $this->db->prepare("
                INSERT INTO ai_agents (
                    name,
                    slug,
                    description,
                    system_instructions,
                    status,
                    model_override,
                    max_output_tokens,
                    capabilities_json,
                    created_by_user_id,
                    updated_by_user_id,
                    created_at,
                    updated_at
                ) VALUES (
                    :name,
                    :slug,
                    :description,
                    :system_instructions,
                    'draft',
                    :model_override,
                    :max_output_tokens,
                    :capabilities_json,
                    :created_by_user_id,
                    :updated_by_user_id,
                    NOW(),
                    NOW()
                )
            ");

            $stmt->execute([
                'name' => $data['name'],
                'slug' => $data['slug'],
                'description' => $data['description'],
                'system_instructions' =>
                    $data['system_instructions'],
                'model_override' =>
                    $data['model_override'],
                'max_output_tokens' =>
                    $data['max_output_tokens'],
                'capabilities_json' =>
                    $data['capabilities_json'],
                'created_by_user_id' => $userId,
                'updated_by_user_id' => $userId,
            ]);

            $agentId =
                (int) $this->db->lastInsertId();

            $agent = $this->lockedAgent(
                $agentId
            );

            $this->insertVersion(
                $agent,
                1,
                $userId,
                'Created agent'
            );

            $this->db->commit();

            return [
                'agent_id' => $agentId,
                'version_number' => 1,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @param array<string, mixed> $data
     * @return array{changed:bool,version_number:int}
     */
    public function updateDefinition(
        int $agentId,
        array $data,
        ?int $userId,
        string $changeNote
    ): array {
        $this->db->beginTransaction();

        try {
            $current = $this->lockedAgent(
                $agentId
            );

            $next = $current;

            foreach (
                [
                    'name',
                    'description',
                    'system_instructions',
                    'model_override',
                    'max_output_tokens',
                    'capabilities_json',
                ] as $field
            ) {
                $next[$field] =
                    $data[$field];
            }

            if (
                $this->definitionSignature(
                    $current
                )
                === $this->definitionSignature(
                    $next
                )
            ) {
                $version =
                    $this->latestVersionNumber(
                        $agentId
                    );

                $this->db->commit();

                return [
                    'changed' => false,
                    'version_number' =>
                        $version,
                ];
            }

            $stmt = $this->db->prepare("
                UPDATE ai_agents
                SET
                    name = :name,
                    description = :description,
                    system_instructions =
                        :system_instructions,
                    model_override =
                        :model_override,
                    max_output_tokens =
                        :max_output_tokens,
                    capabilities_json =
                        :capabilities_json,
                    updated_by_user_id =
                        :updated_by_user_id,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $agentId,
                'name' => $next['name'],
                'description' =>
                    $next['description'],
                'system_instructions' =>
                    $next['system_instructions'],
                'model_override' =>
                    $next['model_override'],
                'max_output_tokens' =>
                    $next['max_output_tokens'],
                'capabilities_json' =>
                    $next['capabilities_json'],
                'updated_by_user_id' =>
                    $userId,
            ]);

            $updated = $this->lockedAgent(
                $agentId
            );

            $version =
                $this->latestVersionNumber(
                    $agentId
                ) + 1;

            $this->insertVersion(
                $updated,
                $version,
                $userId,
                $changeNote
            );

            $this->db->commit();

            return [
                'changed' => true,
                'version_number' =>
                    $version,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array{changed:bool,version_number:int}
     */
    public function changeStatus(
        int $agentId,
        string $status,
        ?int $userId,
        string $changeNote
    ): array {
        $this->db->beginTransaction();

        try {
            $current = $this->lockedAgent(
                $agentId
            );

            if (
                strtolower(
                    (string) $current['status']
                )
                === strtolower($status)
            ) {
                $version =
                    $this->latestVersionNumber(
                        $agentId
                    );

                $this->db->commit();

                return [
                    'changed' => false,
                    'version_number' =>
                        $version,
                ];
            }

            $stmt = $this->db->prepare("
                UPDATE ai_agents
                SET
                    status = :status,
                    updated_by_user_id =
                        :updated_by_user_id,
                    updated_at = NOW()
                WHERE id = :id
            ");

            $stmt->execute([
                'id' => $agentId,
                'status' => $status,
                'updated_by_user_id' =>
                    $userId,
            ]);

            $updated = $this->lockedAgent(
                $agentId
            );

            $version =
                $this->latestVersionNumber(
                    $agentId
                ) + 1;

            $this->insertVersion(
                $updated,
                $version,
                $userId,
                $changeNote
            );

            $this->db->commit();

            return [
                'changed' => true,
                'version_number' =>
                    $version,
            ];
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function lockedAgent(
        int $agentId
    ): array {
        $stmt = $this->db->prepare("
            SELECT *
            FROM ai_agents
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'id' => $agentId,
        ]);

        $row = $stmt->fetch();

        if (! $row) {
            throw new RuntimeException(
                'AI agent was not found.'
            );
        }

        return $row;
    }

    private function latestVersionNumber(
        int $agentId
    ): int {
        $stmt = $this->db->prepare("
            SELECT COALESCE(
                MAX(version_number),
                0
            )
            FROM ai_agent_versions
            WHERE agent_id = :agent_id
        ");

        $stmt->execute([
            'agent_id' => $agentId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function insertVersion(
        array $agent,
        int $versionNumber,
        ?int $userId,
        string $changeNote
    ): void {
        $stmt = $this->db->prepare("
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
            ) VALUES (
                :agent_id,
                :version_number,
                :name,
                :slug,
                :description,
                :system_instructions,
                :status,
                :model_override,
                :max_output_tokens,
                :capabilities_json,
                :changed_by_user_id,
                :change_note,
                NOW()
            )
        ");

        $stmt->execute([
            'agent_id' => (int) $agent['id'],
            'version_number' =>
                $versionNumber,
            'name' => $agent['name'],
            'slug' => $agent['slug'],
            'description' =>
                $agent['description'],
            'system_instructions' =>
                $agent['system_instructions'],
            'status' => $agent['status'],
            'model_override' =>
                $agent['model_override'],
            'max_output_tokens' =>
                $agent['max_output_tokens'],
            'capabilities_json' =>
                $agent['capabilities_json'],
            'changed_by_user_id' =>
                $userId,
            'change_note' =>
                mb_substr(
                    trim($changeNote),
                    0,
                    500
                ),
        ]);
    }

    /**
     * @param array<string, mixed> $agent
     */
    private function definitionSignature(
        array $agent
    ): string {
        return hash(
            'sha256',
            json_encode([
                'name' =>
                    (string) $agent['name'],
                'description' =>
                    (string) (
                        $agent['description']
                        ?? ''
                    ),
                'system_instructions' =>
                    (string) (
                        $agent[
                            'system_instructions'
                        ] ?? ''
                    ),
                'model_override' =>
                    (string) (
                        $agent[
                            'model_override'
                        ] ?? ''
                    ),
                'max_output_tokens' =>
                    (int) $agent[
                        'max_output_tokens'
                    ],
                'capabilities_json' =>
                    (string) (
                        $agent[
                            'capabilities_json'
                        ] ?? '[]'
                    ),
            ], JSON_UNESCAPED_SLASHES)
            ?: ''
        );
    }
}

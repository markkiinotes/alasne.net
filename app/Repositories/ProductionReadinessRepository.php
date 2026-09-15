<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ProductionReadinessRepository
{
    public function __construct(private PDO $db)
    {
    }

    /**
     * @param array<string, mixed> $result
     */
    public function saveRun(array $result): int
    {
        $summary = $result['summary'];

        $stmt = $this->db->prepare("
            INSERT INTO production_readiness_runs (
                status,
                environment_name,
                app_url,
                php_version,
                database_version,
                checks_total,
                checks_passed,
                checks_warned,
                checks_failed,
                checks_info,
                summary,
                started_at,
                finished_at,
                created_at
            ) VALUES (
                :status,
                :environment_name,
                :app_url,
                :php_version,
                :database_version,
                :checks_total,
                :checks_passed,
                :checks_warned,
                :checks_failed,
                :checks_info,
                :summary,
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'status' => (string) $summary['overall_status'],
            'environment_name' => $result['environment']['app_env'] ?? null,
            'app_url' => $result['environment']['app_url'] ?? null,
            'php_version' => PHP_VERSION,
            'database_version' => $result['environment']['database_version'] ?? null,
            'checks_total' => (int) $summary['total'],
            'checks_passed' => (int) $summary['passed'],
            'checks_warned' => (int) $summary['warned'],
            'checks_failed' => (int) $summary['failed'],
            'checks_info' => (int) $summary['info'],
            'summary' => (string) $summary['message'],
        ]);

        $runId = (int) $this->db->lastInsertId();

        $itemStmt = $this->db->prepare("
            INSERT INTO production_readiness_items (
                run_id,
                category,
                check_code,
                status,
                title,
                message,
                remediation,
                evidence,
                sort_order,
                created_at
            ) VALUES (
                :run_id,
                :category,
                :check_code,
                :status,
                :title,
                :message,
                :remediation,
                :evidence,
                :sort_order,
                NOW()
            )
        ");

        foreach ($result['items'] as $index => $item) {
            $itemStmt->execute([
                'run_id' => $runId,
                'category' => (string) $item['category'],
                'check_code' => (string) $item['code'],
                'status' => (string) $item['status'],
                'title' => (string) $item['title'],
                'message' => mb_substr((string) $item['message'], 0, 1000),
                'remediation' => isset($item['remediation'])
                    ? mb_substr((string) $item['remediation'], 0, 1000)
                    : null,
                'evidence' => $item['evidence'] ?? null,
                'sort_order' => (int) ($item['sort_order'] ?? ($index + 1) * 10),
            ]);
        }

        return $runId;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recentRuns(int $limit = 20): array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM production_readiness_runs
            ORDER BY id DESC
            LIMIT " . max(1, min(100, $limit))
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function run(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM production_readiness_runs
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();

        return $row ?: null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function itemsForRun(int $runId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM production_readiness_items
            WHERE run_id = :run_id
            ORDER BY sort_order ASC, id ASC
        ");
        $stmt->execute(['run_id' => $runId]);

        return $stmt->fetchAll();
    }

    public function latestRun(): ?array
    {
        $stmt = $this->db->query("
            SELECT *
            FROM production_readiness_runs
            ORDER BY id DESC
            LIMIT 1
        ");
        $row = $stmt->fetch();

        return $row ?: null;
    }
}

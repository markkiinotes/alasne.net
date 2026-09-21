<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class AiRunRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function createPending(
        int $agentId,
        ?int $userId,
        string $provider,
        string $model,
        string $promptSha256,
        int $promptLength
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO ai_runs (
                agent_id,
                requested_by_user_id,
                provider,
                model,
                status,
                prompt_sha256,
                prompt_length,
                created_at
            ) VALUES (
                :agent_id,
                :requested_by_user_id,
                :provider,
                :model,
                'pending',
                :prompt_sha256,
                :prompt_length,
                NOW()
            )
        ");

        $stmt->execute([
            'agent_id' => $agentId,
            'requested_by_user_id' => $userId,
            'provider' => $provider,
            'model' => $model,
            'prompt_sha256' => $promptSha256,
            'prompt_length' => $promptLength,
        ]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * @param array<string, mixed> $result
     */
    public function markSucceeded(
        int $runId,
        array $result,
        int $latencyMs,
        int $outputLength
    ): void {
        $stmt = $this->db->prepare("
            UPDATE ai_runs
            SET
                status = 'succeeded',
                output_length = :output_length,
                response_id = :response_id,
                provider_request_id = :provider_request_id,
                provider_status = :provider_status,
                input_tokens = :input_tokens,
                output_tokens = :output_tokens,
                total_tokens = :total_tokens,
                latency_ms = :latency_ms,
                error_code = NULL,
                error_message = NULL,
                completed_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $runId,
            'output_length' => $outputLength,
            'response_id' => $result['response_id'] ?? null,
            'provider_request_id' => $result['provider_request_id'] ?? null,
            'provider_status' => $result['provider_status'] ?? null,
            'input_tokens' => $result['input_tokens'] ?? null,
            'output_tokens' => $result['output_tokens'] ?? null,
            'total_tokens' => $result['total_tokens'] ?? null,
            'latency_ms' => $latencyMs,
        ]);
    }

    public function markFailed(
        int $runId,
        string $code,
        string $message,
        int $latencyMs
    ): void {
        $stmt = $this->db->prepare("
            UPDATE ai_runs
            SET
                status = 'failed',
                latency_ms = :latency_ms,
                error_code = :error_code,
                error_message = :error_message,
                completed_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $runId,
            'latency_ms' => $latencyMs,
            'error_code' => mb_substr($code, 0, 120),
            'error_message' => mb_substr($message, 0, 1000),
        ]);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function recent(int $limit = 50): array
    {
        $limit = max(1, min(250, $limit));

        $stmt = $this->db->query("
            SELECT
                r.*,
                a.name AS agent_name,
                u.name AS requested_by_name
            FROM ai_runs r
            INNER JOIN ai_agents a
                ON a.id = r.agent_id
            LEFT JOIN users u
                ON u.id = r.requested_by_user_id
            ORDER BY r.id DESC
            LIMIT {$limit}
        ");

        return $stmt->fetchAll();
    }
}

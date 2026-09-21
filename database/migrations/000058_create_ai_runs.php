<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS ai_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                agent_id BIGINT UNSIGNED NOT NULL,
                requested_by_user_id BIGINT UNSIGNED NULL,
                provider VARCHAR(60) NOT NULL,
                model VARCHAR(191) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending',
                prompt_sha256 CHAR(64) NOT NULL,
                prompt_length INT UNSIGNED NOT NULL DEFAULT 0,
                output_length INT UNSIGNED NOT NULL DEFAULT 0,
                response_id VARCHAR(191) NULL,
                provider_request_id VARCHAR(191) NULL,
                input_tokens INT UNSIGNED NULL,
                output_tokens INT UNSIGNED NULL,
                total_tokens INT UNSIGNED NULL,
                latency_ms INT UNSIGNED NULL,
                error_code VARCHAR(120) NULL,
                error_message VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                completed_at DATETIME NULL,
                KEY idx_ai_runs_agent (
                    agent_id,
                    created_at,
                    id
                ),
                KEY idx_ai_runs_status (
                    status,
                    created_at,
                    id
                ),
                KEY idx_ai_runs_user (
                    requested_by_user_id,
                    created_at,
                    id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");
    }

    public function down(): void
    {
        $this->db->exec("
            DROP TABLE IF EXISTS ai_runs
        ");
    }
};

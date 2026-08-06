<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_email_queue_attempts (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email_outbox_id BIGINT UNSIGNED NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'logged',
                transport VARCHAR(60) NOT NULL DEFAULT 'log',
                recipient VARCHAR(255) NULL,
                subject VARCHAR(255) NULL,
                message VARCHAR(1000) NULL,
                error_message VARCHAR(1000) NULL,
                attempted_at DATETIME NOT NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mc_email_queue_attempts_outbox (
                    email_outbox_id,
                    attempted_at
                ),
                KEY idx_mc_email_queue_attempts_status (
                    status,
                    attempted_at
                ),
                KEY idx_mc_email_queue_attempts_transport (
                    transport,
                    attempted_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        if ($this->tableExists('mission_control_scheduled_tasks')) {
            $this->seedScheduledTask();
        }
    }

    public function down(): void
    {
        $this->db->exec("
            DROP TABLE IF EXISTS mission_control_email_queue_attempts
        ");

        if ($this->tableExists('mission_control_scheduled_tasks')) {
            $stmt = $this->db->prepare("
                DELETE FROM mission_control_scheduled_tasks
                WHERE task_key = :task_key
            ");

            $stmt->execute([
                'task_key' => 'email_queue_processor',
            ]);
        }
    }

    private function seedScheduledTask(): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_scheduled_tasks (
                task_key,
                name,
                description,
                task_type,
                frequency_minutes,
                schedule_label,
                store_id,
                supplier_id,
                is_enabled,
                run_if_due,
                next_run_at,
                created_at,
                updated_at
            ) VALUES (
                'email_queue_processor',
                'Email Queue Processor',
                'Processes pending email outbox messages using the configured safe transport.',
                'email_queue',
                15,
                'Every 15 minutes',
                NULL,
                NULL,
                0,
                1,
                NOW(),
                NOW(),
                NOW()
            )
            ON DUPLICATE KEY UPDATE
                name = VALUES(name),
                description = VALUES(description),
                task_type = VALUES(task_type),
                frequency_minutes = VALUES(frequency_minutes),
                schedule_label = VALUES(schedule_label),
                updated_at = NOW()
        ");

        $stmt->execute();
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute([
            'table_name' => $table,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};

<?php

declare(strict_types=1);

use App\Core\Migration;

return new class($this->db) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_scheduled_tasks (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_key VARCHAR(100) NOT NULL,
                name VARCHAR(191) NOT NULL,
                description VARCHAR(1000) NULL,
                task_type VARCHAR(60) NOT NULL,
                frequency_minutes INT UNSIGNED NOT NULL DEFAULT 1440,
                schedule_label VARCHAR(120) NULL,
                store_id BIGINT UNSIGNED NULL,
                supplier_id BIGINT UNSIGNED NULL,
                is_enabled TINYINT(1) NOT NULL DEFAULT 1,
                run_if_due TINYINT(1) NOT NULL DEFAULT 1,
                next_run_at DATETIME NULL,
                last_run_at DATETIME NULL,
                last_status VARCHAR(30) NULL,
                last_summary VARCHAR(1000) NULL,
                created_at DATETIME NOT NULL,
                updated_at DATETIME NOT NULL,
                UNIQUE KEY uq_mission_control_scheduled_tasks_key (
                    task_key
                ),
                KEY idx_mission_control_scheduled_tasks_due (
                    is_enabled,
                    run_if_due,
                    next_run_at
                ),
                KEY idx_mission_control_scheduled_tasks_type (
                    task_type,
                    is_enabled
                ),
                KEY idx_mission_control_scheduled_tasks_scope (
                    store_id,
                    supplier_id
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS mission_control_scheduled_runs (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                task_id BIGINT UNSIGNED NULL,
                task_key VARCHAR(100) NOT NULL,
                task_type VARCHAR(60) NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'success',
                summary VARCHAR(1000) NULL,
                metrics_json LONGTEXT NULL,
                error_message VARCHAR(1000) NULL,
                started_at DATETIME NOT NULL,
                finished_at DATETIME NULL,
                created_at DATETIME NOT NULL,
                KEY idx_mission_control_scheduled_runs_task (
                    task_id,
                    started_at
                ),
                KEY idx_mission_control_scheduled_runs_status (
                    status,
                    started_at
                ),
                KEY idx_mission_control_scheduled_runs_type (
                    task_type,
                    started_at
                )
            ) ENGINE=InnoDB
              DEFAULT CHARSET=utf8mb4
              COLLATE=utf8mb4_unicode_ci
        ");

        $this->seedDefaultTasks();
    }

    public function down(): void
    {
        foreach ([
            'mission_control_scheduled_runs',
            'mission_control_scheduled_tasks',
        ] as $table) {
            $this->db->exec(
                "DROP TABLE IF EXISTS `{$table}`"
            );
        }
    }

    private function seedDefaultTasks(): void
    {
        $tasks = [
            [
                'task_key' => 'hourly_alert_scan',
                'name' => 'Hourly Alert Scan',
                'description' => 'Runs enabled Mission Control alert rules and opens or refreshes alerts when thresholds are crossed.',
                'task_type' => 'alert_scan',
                'frequency_minutes' => 60,
                'schedule_label' => 'Hourly',
                'is_enabled' => 1,
            ],
            [
                'task_key' => 'daily_admin_briefing_snapshot',
                'name' => 'Daily Admin Briefing Snapshot',
                'description' => 'Saves a briefing snapshot from current alerts, KPIs, and operational queues.',
                'task_type' => 'briefing_snapshot',
                'frequency_minutes' => 1440,
                'schedule_label' => 'Daily',
                'is_enabled' => 1,
            ],
            [
                'task_key' => 'daily_kpi_checkpoint',
                'name' => 'Daily KPI Checkpoint',
                'description' => 'Records a lightweight KPI checkpoint run for Mission Control reporting history.',
                'task_type' => 'kpi_checkpoint',
                'frequency_minutes' => 1440,
                'schedule_label' => 'Daily',
                'is_enabled' => 1,
            ],
            [
                'task_key' => 'weekly_admin_briefing_snapshot',
                'name' => 'Weekly Admin Briefing Snapshot',
                'description' => 'Saves a broader weekly briefing snapshot for management review.',
                'task_type' => 'briefing_snapshot',
                'frequency_minutes' => 10080,
                'schedule_label' => 'Weekly',
                'is_enabled' => 0,
            ],
        ];

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
                :task_key,
                :name,
                :description,
                :task_type,
                :frequency_minutes,
                :schedule_label,
                NULL,
                NULL,
                :is_enabled,
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

        foreach ($tasks as $task) {
            $stmt->execute([
                'task_key' => $task['task_key'],
                'name' => $task['name'],
                'description' => $task['description'],
                'task_type' => $task['task_type'],
                'frequency_minutes' => $task['frequency_minutes'],
                'schedule_label' => $task['schedule_label'],
                'is_enabled' => $task['is_enabled'],
            ]);
        }
    }
};

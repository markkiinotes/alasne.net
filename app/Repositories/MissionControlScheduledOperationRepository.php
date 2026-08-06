<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class MissionControlScheduledOperationRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function stores(): array
    {
        if (! $this->tableExists('stores')) {
            return [];
        }

        return $this->db->query("
            SELECT id, name
            FROM stores
            ORDER BY name ASC
        ")->fetchAll();
    }

    public function suppliers(int $storeId = 0): array
    {
        if (! $this->tableExists('suppliers')) {
            return [];
        }

        $sql = "
            SELECT id, name, code, store_id
            FROM suppliers
            WHERE 1 = 1
        ";
        $params = [];

        if ($storeId > 0 && $this->columnExists('suppliers', 'store_id')) {
            $sql .= ' AND store_id = :store_id';
            $params['store_id'] = $storeId;
        }

        $sql .= ' ORDER BY name ASC';

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function dashboard(array $filters = []): array
    {
        return [
            'summary' => $this->summary(),
            'tasks' => $this->tasks($filters),
            'runs' => $this->recentRuns($filters, 50),
        ];
    }

    public function summary(): array
    {
        $stmt = $this->db->query("
            SELECT
                COUNT(*) AS total_tasks,
                SUM(is_enabled = 1) AS enabled_tasks,
                SUM(is_enabled = 0) AS disabled_tasks,
                SUM(is_enabled = 1 AND run_if_due = 1 AND next_run_at IS NOT NULL AND next_run_at <= NOW()) AS due_tasks,
                SUM(last_status = 'success') AS successful_tasks,
                SUM(last_status = 'failed') AS failed_tasks
            FROM mission_control_scheduled_tasks
        ");

        $row = $stmt->fetch() ?: [];

        return [
            'total_tasks' => (int) ($row['total_tasks'] ?? 0),
            'enabled_tasks' => (int) ($row['enabled_tasks'] ?? 0),
            'disabled_tasks' => (int) ($row['disabled_tasks'] ?? 0),
            'due_tasks' => (int) ($row['due_tasks'] ?? 0),
            'successful_tasks' => (int) ($row['successful_tasks'] ?? 0),
            'failed_tasks' => (int) ($row['failed_tasks'] ?? 0),
        ];
    }

    public function tasks(array $filters = []): array
    {
        $sql = "
            SELECT
                t.*,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_scheduled_tasks t
            LEFT JOIN stores s
                ON s.id = t.store_id
            LEFT JOIN suppliers sup
                ON sup.id = t.supplier_id
            WHERE 1 = 1
        ";
        $params = [];

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND (t.store_id = :store_id OR t.store_id IS NULL)';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND (t.supplier_id = :supplier_id OR t.supplier_id IS NULL)';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        if (trim((string) ($filters['task_type'] ?? '')) !== '') {
            $sql .= ' AND t.task_type = :task_type';
            $params['task_type'] = trim((string) $filters['task_type']);
        }

        $sql .= "
            ORDER BY
                t.is_enabled DESC,
                t.next_run_at IS NULL ASC,
                t.next_run_at ASC,
                t.name ASC
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function task(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_scheduled_tasks
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $id]);
        $task = $stmt->fetch();

        return $task ?: null;
    }

    public function dueTasks(): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM mission_control_scheduled_tasks
            WHERE is_enabled = 1
            AND run_if_due = 1
            AND next_run_at IS NOT NULL
            AND next_run_at <= NOW()
            ORDER BY next_run_at ASC, id ASC
            LIMIT 25
        ");

        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function saveTask(array $data): int
    {
        $id = (int) ($data['id'] ?? 0);

        if ($id <= 0) {
            throw new RuntimeException('Task ID is required.');
        }

        $task = $this->task($id);

        if (! $task) {
            throw new RuntimeException('Scheduled task not found.');
        }

        $frequency = max(5, (int) ($data['frequency_minutes'] ?? $task['frequency_minutes'] ?? 1440));
        $storeId = (int) ($data['store_id'] ?? 0);
        $supplierId = (int) ($data['supplier_id'] ?? 0);

        $stmt = $this->db->prepare("
            UPDATE mission_control_scheduled_tasks
            SET
                name = :name,
                description = :description,
                frequency_minutes = :frequency_minutes,
                schedule_label = :schedule_label,
                store_id = :store_id,
                supplier_id = :supplier_id,
                is_enabled = :is_enabled,
                run_if_due = :run_if_due,
                next_run_at = :next_run_at,
                updated_at = NOW()
            WHERE id = :id
        ");

        $nextRunAt = trim((string) ($data['next_run_at'] ?? ''));

        $stmt->execute([
            'id' => $id,
            'name' => mb_substr(trim((string) ($data['name'] ?? $task['name'])), 0, 191),
            'description' => $this->nullable($data['description'] ?? null, 1000),
            'frequency_minutes' => $frequency,
            'schedule_label' => $this->nullable($data['schedule_label'] ?? null, 120),
            'store_id' => $storeId > 0 ? $storeId : null,
            'supplier_id' => $supplierId > 0 ? $supplierId : null,
            'is_enabled' => ! empty($data['is_enabled']) ? 1 : 0,
            'run_if_due' => ! empty($data['run_if_due']) ? 1 : 0,
            'next_run_at' => $nextRunAt !== ''
                ? str_replace('T', ' ', $nextRunAt) . (strlen($nextRunAt) === 16 ? ':00' : '')
                : null,
        ]);

        return $id;
    }

    public function recordRunStart(array $task): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO mission_control_scheduled_runs (
                task_id,
                task_key,
                task_type,
                status,
                summary,
                metrics_json,
                error_message,
                started_at,
                finished_at,
                created_at
            ) VALUES (
                :task_id,
                :task_key,
                :task_type,
                'running',
                NULL,
                NULL,
                NULL,
                NOW(),
                NULL,
                NOW()
            )
        ");

        $stmt->execute([
            'task_id' => (int) $task['id'],
            'task_key' => (string) $task['task_key'],
            'task_type' => (string) $task['task_type'],
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function recordRunFinish(
        int $runId,
        array $task,
        string $status,
        string $summary,
        array $metrics = [],
        ?string $error = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE mission_control_scheduled_runs
            SET
                status = :status,
                summary = :summary,
                metrics_json = :metrics_json,
                error_message = :error_message,
                finished_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $runId,
            'status' => $status,
            'summary' => mb_substr($summary, 0, 1000),
            'metrics_json' => json_encode($metrics, JSON_PRETTY_PRINT),
            'error_message' => $this->nullable($error, 1000),
        ]);

        $this->updateTaskAfterRun($task, $status, $summary);
    }

    private function updateTaskAfterRun(
        array $task,
        string $status,
        string $summary
    ): void {
        $frequencyMinutes = max(5, (int) $task['frequency_minutes']);

        $stmt = $this->db->prepare("
            UPDATE mission_control_scheduled_tasks
            SET
                last_run_at = NOW(),
                last_status = :status,
                last_summary = :summary,
                next_run_at = DATE_ADD(NOW(), INTERVAL {$frequencyMinutes} MINUTE),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => (int) $task['id'],
            'status' => $status,
            'summary' => mb_substr($summary, 0, 1000),
        ]);
    }

    public function recentRuns(array $filters = [], int $limit = 50): array
    {
        $sql = "
            SELECT
                r.*,
                t.name AS task_name,
                s.name AS store_name,
                sup.name AS supplier_name
            FROM mission_control_scheduled_runs r
            LEFT JOIN mission_control_scheduled_tasks t
                ON t.id = r.task_id
            LEFT JOIN stores s
                ON s.id = t.store_id
            LEFT JOIN suppliers sup
                ON sup.id = t.supplier_id
            WHERE 1 = 1
        ";
        $params = [];

        if (trim((string) ($filters['task_type'] ?? '')) !== '') {
            $sql .= ' AND r.task_type = :task_type';
            $params['task_type'] = trim((string) $filters['task_type']);
        }

        if ((int) ($filters['store_id'] ?? 0) > 0) {
            $sql .= ' AND t.store_id = :store_id';
            $params['store_id'] = (int) $filters['store_id'];
        }

        if ((int) ($filters['supplier_id'] ?? 0) > 0) {
            $sql .= ' AND t.supplier_id = :supplier_id';
            $params['supplier_id'] = (int) $filters['supplier_id'];
        }

        $sql .= '
            ORDER BY r.started_at DESC, r.id DESC
            LIMIT ' . max(1, min(250, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function taskTypes(): array
    {
        return [
            'alert_scan',
            'briefing_snapshot',
            'kpi_checkpoint',
        ];
    }

    private function nullable(mixed $value, int $limit): ?string
    {
        $value = trim((string) $value);

        if ($value === '') {
            return null;
        }

        return mb_substr($value, 0, $limit);
    }

    private function tableExists(string $table): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
        ");

        $stmt->execute(['table_name' => $table]);

        return (int) $stmt->fetchColumn() > 0;
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
}

<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlScheduledOperationRepository;
use RuntimeException;

class MissionControlScheduledOperationService
{
    public function __construct(
        private MissionControlScheduledOperationRepository $scheduled,
        private MissionControlAlertService $alerts,
        private MissionControlBriefingService $briefings,
        private MissionControlEmailQueueService $emailQueue
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function runTask(int $taskId): array
    {
        $task = $this->scheduled->task($taskId);

        if (! $task) {
            throw new RuntimeException('Scheduled task not found.');
        }

        return $this->executeTask($task);
    }

    /**
     * @return array<string, mixed>
     */
    public function runDue(): array
    {
        $results = [];
        $success = 0;
        $failed = 0;

        foreach ($this->scheduled->dueTasks() as $task) {
            try {
                $results[] = $this->executeTask($task);
                $success++;
            } catch (\Throwable $exception) {
                $results[] = [
                    'task_id' => (int) $task['id'],
                    'task_key' => (string) $task['task_key'],
                    'status' => 'failed',
                    'summary' => $exception->getMessage(),
                ];
                $failed++;
            }
        }

        return [
            'success' => $success,
            'failed' => $failed,
            'results' => $results,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function executeTask(array $task): array
    {
        $runId = $this->scheduled->recordRunStart($task);

        try {
            $result = match ((string) $task['task_type']) {
                'alert_scan' => $this->runAlertScan($task),
                'briefing_snapshot' => $this->runBriefingSnapshot($task),
                'kpi_checkpoint' => $this->runKpiCheckpoint($task),
                'email_queue' => $this->runEmailQueue($task),
                default => throw new RuntimeException(
                    'Unsupported scheduled task type: '
                    . (string) $task['task_type']
                ),
            };

            $this->scheduled->recordRunFinish(
                $runId,
                $task,
                'success',
                (string) $result['summary'],
                $result['metrics'] ?? [],
                null
            );

            return [
                'run_id' => $runId,
                'task_id' => (int) $task['id'],
                'task_key' => (string) $task['task_key'],
                'status' => 'success',
                'summary' => (string) $result['summary'],
                'metrics' => $result['metrics'] ?? [],
            ];
        } catch (\Throwable $exception) {
            $this->scheduled->recordRunFinish(
                $runId,
                $task,
                'failed',
                $exception->getMessage(),
                [],
                $exception->getMessage()
            );

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function runAlertScan(array $task): array
    {
        $filters = $this->taskFilters($task);
        $result = $this->alerts->scan($filters);

        return [
            'summary' =>
                (int) $result['created_or_updated']
                . ' alert(s) opened or refreshed; '
                . (int) $result['skipped']
                . ' rule(s) skipped.',
            'metrics' => $result['metrics'] ?? [],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runBriefingSnapshot(array $task): array
    {
        $filters = $this->taskFilters($task);
        $filters['period_start'] = date('Y-m-d', strtotime('-7 days'));
        $filters['period_end'] = date('Y-m-d');

        if ((int) $task['frequency_minutes'] >= 10080) {
            $filters['period_start'] = date('Y-m-d', strtotime('-30 days'));
        }

        $briefingId = $this->briefings->savePreview($filters, null);

        return [
            'summary' => 'Saved Mission Control briefing snapshot #' . $briefingId . '.',
            'metrics' => [
                'briefing_id' => $briefingId,
                'period_start' => $filters['period_start'],
                'period_end' => $filters['period_end'],
                'store_id' => $filters['store_id'],
                'supplier_id' => $filters['supplier_id'],
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function runKpiCheckpoint(array $task): array
    {
        $filters = $this->taskFilters($task);
        $metrics = $this->alerts->metrics($filters);

        return [
            'summary' =>
                'KPI checkpoint recorded: '
                . '$'
                . number_format((float) ($metrics['sales_revenue'] ?? 0), 2)
                . ' revenue, '
                . number_format((float) ($metrics['estimated_margin_percent'] ?? 0), 1)
                . '% margin, '
                . (int) ($metrics['tracking_gaps'] ?? 0)
                . ' tracking gap(s).',
            'metrics' => $metrics,
        ];
    }


    /**
     * @return array<string, mixed>
     */
    private function runEmailQueue(array $task): array
    {
        $result = $this->emailQueue->process([
            'limit' => 25,
            'transport' => $_ENV['EMAIL_QUEUE_TRANSPORT'] ?? 'log',
        ]);

        return [
            'summary' =>
                (int) $result['processed']
                . ' email(s) processed; '
                . (int) $result['logged']
                . ' logged, '
                . (int) $result['sent']
                . ' sent, '
                . (int) $result['failed']
                . ' failed.',
            'metrics' => [
                'processed' => (int) $result['processed'],
                'logged' => (int) $result['logged'],
                'sent' => (int) $result['sent'],
                'failed' => (int) $result['failed'],
                'transport' => (string) $result['transport'],
            ],
        ];
    }

    /**
     * @return array<string, int>
     */
    private function taskFilters(array $task): array
    {
        return [
            'store_id' => max(0, (int) ($task['store_id'] ?? 0)),
            'supplier_id' => max(0, (int) ($task['supplier_id'] ?? 0)),
        ];
    }
}

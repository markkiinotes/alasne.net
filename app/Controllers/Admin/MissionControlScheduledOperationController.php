<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\MissionControlScheduledOperationRepository;
use App\Services\Admin\MissionControlScheduledOperationService;
use App\Services\Auth\CsrfService;

class MissionControlScheduledOperationController extends Controller
{
    public function __construct(
        private MissionControlScheduledOperationRepository $scheduled,
        private MissionControlScheduledOperationService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();

        return $this->view(
            'admin.scheduled-operations.index',
            [
                'title' => 'Scheduled Operations',
                'filters' => $filters,
                'stores' => $this->scheduled->stores(),
                'suppliers' => $this->scheduled->suppliers((int) $filters['store_id']),
                'taskTypes' => $this->scheduled->taskTypes(),
                'dashboard' => $this->scheduled->dashboard($filters),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_schedule_success'),
                'error' => $this->flash('mission_control_schedule_error'),
            ],
            'admin'
        );
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('task_id');
        $task = $this->scheduled->task($id);

        if (! $task) {
            http_response_code(404);

            return '404 - Scheduled task not found';
        }

        return $this->view(
            'admin.scheduled-operations.edit',
            [
                'title' => 'Edit Scheduled Operation',
                'task' => $task,
                'stores' => $this->scheduled->stores(),
                'suppliers' => $this->scheduled->suppliers(),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_schedule_success'),
                'error' => $this->flash('mission_control_schedule_error'),
            ],
            'admin'
        );
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('task_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/scheduled-operations/' . $id,
                'Security token expired. Please try again.'
            );
        }

        try {
            $data = $this->taskInput();
            $data['id'] = $id;

            $this->scheduled->saveTask($data);
            $this->csrf->regenerate();

            $_SESSION['mission_control_schedule_success'] =
                'Scheduled operation updated.';

            $this->response->redirect('/admin/scheduled-operations/' . $id);
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_schedule_error'] =
                $exception->getMessage()
                ?: 'Unable to update scheduled operation.';

            $this->response->redirect('/admin/scheduled-operations/' . $id);
        }

        return null;
    }

    public function runTask(Request $request)
    {
        $id = (int) $request->route('task_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/scheduled-operations',
                'Security token expired. Please try again.'
            );
        }

        try {
            $result = $this->service->runTask($id);
            $this->csrf->regenerate();

            $_SESSION['mission_control_schedule_success'] =
                'Scheduled operation run complete: '
                . (string) $result['summary'];
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_schedule_error'] =
                $exception->getMessage()
                ?: 'Scheduled operation failed.';
        }

        $this->response->redirect('/admin/scheduled-operations');

        return null;
    }

    public function runDue()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/scheduled-operations',
                'Security token expired. Please try again.'
            );
        }

        try {
            $result = $this->service->runDue();
            $this->csrf->regenerate();

            $_SESSION['mission_control_schedule_success'] =
                'Due operation run complete: '
                . (int) $result['success']
                . ' succeeded, '
                . (int) $result['failed']
                . ' failed.';
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_schedule_error'] =
                $exception->getMessage()
                ?: 'Unable to run due operations.';
        }

        $this->response->redirect('/admin/scheduled-operations');

        return null;
    }

    public function export()
    {
        $runs = $this->scheduled->recentRuns($this->filters(), 1000);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="mission-control-scheduled-runs-'
            . date('Y-m-d-His')
            . '.csv"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            exit;
        }

        fputcsv($output, [
            'Run ID',
            'Task',
            'Task Key',
            'Type',
            'Status',
            'Summary',
            'Started',
            'Finished',
            'Error',
        ]);

        foreach ($runs as $run) {
            fputcsv($output, [
                $run['id'] ?? '',
                $run['task_name'] ?? '',
                $run['task_key'] ?? '',
                $run['task_type'] ?? '',
                $run['status'] ?? '',
                $run['summary'] ?? '',
                $run['started_at'] ?? '',
                $run['finished_at'] ?? '',
                $run['error_message'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return [
            'task_type' => trim((string) $this->request->input('task_type')),
            'store_id' => max(0, (int) $this->request->input('store_id')),
            'supplier_id' => max(0, (int) $this->request->input('supplier_id')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function taskInput(): array
    {
        return [
            'name' => $this->request->input('name'),
            'description' => $this->request->input('description'),
            'frequency_minutes' => $this->request->input('frequency_minutes'),
            'schedule_label' => $this->request->input('schedule_label'),
            'store_id' => $this->request->input('store_id'),
            'supplier_id' => $this->request->input('supplier_id'),
            'is_enabled' => $this->request->input('is_enabled'),
            'run_if_due' => $this->request->input('run_if_due'),
            'next_run_at' => $this->request->input('next_run_at'),
        ];
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input('_csrf_token')
        );
    }

    private function redirectWithError(
        string $path,
        string $message
    ) {
        $_SESSION['mission_control_schedule_error'] = $message;

        $this->response->redirect($path);

        return null;
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return $value;
    }
}

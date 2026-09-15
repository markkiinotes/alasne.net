<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ProductionReadinessRepository;
use App\Services\Auth\CsrfService;
use App\Services\Deployment\ProductionReadinessService;

class ProductionReadinessController extends Controller
{
    public function __construct(
        private ProductionReadinessService $readiness,
        private ProductionReadinessRepository $repository,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $live = $this->readiness->run();

        return $this->view(
            'admin.production-readiness.index',
            [
                'title' => 'Production Readiness & Security Hardening',
                'live' => $live,
                'recentRuns' => $this->repository->recentRuns(),
                'latestRun' => $this->repository->latestRun(),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('production_readiness_success'),
                'error' => $this->flash('production_readiness_error'),
            ],
            'admin'
        );
    }

    public function run()
    {
        if (! $this->validateCsrf()) {
            $_SESSION['production_readiness_error'] =
                'Security token expired. Please try again.';
            $this->response->redirect('/admin/production-readiness');
            return null;
        }

        try {
            $result = $this->readiness->run();
            $runId = $this->repository->saveRun($result);
            $this->csrf->regenerate();

            $_SESSION['production_readiness_success'] =
                'Production readiness audit saved.';

            $this->response->redirect(
                '/admin/production-readiness/runs/' . $runId
            );
        } catch (\Throwable $exception) {
            $_SESSION['production_readiness_error'] =
                $exception->getMessage()
                ?: 'Unable to save production readiness audit.';
            $this->response->redirect('/admin/production-readiness');
        }

        return null;
    }

    public function show(Request $request)
    {
        $runId = (int) $request->route('run_id');
        $run = $this->repository->run($runId);

        if (! $run) {
            http_response_code(404);
            return '404 - Production readiness run not found';
        }

        return $this->view(
            'admin.production-readiness.show',
            [
                'title' => 'Production Readiness Run #' . $runId,
                'run' => $run,
                'items' => $this->repository->itemsForRun($runId),
            ],
            'admin'
        );
    }

    public function export(Request $request)
    {
        $runId = (int) $request->input('run_id');
        $run = $runId > 0
            ? $this->repository->run($runId)
            : $this->repository->latestRun();

        if (! $run) {
            $result = $this->readiness->run();
            $this->exportLive($result);
            return null;
        }

        $items = $this->repository->itemsForRun((int) $run['id']);
        $filename = 'production-readiness-run-'
            . (int) $run['id']
            . '-' . date('Y-m-d-His') . '.csv';

        $this->streamCsv($filename, $items);
        return null;
    }

    /**
     * @param array<string, mixed> $result
     */
    private function exportLive(array $result): void
    {
        $filename = 'production-readiness-live-'
            . date('Y-m-d-His')
            . '.csv';

        $items = [];
        foreach ($result['items'] as $item) {
            $items[] = [
                'category' => $item['category'],
                'check_code' => $item['code'],
                'status' => $item['status'],
                'title' => $item['title'],
                'message' => $item['message'],
                'remediation' => $item['remediation'] ?? '',
                'evidence' => $item['evidence'] ?? '',
            ];
        }

        $this->streamCsv($filename, $items);
    }

    /**
     * @param list<array<string, mixed>> $items
     */
    private function streamCsv(string $filename, array $items): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            exit;
        }

        fputcsv($output, [
            'Category',
            'Check Code',
            'Status',
            'Title',
            'Message',
            'Remediation',
            'Evidence',
        ]);

        foreach ($items as $item) {
            fputcsv($output, [
                $item['category'] ?? '',
                $item['check_code'] ?? $item['code'] ?? '',
                $item['status'] ?? '',
                $item['title'] ?? '',
                $item['message'] ?? '',
                $item['remediation'] ?? '',
                $item['evidence'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input('_csrf_token')
        );
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;
        unset($_SESSION[$key]);

        return $value;
    }
}

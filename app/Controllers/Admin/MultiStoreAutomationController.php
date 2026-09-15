<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\MultiStoreAutomationRepository;
use App\Services\Auth\CsrfService;
use App\Services\MultiStore\MultiStoreAutomationService;

class MultiStoreAutomationController extends Controller
{
    public function __construct(
        private MultiStoreAutomationRepository $automation,
        private MultiStoreAutomationService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();
        $dashboard = $this->automation->dashboard($filters);

        return $this->view(
            'admin.multi-store-automation.index',
            [
                'title' => 'Multi-Store Automation Scaling',
                'filters' => $filters,
                'stores' => $this->automation->stores(),
                'dashboard' => $dashboard,
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash(
                    'multi_store_automation_success'
                ),
                'error' => $this->flash(
                    'multi_store_automation_error'
                ),
            ],
            'admin'
        );
    }

    public function show(Request $request)
    {
        $storeId = (int) $request->route('store_id');
        $detail = $this->automation->storeDetail($storeId);

        if (! $detail) {
            http_response_code(404);

            return '404 - Store automation profile not found';
        }

        return $this->view(
            'admin.multi-store-automation.show',
            [
                'title' =>
                    'Store Automation | '
                    . $detail['scorecard']['store_name'],
                'detail' => $detail,
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash(
                    'multi_store_automation_success'
                ),
                'error' => $this->flash(
                    'multi_store_automation_error'
                ),
            ],
            'admin'
        );
    }

    public function saveProfile(Request $request)
    {
        $storeId = (int) $request->route('store_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/multi-store-automation/' . $storeId,
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->automation->saveProfile(
                $storeId,
                $this->profileInput()
            );

            $this->csrf->regenerate();

            $_SESSION['multi_store_automation_success'] =
                'Store automation profile saved.';
        } catch (\Throwable $exception) {
            $_SESSION['multi_store_automation_error'] =
                $exception->getMessage()
                ?: 'Unable to save the store automation profile.';
        }

        $this->response->redirect(
            '/admin/multi-store-automation/' . $storeId
        );

        return null;
    }

    public function saveAudit()
    {
        $filters = $this->filters();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $runId = $this->service->saveAuditRun(
                (int) ($filters['store_id'] ?? 0) ?: null
            );

            $this->csrf->regenerate();

            $_SESSION['multi_store_automation_success'] =
                'Multi-store launch audit saved.';

            $this->response->redirect(
                '/admin/multi-store-automation/runs/' . $runId
            );

            return null;
        } catch (\Throwable $exception) {
            $_SESSION['multi_store_automation_error'] =
                $exception->getMessage()
                ?: 'Unable to save the launch audit.';
        }

        $this->response->redirect($this->indexUrl($filters));

        return null;
    }

    public function refreshCandidates()
    {
        $filters = $this->filters();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $created = $this->service->refreshCatalogCandidates(
                (int) ($filters['store_id'] ?? 0) ?: null
            );

            $this->csrf->regenerate();

            $_SESSION['multi_store_automation_success'] =
                $created . ' catalog candidate row(s) refreshed.';
        } catch (\Throwable $exception) {
            $_SESSION['multi_store_automation_error'] =
                $exception->getMessage()
                ?: 'Unable to refresh catalog candidates.';
        }

        $this->response->redirect($this->indexUrl($filters));

        return null;
    }

    public function reviewCandidate(Request $request)
    {
        $candidateId = (int) $request->route('candidate_id');
        $filters = $this->filtersFromRequest();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->automation->reviewCandidate(
                $candidateId,
                trim((string) $this->request->input('candidate_status')),
                trim((string) $this->request->input('review_note'))
            );

            $this->csrf->regenerate();

            $_SESSION['multi_store_automation_success'] =
                'Catalog candidate review saved.';
        } catch (\Throwable $exception) {
            $_SESSION['multi_store_automation_error'] =
                $exception->getMessage()
                ?: 'Unable to save catalog candidate review.';
        }

        $this->response->redirect($this->indexUrl($filters));

        return null;
    }

    public function run(Request $request)
    {
        $runId = (int) $request->route('run_id');
        $run = $this->automation->run($runId);

        if (! $run) {
            http_response_code(404);

            return '404 - Multi-store audit run not found';
        }

        return $this->view(
            'admin.multi-store-automation.run',
            [
                'title' => 'Multi-Store Audit Run #' . $runId,
                'run' => $run,
                'items' => $this->automation->runItems($runId),
            ],
            'admin'
        );
    }

    public function export()
    {
        $filters = $this->filters();
        $rows = $this->automation->storeScorecards($filters);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="multi-store-automation-'
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
            'Store',
            'Slug',
            'Store Status',
            'Launch Status',
            'Automation Status',
            'Readiness',
            'Health Score',
            'Active Products',
            'Approved Supplier Products',
            'Active Suppliers',
            'Supplier Mappings',
            'Tracking Gaps',
            'Return Policy Ready',
            'Blocked Checks',
            'Warning Checks',
            'Ready Checks',
            'Last Audit',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['store_name'] ?? '',
                $row['store_slug'] ?? '',
                $row['store_status'] ?? '',
                $row['launch_status'] ?? '',
                $row['automation_status'] ?? '',
                $row['readiness'] ?? '',
                $row['health_score'] ?? '',
                $row['active_product_count'] ?? 0,
                $row['approved_mapping_count'] ?? 0,
                $row['active_supplier_count'] ?? 0,
                $row['supplier_mapping_count'] ?? 0,
                $row['tracking_gap_count'] ?? 0,
                ! empty($row['return_policy_ready']) ? 'yes' : 'no',
                $row['blocked_count'] ?? 0,
                $row['warning_count'] ?? 0,
                $row['ready_count'] ?? 0,
                $row['last_automation_audit_at'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    public function exportRun(Request $request)
    {
        $runId = (int) $request->route('run_id');
        $run = $this->automation->run($runId);

        if (! $run) {
            http_response_code(404);
            exit('Audit run not found.');
        }

        $items = $this->automation->runItems($runId);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="multi-store-audit-run-'
            . $runId
            . '.csv"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            exit;
        }

        fputcsv($output, [
            'Store',
            'Category',
            'Check',
            'Severity',
            'Title',
            'Message',
            'Action URL',
        ]);

        foreach ($items as $item) {
            fputcsv($output, [
                $item['store_name'] ?? '',
                $item['category'] ?? '',
                $item['check_key'] ?? '',
                $item['severity'] ?? '',
                $item['title'] ?? '',
                $item['message'] ?? '',
                $item['action_url'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    private function filters(): array
    {
        return [
            'store_id' => max(0, (int) $this->request->input('store_id')),
            'launch_status' => trim((string) $this->request->input('launch_status')),
            'readiness' => trim((string) $this->request->input('readiness')),
            'candidate_status' => trim((string) $this->request->input('candidate_status')),
        ];
    }

    private function filtersFromRequest(): array
    {
        return [
            'store_id' => max(0, (int) $this->request->input('filter_store_id')),
            'launch_status' => trim((string) $this->request->input('filter_launch_status')),
            'readiness' => trim((string) $this->request->input('filter_readiness')),
            'candidate_status' => trim((string) $this->request->input('filter_candidate_status')),
        ];
    }

    private function profileInput(): array
    {
        return [
            'launch_status' => $this->request->input('launch_status'),
            'automation_status' => $this->request->input('automation_status'),
            'target_launch_date' => $this->request->input('target_launch_date'),
            'niche_summary' => $this->request->input('niche_summary'),
            'primary_supplier_id' => $this->request->input('primary_supplier_id'),
            'margin_target_percent' => $this->request->input('margin_target_percent'),
            'minimum_approved_products' => $this->request->input('minimum_approved_products'),
            'require_return_policy' => $this->request->input('require_return_policy'),
            'require_supplier_mapping' => $this->request->input('require_supplier_mapping'),
            'require_store_credit_ready' => $this->request->input('require_store_credit_ready'),
            'require_tracking_ready' => $this->request->input('require_tracking_ready'),
            'notes' => $this->request->input('notes'),
        ];
    }

    private function indexUrl(array $filters): string
    {
        $query = http_build_query(array_filter(
            $filters,
            static fn (mixed $value): bool =>
                $value !== ''
                && $value !== null
                && $value !== 0
        ));

        return '/admin/multi-store-automation'
            . ($query !== '' ? '?' . $query : '');
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input('_csrf_token')
        );
    }

    private function redirectWithError(string $path, string $message)
    {
        $_SESSION['multi_store_automation_error'] = $message;
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

<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\MissionControlAlertRepository;
use App\Services\Admin\MissionControlAlertService;
use App\Services\Auth\CsrfService;

class MissionControlAlertController extends Controller
{
    public function __construct(
        private MissionControlAlertRepository $alerts,
        private MissionControlAlertService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();

        return $this->view(
            'admin.alerts.index',
            [
                'title' => 'Alerts & Notification Center',
                'filters' => $filters,
                'stores' => $this->alerts->stores(),
                'suppliers' => $this->alerts->suppliers((int) $filters['store_id']),
                'dashboard' => $this->alerts->dashboard($filters),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_alert_success'),
                'error' => $this->flash('mission_control_alert_error'),
            ],
            'admin'
        );
    }

    public function ruleForm(Request $request)
    {
        $id = (int) ($request->route('rule_id') ?? 0);
        $rule = $id > 0 ? $this->alerts->rule($id) : null;

        if ($id > 0 && ! $rule) {
            http_response_code(404);

            return '404 - Alert rule not found';
        }

        return $this->view(
            'admin.alerts.rule',
            [
                'title' => $id > 0
                    ? 'Edit Alert Rule'
                    : 'Create Alert Rule',
                'rule' => $rule,
                'metricKeys' => $this->alerts->metricKeys(),
                'stores' => $this->alerts->stores(),
                'suppliers' => $this->alerts->suppliers(),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_alert_success'),
                'error' => $this->flash('mission_control_alert_error'),
            ],
            'admin'
        );
    }

    public function saveRule(Request $request)
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/alerts',
                'Security token expired. Please try again.'
            );
        }

        $id = (int) ($request->route('rule_id') ?? 0);

        try {
            $data = $this->ruleInput();
            $data['id'] = $id;

            $savedId = $this->alerts->saveRule($data);

            $this->csrf->regenerate();

            $_SESSION['mission_control_alert_success'] =
                'Alert rule saved.';

            $this->response->redirect(
                '/admin/alerts/rules/' . $savedId
            );
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_alert_error'] =
                $exception->getMessage()
                ?: 'Unable to save alert rule.';

            $this->response->redirect(
                $id > 0
                    ? '/admin/alerts/rules/' . $id
                    : '/admin/alerts/rules/create'
            );
        }

        return null;
    }

    public function deleteRule(Request $request)
    {
        $id = (int) $request->route('rule_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/alerts',
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->alerts->deleteRule($id);
            $this->csrf->regenerate();

            $_SESSION['mission_control_alert_success'] =
                'Alert rule deleted.';
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_alert_error'] =
                $exception->getMessage()
                ?: 'Unable to delete alert rule.';
        }

        $this->response->redirect('/admin/alerts');

        return null;
    }

    public function scan()
    {
        $filters = $this->filters();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $result = $this->service->scan($filters);

            $this->csrf->regenerate();

            $_SESSION['mission_control_alert_success'] =
                'Alert scan complete: '
                . (int) $result['created_or_updated']
                . ' alert(s) opened or refreshed, '
                . (int) $result['skipped']
                . ' rule(s) skipped.';
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_alert_error'] =
                $exception->getMessage()
                ?: 'Alert scan failed.';
        }

        $this->response->redirect($this->indexUrl($filters));

        return null;
    }

    public function updateAlert(Request $request)
    {
        $id = (int) $request->route('alert_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/alerts',
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->alerts->resolveAlert(
                $id,
                trim((string) $this->request->input('status')),
                trim((string) $this->request->input('resolution_note')),
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            $_SESSION['mission_control_alert_success'] =
                'Alert updated.';
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_alert_error'] =
                $exception->getMessage()
                ?: 'Unable to update alert.';
        }

        $this->response->redirect('/admin/alerts');

        return null;
    }

    public function export()
    {
        $filters = $this->filters();
        $alerts = $this->alerts->alerts($filters, 1000);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="mission-control-alerts-'
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
            'ID',
            'Status',
            'Severity',
            'Title',
            'Metric',
            'Metric Value',
            'Threshold',
            'Store',
            'Supplier',
            'First Seen',
            'Last Seen',
            'Resolved At',
            'Action URL',
        ]);

        foreach ($alerts as $alert) {
            fputcsv($output, [
                $alert['id'] ?? '',
                $alert['status'] ?? '',
                $alert['severity'] ?? '',
                $alert['title'] ?? '',
                $alert['metric_key'] ?? '',
                $alert['metric_value'] ?? '',
                $alert['threshold_value'] ?? '',
                $alert['store_name'] ?? '',
                $alert['supplier_name'] ?? '',
                $alert['first_seen_at'] ?? '',
                $alert['last_seen_at'] ?? '',
                $alert['resolved_at'] ?? '',
                $alert['action_url'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    private function filters(): array
    {
        return [
            'status' => trim((string) $this->request->input('status')),
            'severity' => trim((string) $this->request->input('severity')),
            'store_id' => max(0, (int) $this->request->input('store_id')),
            'supplier_id' => max(0, (int) $this->request->input('supplier_id')),
        ];
    }

    private function ruleInput(): array
    {
        return [
            'rule_key' => $this->request->input('rule_key'),
            'name' => $this->request->input('name'),
            'description' => $this->request->input('description'),
            'metric_key' => $this->request->input('metric_key'),
            'operator' => $this->request->input('operator'),
            'threshold_value' => $this->request->input('threshold_value'),
            'severity' => $this->request->input('severity'),
            'store_id' => $this->request->input('store_id'),
            'supplier_id' => $this->request->input('supplier_id'),
            'is_enabled' => $this->request->input('is_enabled'),
            'cooldown_minutes' => $this->request->input('cooldown_minutes'),
            'action_url' => $this->request->input('action_url'),
        ];
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input('_csrf_token')
        );
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

        return '/admin/alerts'
            . ($query !== '' ? '?' . $query : '');
    }

    private function redirectWithError(
        string $path,
        string $message
    ) {
        $_SESSION['mission_control_alert_error'] = $message;

        $this->response->redirect($path);

        return null;
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return $value;
    }

    private function currentUserId(): ?int
    {
        return isset($_SESSION['user']['id'])
            ? (int) $_SESSION['user']['id']
            : null;
    }
}

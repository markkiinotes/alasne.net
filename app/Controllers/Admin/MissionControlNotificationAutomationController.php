<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\MissionControlNotificationAutomationRepository;
use App\Services\Admin\MissionControlNotificationAutomationService;
use App\Services\Auth\CsrfService;

class MissionControlNotificationAutomationController extends Controller
{
    public function __construct(
        private MissionControlNotificationAutomationRepository $automations,
        private MissionControlNotificationAutomationService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();

        return $this->view(
            'admin.notification-automations.index',
            [
                'title' => 'Notification Automation Rules',
                'filters' => $filters,
                'dashboard' => $this->automations->dashboard($filters),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('notification_automation_success'),
                'error' => $this->flash('notification_automation_error'),
            ],
            'admin'
        );
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('rule_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-automations',
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->automations->update(
                $id,
                [
                    'name' => $this->request->input('name'),
                    'template_id' => $this->request->input('template_id'),
                    'category' => $this->request->input('category'),
                    'audience' => $this->request->input('audience'),
                    'recipient_source' => $this->request->input('recipient_source'),
                    'default_recipient' => $this->request->input('default_recipient'),
                    'payload_strategy' => $this->request->input('payload_strategy'),
                    'description' => $this->request->input('description'),
                    'guardrails_json' => $this->request->input('guardrails_json'),
                    'is_enabled' => $this->request->input('is_enabled'),
                    'dry_run_only' => $this->request->input('dry_run_only'),
                ],
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            $_SESSION['notification_automation_success'] =
                'Notification automation rule updated.';
        } catch (\Throwable $exception) {
            $_SESSION['notification_automation_error'] =
                $exception->getMessage()
                ?: 'Unable to update notification automation rule.';
        }

        $this->response->redirect('/admin/notification-automations');

        return null;
    }

    public function test(Request $request)
    {
        $id = (int) $request->route('rule_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-automations',
                'Security token expired. Please try again.'
            );
        }

        try {
            $result = $this->service->testRule(
                $id,
                [
                    'recipient' => $this->request->input('recipient'),
                    'payload_json' => $this->request->input('payload_json'),
                ],
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            if (($result['mode'] ?? '') === 'dry_run') {
                $_SESSION['notification_automation_success'] =
                    'Dry-run test rendered '
                    . $result['template_key']
                    . ' for '
                    . $result['recipient']
                    . '. No outbox message was created.';
            } else {
                $_SESSION['notification_automation_success'] =
                    'Automation queued '
                    . $result['template_key']
                    . ' for '
                    . $result['recipient']
                    . ' as outbox message #'
                    . $result['email_outbox_id']
                    . '.';
            }
        } catch (\Throwable $exception) {
            $_SESSION['notification_automation_error'] =
                $exception->getMessage()
                ?: 'Unable to test notification automation rule.';
        }

        $this->response->redirect('/admin/notification-automations');

        return null;
    }

    public function export()
    {
        $rows = $this->automations->exportRows();

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="notification-automation-rules-'
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
            'Rule ID',
            'Rule Key',
            'Name',
            'Event Key',
            'Template Key',
            'Category',
            'Audience',
            'Recipient Source',
            'Default Recipient',
            'Payload Strategy',
            'Enabled',
            'Dry Run Only',
            'Run Count',
            'Last Run',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['rule_key'] ?? '',
                $row['name'] ?? '',
                $row['event_key'] ?? '',
                $row['template_key'] ?? '',
                $row['category'] ?? '',
                $row['audience'] ?? '',
                $row['recipient_source'] ?? '',
                $row['default_recipient'] ?? '',
                $row['payload_strategy'] ?? '',
                ! empty($row['is_enabled']) ? 'yes' : 'no',
                ! empty($row['dry_run_only']) ? 'yes' : 'no',
                $row['run_count'] ?? '',
                $row['last_run_at'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * @return array<string, string>
     */
    private function filters(): array
    {
        return [
            'category' => trim((string) $this->request->input('category')),
            'audience' => trim((string) $this->request->input('audience')),
            'search' => trim((string) $this->request->input('search')),
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
        $_SESSION['notification_automation_error'] = $message;

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

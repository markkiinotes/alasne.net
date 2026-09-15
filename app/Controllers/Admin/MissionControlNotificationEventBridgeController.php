<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\MissionControlNotificationEventBridgeRepository;
use App\Services\Admin\MissionControlNotificationEventBridgeService;
use App\Services\Auth\CsrfService;

class MissionControlNotificationEventBridgeController extends Controller
{
    public function __construct(
        private MissionControlNotificationEventBridgeRepository $bridge,
        private MissionControlNotificationEventBridgeService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();

        return $this->view(
            'admin.notification-event-bridge.index',
            [
                'title' => 'Notification Event Bridge',
                'filters' => $filters,
                'dashboard' => $this->bridge->dashboard($filters),
                'sample_payloads' => $this->service->samplePayloads(),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('notification_event_bridge_success'),
                'error' => $this->flash('notification_event_bridge_error'),
                'result' => $this->flash('notification_event_bridge_result'),
            ],
            'admin'
        );
    }

    public function simulate()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-event-bridge',
                'Security token expired. Please try again.'
            );
        }

        try {
            $eventKey = trim((string) $this->request->input('event_key'));
            $payloadJson = trim((string) $this->request->input('payload_json'));
            $payload = [];

            if ($payloadJson !== '') {
                $payload = json_decode($payloadJson, true);

                if (! is_array($payload)) {
                    throw new \RuntimeException('Payload must be a valid JSON object.');
                }
            }

            $includeDisabled = ! empty($this->request->input('include_disabled'));
            $dryRun = ! empty($this->request->input('dry_run')) || $includeDisabled;

            $result = $this->service->handle(
                $eventKey,
                $payload,
                [
                    'event_source' => $this->request->input('event_source') ?: 'manual_simulator',
                    'recipient' => $this->request->input('recipient'),
                    'idempotency_key' => $this->request->input('idempotency_key'),
                    'include_disabled' => $includeDisabled,
                    'dry_run' => $dryRun,
                ],
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            $_SESSION['notification_event_bridge_success'] =
                'Event bridge simulation completed: '
                . $result['message'];

            $_SESSION['notification_event_bridge_result'] = $result;
        } catch (\Throwable $exception) {
            $_SESSION['notification_event_bridge_error'] =
                $exception->getMessage()
                ?: 'Unable to simulate notification event.';
        }

        $this->response->redirect('/admin/notification-event-bridge');

        return null;
    }

    public function export()
    {
        $rows = $this->bridge->exportRows();

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="notification-event-bridge-runs-'
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
            'Event Key',
            'Event Source',
            'Status',
            'Idempotency Key',
            'Matched Rules',
            'Queued Dispatches',
            'Dry Run Events',
            'Skipped Rules',
            'Failed Rules',
            'Message',
            'Created At',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['event_key'] ?? '',
                $row['event_source'] ?? '',
                $row['status'] ?? '',
                $row['idempotency_key'] ?? '',
                $row['matched_rules'] ?? '',
                $row['queued_dispatches'] ?? '',
                $row['dry_run_events'] ?? '',
                $row['skipped_rules'] ?? '',
                $row['failed_rules'] ?? '',
                $row['message'] ?? '',
                $row['created_at'] ?? '',
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
            'event_key' => trim((string) $this->request->input('event_key')),
            'status' => trim((string) $this->request->input('status')),
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
        $_SESSION['notification_event_bridge_error'] = $message;

        $this->response->redirect($path);

        return null;
    }

    private function flash(string $key): mixed
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

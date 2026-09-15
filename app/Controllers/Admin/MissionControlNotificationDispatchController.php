<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\MissionControlNotificationDispatchRepository;
use App\Services\Admin\MissionControlNotificationDispatchService;
use App\Services\Auth\CsrfService;

class MissionControlNotificationDispatchController extends Controller
{
    public function __construct(
        private MissionControlNotificationDispatchRepository $dispatches,
        private MissionControlNotificationDispatchService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view(
            'admin.notification-dispatches.index',
            [
                'title' => 'Notification Dispatch Center',
                'dashboard' => $this->dispatches->dashboard(),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('notification_dispatch_success'),
                'error' => $this->flash('notification_dispatch_error'),
            ],
            'admin'
        );
    }

    public function queue()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-dispatches',
                'Security token expired. Please try again.'
            );
        }

        try {
            $result = $this->service->queue([
                'template_id' => $this->request->input('template_id'),
                'recipient' => $this->request->input('recipient'),
                'payload_json' => $this->request->input('payload_json'),
            ], $this->currentUserId());

            $this->csrf->regenerate();

            $_SESSION['notification_dispatch_success'] =
                'Queued '
                . $result['template_key']
                . ' for '
                . $result['recipient']
                . ' as outbox message #'
                . $result['email_outbox_id']
                . '.';
        } catch (\Throwable $exception) {
            $_SESSION['notification_dispatch_error'] =
                $exception->getMessage()
                ?: 'Unable to queue notification.';
        }

        $this->response->redirect('/admin/notification-dispatches');

        return null;
    }

    public function export()
    {
        $rows = $this->dispatches->exportRows();

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="notification-dispatches-'
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
            'Dispatch ID',
            'Template Key',
            'Recipient',
            'Subject',
            'Status',
            'Email Outbox ID',
            'Queued At',
            'Error',
            'Created At',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['template_key'] ?? '',
                $row['recipient'] ?? '',
                $row['subject'] ?? '',
                $row['status'] ?? '',
                $row['email_outbox_id'] ?? '',
                $row['queued_at'] ?? '',
                $row['error_message'] ?? '',
                $row['created_at'] ?? '',
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

    private function redirectWithError(
        string $path,
        string $message
    ) {
        $_SESSION['notification_dispatch_error'] = $message;

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

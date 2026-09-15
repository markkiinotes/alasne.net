<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\MissionControlEmailQueueRepository;
use App\Services\Admin\MissionControlEmailQueueService;
use App\Services\Auth\CsrfService;

class MissionControlEmailQueueController extends Controller
{
    public function __construct(
        private MissionControlEmailQueueRepository $emails,
        private MissionControlEmailQueueService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view(
            'admin.email-queue.index',
            [
                'title' => 'Email Queue Processing',
                'dashboard' => $this->emails->dashboard(),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_email_queue_success'),
                'error' => $this->flash('mission_control_email_queue_error'),
                'default_transport' => $this->defaultTransport(),
            ],
            'admin'
        );
    }

    public function process()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/email-queue',
                'Security token expired. Please try again.'
            );
        }

        try {
            $result = $this->service->process([
                'limit' => $this->request->input('limit') ?: 10,
                'transport' => $this->request->input('transport') ?: 'log',
                'dry_run' => (bool) $this->request->input('dry_run'),
            ]);

            $this->csrf->regenerate();

            $_SESSION['mission_control_email_queue_success'] =
                'Email queue processed: '
                . (int) $result['processed']
                . ' processed, '
                . (int) $result['logged']
                . ' logged, '
                . (int) $result['sent']
                . ' sent, '
                . (int) $result['failed']
                . ' failed.';
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_email_queue_error'] =
                $exception->getMessage()
                ?: 'Unable to process email queue.';
        }

        $this->response->redirect('/admin/email-queue');

        return null;
    }

    public function export()
    {
        $attempts = $this->emails->recentAttempts(1000);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="mission-control-email-queue-attempts-'
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
            'Attempt ID',
            'Email Outbox ID',
            'Status',
            'Transport',
            'Recipient',
            'Subject',
            'Message',
            'Error',
            'Attempted At',
        ]);

        foreach ($attempts as $attempt) {
            fputcsv($output, [
                $attempt['id'] ?? '',
                $attempt['email_outbox_id'] ?? '',
                $attempt['status'] ?? '',
                $attempt['transport'] ?? '',
                $attempt['recipient'] ?? '',
                $attempt['subject'] ?? '',
                $attempt['message'] ?? '',
                $attempt['error_message'] ?? '',
                $attempt['attempted_at'] ?? '',
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

    private function defaultTransport(): string
    {
        return trim(
            (string) (
                $_ENV['EMAIL_QUEUE_TRANSPORT']
                ?? $_SERVER['EMAIL_QUEUE_TRANSPORT']
                ?? getenv('EMAIL_QUEUE_TRANSPORT')
                ?: 'log'
            )
        );
    }

    private function redirectWithError(
        string $path,
        string $message
    ) {
        $_SESSION['mission_control_email_queue_error'] = $message;

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

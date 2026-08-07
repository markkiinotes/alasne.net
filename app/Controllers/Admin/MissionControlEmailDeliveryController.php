<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\MissionControlEmailQueueRepository;
use App\Services\Admin\MissionControlSmtpMailService;
use App\Services\Auth\CsrfService;

class MissionControlEmailDeliveryController extends Controller
{
    public function __construct(
        private MissionControlSmtpMailService $smtp,
        private MissionControlEmailQueueRepository $emails,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        return $this->view(
            'admin.email-delivery.index',
            [
                'title' => 'Email Delivery & SMTP',
                'settings' => $this->smtp->settings(),
                'configured' => $this->smtp->isConfigured(),
                'attempts' => $this->emails->recentAttempts(25),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_email_delivery_success'),
                'error' => $this->flash('mission_control_email_delivery_error'),
            ],
            'admin'
        );
    }

    public function test()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/email-delivery',
                'Security token expired. Please try again.'
            );
        }

        $recipient = trim((string) $this->request->input('recipient'));

        try {
            $this->smtp->sendTest($recipient);

            $this->emails->recordAttempt([
                'email_outbox_id' => null,
                'status' => 'sent',
                'transport' => 'smtp',
                'recipient' => $recipient,
                'subject' => 'Alasne Mission Control SMTP Test',
                'message' => 'SMTP test message sent successfully.',
                'error_message' => null,
            ]);

            $this->csrf->regenerate();

            $_SESSION['mission_control_email_delivery_success'] =
                'SMTP test email sent successfully.';
        } catch (\Throwable $exception) {
            $this->emails->recordAttempt([
                'email_outbox_id' => null,
                'status' => 'failed',
                'transport' => 'smtp',
                'recipient' => $recipient,
                'subject' => 'Alasne Mission Control SMTP Test',
                'message' => 'SMTP test failed.',
                'error_message' => $exception->getMessage(),
            ]);

            $_SESSION['mission_control_email_delivery_error'] =
                $exception->getMessage()
                ?: 'SMTP test failed.';
        }

        $this->response->redirect('/admin/email-delivery');

        return null;
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
        $_SESSION['mission_control_email_delivery_error'] = $message;

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

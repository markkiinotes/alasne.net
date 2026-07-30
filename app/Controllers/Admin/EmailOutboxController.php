<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\EmailOutboxRepository;
use App\Services\Mail\EmailOutboxSender;

class EmailOutboxController extends Controller
{
    public function __construct(
        private EmailOutboxRepository $emails,
        private EmailOutboxSender $sender
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = [
            'status' => trim((string) $this->request->input('status')),
            'q' => trim((string) $this->request->input('q')),
        ];

        $success = $_SESSION['email_outbox_success'] ?? null;
        $error = $_SESSION['email_outbox_error'] ?? null;

        unset($_SESSION['email_outbox_success'], $_SESSION['email_outbox_error']);

        return $this->view('admin.email-outbox.index', [
            'title' => 'Email Outbox',
            'emails' => $this->emails->all($filters, 100),
            'filters' => $filters,
            'statuses' => $this->emails->statuses(),
            'counts' => $this->emails->counts(),
            'success' => $success,
            'error' => $error,
        ], 'admin');
    }

    public function show(Request $request)
    {
        $id = (int) $request->route('id');

        $email = $this->emails->find($id);

        if (! $email) {
            http_response_code(404);
            return '404 - Email not found';
        }

        return $this->view('admin.email-outbox.show', [
            'title' => 'Email Preview',
            'email' => $email,
        ], 'admin');
    }

    public function sendPending()
    {
        $results = $this->sender->sendPending();

        if ($results['failed'] > 0) {
            $_SESSION['email_outbox_error'] = implode(' ', $results['messages']);
        } else {
            $_SESSION['email_outbox_success'] = implode(' ', $results['messages']);
        }

        $this->response->redirect('/admin/email-outbox');
    }
}
<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\MissionControlNotificationTemplateRepository;
use App\Services\Admin\MissionControlNotificationTemplateService;
use App\Services\Auth\CsrfService;

class MissionControlNotificationTemplateController extends Controller
{
    public function __construct(
        private MissionControlNotificationTemplateRepository $templates,
        private MissionControlNotificationTemplateService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();

        return $this->view(
            'admin.notification-templates.index',
            [
                'title' => 'Notification Template Center',
                'filters' => $filters,
                'dashboard' => $this->templates->dashboard($filters),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('notification_template_success'),
                'error' => $this->flash('notification_template_error'),
            ],
            'admin'
        );
    }

    public function create()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-templates',
                'Security token expired. Please try again.'
            );
        }

        try {
            $id = $this->templates->create(
                $this->templateInput(),
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            $_SESSION['notification_template_success'] =
                'Notification template created.';

            $this->response->redirect('/admin/notification-templates/' . $id);
        } catch (\Throwable $exception) {
            $_SESSION['notification_template_error'] =
                $exception->getMessage()
                ?: 'Unable to create template.';

            $this->response->redirect('/admin/notification-templates');
        }

        return null;
    }

    public function edit(Request $request)
    {
        $id = (int) $request->route('template_id');
        $template = $this->templates->find($id);

        if (! $template) {
            http_response_code(404);

            return '404 - Notification template not found';
        }

        $preview = $_SESSION['notification_template_preview'] ?? null;
        unset($_SESSION['notification_template_preview']);

        if (! is_array($preview)) {
            $preview = $this->service->preview(
                $id,
                null,
                $this->currentUserId()
            );
        }

        return $this->view(
            'admin.notification-templates.edit',
            [
                'title' => 'Edit Notification Template',
                'template' => $template,
                'preview' => $preview,
                'versions' => $this->templates->versions($id),
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('notification_template_success'),
                'error' => $this->flash('notification_template_error'),
            ],
            'admin'
        );
    }

    public function update(Request $request)
    {
        $id = (int) $request->route('template_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-templates/' . $id,
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->templates->update(
                $id,
                $this->templateInput(),
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            $_SESSION['notification_template_success'] =
                'Notification template updated.';

            $this->response->redirect('/admin/notification-templates/' . $id);
        } catch (\Throwable $exception) {
            $_SESSION['notification_template_error'] =
                $exception->getMessage()
                ?: 'Unable to update template.';

            $this->response->redirect('/admin/notification-templates/' . $id);
        }

        return null;
    }

    public function preview(Request $request)
    {
        $id = (int) $request->route('template_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/notification-templates/' . $id,
                'Security token expired. Please try again.'
            );
        }

        try {
            $payload = $this->service->normalizedPayloadFromJson(
                (string) $this->request->input('preview_payload_json')
            );

            $preview = $this->service->preview(
                $id,
                $payload,
                $this->currentUserId()
            );

            $_SESSION['notification_template_preview'] = $preview;
            $_SESSION['notification_template_success'] =
                'Preview generated with custom payload.';
        } catch (\Throwable $exception) {
            $_SESSION['notification_template_error'] =
                $exception->getMessage()
                ?: 'Unable to generate preview.';
        }

        $this->response->redirect('/admin/notification-templates/' . $id);

        return null;
    }

    public function export()
    {
        $rows = $this->templates->exportRows();

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="notification-templates-'
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
            'Template Key',
            'Name',
            'Category',
            'Audience',
            'Channel',
            'Enabled',
            'Subject Template',
            'Variables',
            'Last Previewed',
            'Updated',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['id'] ?? '',
                $row['template_key'] ?? '',
                $row['name'] ?? '',
                $row['category'] ?? '',
                $row['audience'] ?? '',
                $row['channel'] ?? '',
                ! empty($row['is_enabled']) ? 'yes' : 'no',
                $row['subject_template'] ?? '',
                $row['variables_json'] ?? '',
                $row['last_previewed_at'] ?? '',
                $row['updated_at'] ?? '',
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
            'category' => trim((string) $this->request->input('category')),
            'audience' => trim((string) $this->request->input('audience')),
            'search' => trim((string) $this->request->input('search')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function templateInput(): array
    {
        return [
            'template_key' => $this->request->input('template_key'),
            'name' => $this->request->input('name'),
            'category' => $this->request->input('category'),
            'audience' => $this->request->input('audience'),
            'description' => $this->request->input('description'),
            'subject_template' => $this->request->input('subject_template'),
            'body_text_template' => $this->request->input('body_text_template'),
            'body_html_template' => $this->request->input('body_html_template'),
            'variables_json' => $this->request->input('variables_json'),
            'sample_payload_json' => $this->request->input('sample_payload_json'),
            'change_note' => $this->request->input('change_note'),
            'is_enabled' => $this->request->input('is_enabled'),
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
        $_SESSION['notification_template_error'] = $message;

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

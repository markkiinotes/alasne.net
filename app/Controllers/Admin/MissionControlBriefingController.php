<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\MissionControlBriefingRepository;
use App\Services\Admin\MissionControlBriefingService;
use App\Services\Auth\CsrfService;

class MissionControlBriefingController extends Controller
{
    public function __construct(
        private MissionControlBriefingRepository $briefings,
        private MissionControlBriefingService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $preview = $this->service->preview($this->filters());

        return $this->view(
            'admin.briefings.index',
            [
                'title' => 'Alert Digest & Admin Briefing',
                'preview' => $preview,
                'filters' => $preview['filters'],
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_briefing_success'),
                'error' => $this->flash('mission_control_briefing_error'),
            ],
            'admin'
        );
    }

    public function save()
    {
        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/briefings',
                'Security token expired. Please try again.'
            );
        }

        try {
            $id = $this->service->savePreview(
                $this->filters(),
                $this->currentUserId()
            );

            $this->csrf->regenerate();

            $_SESSION['mission_control_briefing_success'] =
                'Mission Control briefing saved.';

            $this->response->redirect('/admin/briefings/' . $id);
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_briefing_error'] =
                $exception->getMessage()
                ?: 'Unable to save briefing.';

            $this->response->redirect('/admin/briefings');
        }

        return null;
    }

    public function show(Request $request)
    {
        $id = (int) $request->route('briefing_id');
        $saved = $this->service->saved($id);

        if (! $saved['briefing']) {
            http_response_code(404);

            return '404 - Briefing not found';
        }

        return $this->view(
            'admin.briefings.show',
            [
                'title' => 'Saved Mission Control Briefing',
                'briefing' => $saved['briefing'],
                'items' => $saved['items'],
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('mission_control_briefing_success'),
                'error' => $this->flash('mission_control_briefing_error'),
            ],
            'admin'
        );
    }

    public function delete(Request $request)
    {
        $id = (int) $request->route('briefing_id');

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                '/admin/briefings/' . $id,
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->briefings->delete($id);
            $this->csrf->regenerate();

            $_SESSION['mission_control_briefing_success'] =
                'Briefing deleted.';
            $this->response->redirect('/admin/briefings');
        } catch (\Throwable $exception) {
            $_SESSION['mission_control_briefing_error'] =
                $exception->getMessage()
                ?: 'Unable to delete briefing.';
            $this->response->redirect('/admin/briefings/' . $id);
        }

        return null;
    }

    public function exportPreview()
    {
        $this->exportRows(
            $this->service->exportRowsFromPreview($this->filters()),
            'mission-control-briefing-preview-' . date('Y-m-d-His') . '.csv'
        );

        return null;
    }

    public function exportSaved(Request $request)
    {
        $id = (int) $request->route('briefing_id');

        $this->exportRows(
            $this->service->exportRowsFromSaved($id),
            'mission-control-briefing-' . $id . '-' . date('Y-m-d-His') . '.csv'
        );

        return null;
    }

    private function exportRows(array $rows, string $filename): void
    {
        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="' . $filename . '"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            exit;
        }

        fputcsv($output, [
            'Section',
            'Name',
            'Value',
            'Detail',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['section'] ?? '',
                $row['name'] ?? '',
                $row['value'] ?? '',
                $row['detail'] ?? '',
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
            'period_start' => $this->request->input('period_start'),
            'period_end' => $this->request->input('period_end'),
            'store_id' => $this->request->input('store_id'),
            'supplier_id' => $this->request->input('supplier_id'),
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
        $_SESSION['mission_control_briefing_error'] = $message;

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

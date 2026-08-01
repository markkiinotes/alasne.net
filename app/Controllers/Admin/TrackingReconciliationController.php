<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\TrackingReconciliationRepository;
use App\Services\Auth\CsrfService;
use App\Services\Tracking\TrackingReconciliationService;
use RuntimeException;

class TrackingReconciliationController extends Controller
{
    private const MAX_UPLOAD_BYTES = 10485760;

    public function __construct(
        private TrackingReconciliationRepository $tracking,
        private TrackingReconciliationService $service,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();
        $dashboard = $this->tracking->dashboard($filters);

        return $this->view(
            'admin.tracking-reconciliation.index',
            [
                'title' =>
                    'Automated Tracking Reconciliation',
                'filters' => $filters,
                'stores' => $this->tracking->stores(),
                'suppliers' =>
                    $this->tracking->suppliers(
                        (int) $filters['store_id']
                    ),
                'dashboard' => $dashboard,
                'csrf_token' => $this->csrf->token(),
                'success' =>
                    $this->flash(
                        'tracking_reconciliation_success'
                    ),
                'error' =>
                    $this->flash(
                        'tracking_reconciliation_error'
                    ),
            ],
            'admin'
        );
    }

    public function upload()
    {
        $filters = $this->filters();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $file = $_FILES['tracking_csv'] ?? null;

            if (! is_array($file)) {
                throw new RuntimeException(
                    'Select a tracking CSV file to upload.'
                );
            }

            $uploadError =
                (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new RuntimeException(
                    $this->uploadErrorMessage($uploadError)
                );
            }

            $size = (int) ($file['size'] ?? 0);

            if (
                $size <= 0
                || $size > self::MAX_UPLOAD_BYTES
            ) {
                throw new RuntimeException(
                    'The tracking CSV must be between 1 byte and 10 MB.'
                );
            }

            $originalName = basename(
                (string) ($file['name'] ?? '')
            );

            if (
                strtolower(
                    pathinfo(
                        $originalName,
                        PATHINFO_EXTENSION
                    )
                ) !== 'csv'
            ) {
                throw new RuntimeException(
                    'Only CSV files are accepted.'
                );
            }

            $temporaryPath =
                (string) ($file['tmp_name'] ?? '');

            if (
                $temporaryPath === ''
                || ! is_uploaded_file($temporaryPath)
            ) {
                throw new RuntimeException(
                    'The uploaded file could not be verified.'
                );
            }

            $result = $this->service->importCsv(
                $temporaryPath,
                $originalName,
                (int) ($filters['store_id'] ?? 0) ?: null,
                (int) ($filters['supplier_id'] ?? 0) ?: null
            );

            $counts = $result['counts'];

            $this->csrf->regenerate();

            $_SESSION[
                'tracking_reconciliation_success'
            ] =
                'Tracking reconciliation '
                . $result['status']
                . ': '
                . (int) $counts['matched']
                . ' matched, '
                . (int) $counts['updated']
                . ' updated, '
                . (int) $counts['skipped']
                . ' skipped, '
                . (int) $counts['failed']
                . ' failed.';

            $this->response->redirect(
                '/admin/tracking-reconciliation/runs/'
                . (int) $result['run_id']
            );
        } catch (\Throwable $exception) {
            $_SESSION[
                'tracking_reconciliation_error'
            ] =
                $exception->getMessage()
                ?: 'Unable to reconcile tracking CSV.';

            $this->response->redirect(
                $this->indexUrl($filters)
            );
        }

        return null;
    }

    public function run(Request $request)
    {
        $runId = (int) $request->route('run_id');
        $run = $this->tracking->run($runId);

        if (! $run) {
            http_response_code(404);

            return '404 - Tracking reconciliation run not found';
        }

        return $this->view(
            'admin.tracking-reconciliation.run',
            [
                'title' =>
                    'Tracking Reconciliation Run #'
                    . $runId,
                'run' => $run,
                'rows' =>
                    $this->tracking->rowsForRun($runId),
            ],
            'admin'
        );
    }

    public function template()
    {
        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="tracking-reconciliation-template.csv"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        $output = fopen('php://output', 'wb');

        if ($output === false) {
            exit;
        }

        fputcsv($output, [
            'purchase_order_number',
            'supplier_order_id',
            'external_order_id',
            'supplier_reference',
            'carrier',
            'tracking_number',
            'tracking_url',
            'shipment_status',
            'shipped_date',
            'delivered_date',
            'notes',
        ]);

        fputcsv($output, [
            'PO-20260731-0001',
            'SUPPLIER-ORDER-123',
            '',
            '',
            'UPS',
            '1Z999AA10123456784',
            'https://www.ups.com/track?tracknum=1Z999AA10123456784',
            'in_transit',
            date('Y-m-d H:i:s'),
            '',
            'Example row',
        ]);

        fclose($output);
        exit;
    }

    public function exportQueue()
    {
        $filters = $this->filters();
        $queue = $this->tracking->queue($filters, 1000);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="tracking-reconciliation-queue-'
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
            'Supplier',
            'Supplier Code',
            'Purchase Order',
            'Customer Order',
            'PO Status',
            'Tracking Status',
            'Carrier',
            'Tracking Number',
            'Last Reconciled',
            'Action URL',
        ]);

        foreach ($queue as $row) {
            fputcsv($output, [
                $row['store_name'] ?? '',
                $row['supplier_name'] ?? '',
                $row['supplier_code'] ?? '',
                $row['purchase_order_number'] ?? '',
                $row['order_number'] ?? '',
                $row['status'] ?? '',
                $row['tracking_status'] ?? '',
                $row['shipping_carrier'] ?? '',
                $row['tracking_number'] ?? '',
                $row['last_tracking_reconciled_at'] ?? '',
                '/admin/purchase-orders/'
                    . (int) $row['id'],
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
            'store_id' => max(
                0,
                (int) $this->request->input('store_id')
            ),
            'supplier_id' => max(
                0,
                (int) $this->request->input('supplier_id')
            ),
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

        return '/admin/tracking-reconciliation'
            . ($query !== '' ? '?' . $query : '');
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }

    private function redirectWithError(
        string $path,
        string $message
    ) {
        $_SESSION[
            'tracking_reconciliation_error'
        ] = $message;

        $this->response->redirect($path);

        return null;
    }

    private function uploadErrorMessage(int $error): string
    {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'The uploaded tracking CSV exceeds the allowed size.',
            UPLOAD_ERR_PARTIAL =>
                'The tracking CSV upload was incomplete.',
            UPLOAD_ERR_NO_FILE =>
                'Select a tracking CSV file to upload.',
            default =>
                'The tracking CSV upload failed.',
        };
    }

    private function flash(string $key): ?string
    {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return $value;
    }
}

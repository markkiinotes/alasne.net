<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\SupplierIntegrationRepository;
use App\Services\Auth\CsrfService;
use App\Services\Suppliers\SupplierCatalogImportService;
use App\Services\Suppliers\SupplierProviderRegistry;
use RuntimeException;

class SupplierIntegrationController extends Controller
{
    private const MAX_UPLOAD_BYTES = 10485760;

    public function __construct(
        private SupplierIntegrationRepository $integrations,
        private SupplierProviderRegistry $providers,
        private SupplierCatalogImportService $imports,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function edit(Request $request)
    {
        $supplierId = (int) $request->route(
            'supplier_id'
        );

        $supplier =
            $this->integrations->supplier(
                $supplierId
            );

        if (! $supplier) {
            http_response_code(404);

            return '404 - Supplier not found';
        }

        $integration =
            $this->integrations->forSupplier(
                $supplierId
            );

        return $this->view(
            'admin.supplier-integrations.edit',
            [
                'title' =>
                    'Supplier Integration | '
                    . $supplier['name'],
                'supplier' => $supplier,
                'integration' => $integration,
                'providerOptions' =>
                    $this->providers->options(),
                'capabilities' =>
                    $this->providers
                        ->capabilities(),
                'environmentStatus' =>
                    $this->environmentStatus(
                        $integration
                    ),
                'syncRuns' =>
                    $this->integrations
                        ->syncRuns($supplierId),
                'csrf_token' =>
                    $this->csrf->token(),
                'success' =>
                    $this->flash(
                        'supplier_integration_success'
                    ),
                'error' =>
                    $this->flash(
                        'supplier_integration_error'
                    ),
            ],
            'admin'
        );
    }

    public function update(Request $request)
    {
        $supplierId = (int) $request->route(
            'supplier_id'
        );

        if (! $this->validateCsrf()) {
            return $this->redirectError(
                $supplierId,
                'Security token expired. Please try again.'
            );
        }

        $data = [
            'provider_code' => trim(
                (string) $this->request->input(
                    'provider_code'
                )
            ),
            'status' => trim(
                (string) $this->request->input(
                    'status',
                    'active'
                )
            ),
            'mode' => trim(
                (string) $this->request->input(
                    'mode',
                    'test'
                )
            ),
            'auto_prepare_orders' =>
                (string) $this->request->input(
                    'auto_prepare_orders'
                ) === '1',
            'auto_submit_orders' =>
                (string) $this->request->input(
                    'auto_submit_orders'
                ) === '1',
            'purchase_order_email' => trim(
                (string) $this->request->input(
                    'purchase_order_email'
                )
            ),
            'catalog_feed_url' => trim(
                (string) $this->request->input(
                    'catalog_feed_url'
                )
            ),
            'endpoint_url' => trim(
                (string) $this->request->input(
                    'endpoint_url'
                )
            ),
            'api_key_env' => trim(
                (string) $this->request->input(
                    'api_key_env'
                )
            ),
            'api_secret_env' => trim(
                (string) $this->request->input(
                    'api_secret_env'
                )
            ),
            'account_id_env' => trim(
                (string) $this->request->input(
                    'account_id_env'
                )
            ),
            'default_order_notes' => trim(
                (string) $this->request->input(
                    'default_order_notes'
                )
            ),
        ];

        try {
            $this->validateIntegration($data);

            $this->integrations->save(
                $supplierId,
                $data
            );

            $this->csrf->regenerate();

            $_SESSION[
                'supplier_integration_success'
            ] =
                'Supplier integration saved successfully.';
        } catch (\Throwable $exception) {
            $_SESSION[
                'supplier_integration_error'
            ] =
                $exception->getMessage()
                ?: 'Unable to save supplier integration.';
        }

        $this->response->redirect(
            '/admin/suppliers/'
            . $supplierId
            . '/integration'
        );
    }

    public function importCsv(Request $request)
    {
        $supplierId = (int) $request->route(
            'supplier_id'
        );

        if (! $this->validateCsrf()) {
            return $this->redirectError(
                $supplierId,
                'Security token expired. Please try again.'
            );
        }

        try {
            $syncType = trim(
                (string) $this->request->input(
                    'sync_type',
                    'catalog'
                )
            );

            $file = $_FILES[
                'supplier_csv'
            ] ?? null;

            if (! is_array($file)) {
                throw new RuntimeException(
                    'Select a CSV file to import.'
                );
            }

            $uploadError =
                (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);

            if ($uploadError !== UPLOAD_ERR_OK) {
                throw new RuntimeException(
                    $this->uploadErrorMessage(
                        $uploadError
                    )
                );
            }

            $size = (int) ($file['size'] ?? 0);

            if (
                $size <= 0
                || $size > self::MAX_UPLOAD_BYTES
            ) {
                throw new RuntimeException(
                    'The CSV must be between 1 byte and 10 MB.'
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
                || ! is_uploaded_file(
                    $temporaryPath
                )
            ) {
                throw new RuntimeException(
                    'The uploaded file could not be verified.'
                );
            }

            $result = $this->imports->importCsv(
                $supplierId,
                $temporaryPath,
                $originalName,
                $syncType
            );

            $counts = $result['counts'];

            $_SESSION[
                'supplier_integration_success'
            ] =
                ucfirst($syncType)
                . ' sync '
                . $result['status']
                . ': '
                . (int) $counts['created']
                . ' created, '
                . (int) $counts['updated']
                . ' updated, '
                . (int) $counts['skipped']
                . ' skipped, '
                . (int) $counts['failed']
                . ' failed.';

            $this->csrf->regenerate();
        } catch (\Throwable $exception) {
            $_SESSION[
                'supplier_integration_error'
            ] =
                $exception->getMessage()
                ?: 'Unable to import supplier CSV.';
        }

        $this->response->redirect(
            '/admin/suppliers/'
            . $supplierId
            . '/integration'
        );
    }

    public function template(Request $request)
    {
        $supplierId = (int) $request->route(
            'supplier_id'
        );

        if (! $this->integrations->supplier(
            $supplierId
        )) {
            http_response_code(404);

            return '404 - Supplier not found';
        }

        $syncType = trim(
            (string) $this->request->input(
                'sync_type',
                'catalog'
            )
        );

        $handle = fopen(
            'php://output',
            'wb'
        );

        if ($handle === false) {
            throw new RuntimeException(
                'Unable to generate CSV template.'
            );
        }

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="supplier-'
            . $supplierId
            . '-'
            . (
                $syncType === 'inventory'
                    ? 'inventory'
                    : 'catalog'
            )
            . '-template.csv"'
        );
        header('Pragma: no-cache');
        header('Expires: 0');

        if ($syncType === 'inventory') {
            fputcsv($handle, [
                'supplier_sku',
                'wholesale_cost',
                'currency',
                'available_quantity',
                'stock_status',
                'source_updated_at',
            ]);

            fputcsv($handle, [
                'SUP-EXAMPLE-001',
                '12.50',
                'USD',
                '125',
                'in_stock',
                date('Y-m-d H:i:s'),
            ]);
        } else {
            fputcsv($handle, [
                'supplier_sku',
                'product_sku',
                'product_id',
                'provider_product_id',
                'wholesale_cost',
                'currency',
                'available_quantity',
                'stock_status',
                'lead_time_min',
                'lead_time_max',
                'minimum_order_quantity',
                'pack_size',
                'is_preferred',
                'priority',
                'source_updated_at',
            ]);

            fputcsv($handle, [
                'SUP-EXAMPLE-001',
                'STORE-SKU-001',
                '',
                'provider-item-001',
                '12.50',
                'USD',
                '125',
                'in_stock',
                '1',
                '3',
                '1',
                '1',
                'yes',
                '10',
                date('Y-m-d H:i:s'),
            ]);
        }

        fclose($handle);

        exit;
    }

    public function syncRun(Request $request)
    {
        $supplierId = (int) $request->route(
            'supplier_id'
        );
        $runId = (int) $request->route(
            'run_id'
        );

        $supplier =
            $this->integrations->supplier(
                $supplierId
            );
        $run =
            $this->integrations->syncRun(
                $runId
            );

        if (
            ! $supplier
            || ! $run
            || (int) $run['supplier_id']
                !== $supplierId
        ) {
            http_response_code(404);

            return '404 - Supplier sync run not found';
        }

        return $this->view(
            'admin.supplier-integrations.sync-run',
            [
                'title' =>
                    'Supplier Sync Run #'
                    . $runId,
                'supplier' => $supplier,
                'run' => $run,
                'errors' =>
                    $this->integrations
                        ->syncErrors($runId),
            ],
            'admin'
        );
    }

    private function validateIntegration(
        array $data
    ): void {
        $providerCode =
            (string) $data['provider_code'];

        $adapter = $this->providers->get(
            $providerCode
        );

        if (! in_array(
            $data['status'],
            ['active', 'inactive'],
            true
        )) {
            throw new RuntimeException(
                'Invalid integration status.'
            );
        }

        if (! in_array(
            $data['mode'],
            ['test', 'live'],
            true
        )) {
            throw new RuntimeException(
                'Invalid integration mode.'
            );
        }

        if (
            $data['purchase_order_email'] !== ''
            && ! filter_var(
                $data['purchase_order_email'],
                FILTER_VALIDATE_EMAIL
            )
        ) {
            throw new RuntimeException(
                'Enter a valid purchase-order email address.'
            );
        }

        foreach ([
            'catalog_feed_url',
            'endpoint_url',
        ] as $urlField) {
            if (
                $data[$urlField] !== ''
                && ! filter_var(
                    $data[$urlField],
                    FILTER_VALIDATE_URL
                )
            ) {
                throw new RuntimeException(
                    'Enter a valid URL for '
                    . str_replace('_', ' ', $urlField)
                    . '.'
                );
            }
        }

        foreach ([
            'api_key_env',
            'api_secret_env',
            'account_id_env',
        ] as $environmentField) {
            $value = $data[$environmentField];

            if (
                $value !== ''
                && ! preg_match(
                    '/^[A-Za-z_][A-Za-z0-9_]*$/',
                    $value
                )
            ) {
                throw new RuntimeException(
                    'Environment variable names may contain only letters, numbers, and underscores and may not begin with a number.'
                );
            }
        }

        $errors =
            $adapter->validateIntegration(
                $data
            );

        if (! empty($errors)) {
            throw new RuntimeException(
                implode(' ', $errors)
            );
        }
    }

    private function environmentStatus(
        array $integration
    ): array {
        $status = [];

        foreach ([
            'api_key_env',
            'api_secret_env',
            'account_id_env',
        ] as $field) {
            $name = trim(
                (string) (
                    $integration[$field] ?? ''
                )
            );

            $status[$field] = [
                'name' => $name,
                'configured' =>
                    $name !== ''
                    && $this->environmentValue(
                        $name
                    ) !== null,
            ];
        }

        return $status;
    }

    private function environmentValue(
        string $name
    ): ?string {
        $value = $_ENV[$name]
            ?? $_SERVER[$name]
            ?? getenv($name);

        if (
            $value === false
            || $value === null
            || trim((string) $value) === ''
        ) {
            return null;
        }

        return (string) $value;
    }

    private function uploadErrorMessage(
        int $error
    ): string {
        return match ($error) {
            UPLOAD_ERR_INI_SIZE,
            UPLOAD_ERR_FORM_SIZE =>
                'The uploaded CSV exceeds the allowed size.',
            UPLOAD_ERR_PARTIAL =>
                'The CSV upload was incomplete.',
            UPLOAD_ERR_NO_FILE =>
                'Select a CSV file to import.',
            default =>
                'The CSV upload failed.',
        };
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input(
                '_csrf_token'
            )
        );
    }

    private function redirectError(
        int $supplierId,
        string $message
    ) {
        $_SESSION[
            'supplier_integration_error'
        ] = $message;

        $this->response->redirect(
            '/admin/suppliers/'
            . $supplierId
            . '/integration'
        );

        return null;
    }

    private function flash(
        string $key
    ): ?string {
        $value = $_SESSION[$key] ?? null;

        unset($_SESSION[$key]);

        return $value;
    }
}

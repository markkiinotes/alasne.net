<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\SupplierPerformanceRepository;
use App\Services\Auth\CsrfService;

class SupplierPerformanceController extends Controller
{
    public function __construct(
        private SupplierPerformanceRepository $performance,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();
        $dashboard = $this->performance->dashboard($filters);

        return $this->view(
            'admin.supplier-performance.index',
            [
                'title' => 'Supplier Performance Reporting',
                'filters' => $filters,
                'stores' => $this->performance->stores(),
                'suppliers' => $this->performance->suppliers(
                    (int) $filters['store_id']
                ),
                'dashboard' => $dashboard,
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('supplier_performance_success'),
                'error' => $this->flash('supplier_performance_error'),
            ],
            'admin'
        );
    }

    public function show(Request $request)
    {
        $supplierId = (int) $request->route('supplier_id');
        $filters = $this->filters();
        $detail = $this->performance->supplierDetail(
            $supplierId,
            $filters
        );

        if (! $detail) {
            http_response_code(404);

            return '404 - Supplier performance not found';
        }

        return $this->view(
            'admin.supplier-performance.show',
            [
                'title' => 'Supplier Performance | '
                    . $detail['scorecard']['supplier_name'],
                'filters' => array_merge(
                    $filters,
                    ['supplier_id' => $supplierId]
                ),
                'detail' => $detail,
                'csrf_token' => $this->csrf->token(),
                'success' => $this->flash('supplier_performance_success'),
                'error' => $this->flash('supplier_performance_error'),
            ],
            'admin'
        );
    }

    public function review(Request $request)
    {
        $supplierId = (int) $request->route('supplier_id');
        $filters = $this->filtersFromRequest();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $status = trim(
                (string) $this->request->input('status')
            );
            $note = trim(
                (string) $this->request->input('review_note')
            );

            $this->performance->recordReview(
                $supplierId,
                $filters,
                $status,
                $note
            );

            $this->csrf->regenerate();

            $_SESSION['supplier_performance_success'] =
                'Supplier performance review saved.';
        } catch (\Throwable $exception) {
            $_SESSION['supplier_performance_error'] =
                $exception->getMessage()
                ?: 'Unable to save supplier performance review.';
        }

        $this->response->redirect($this->indexUrl($filters));
    }

    public function export()
    {
        $filters = $this->filters();
        $rows = $this->performance->exportRows($filters);

        $filename = 'supplier-performance-'
            . date('Y-m-d-His')
            . '.csv';

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="'
            . $filename
            . '"'
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
            'Supplier Status',
            'Purchase Orders',
            'Customer Orders',
            'Revenue',
            'Supplier Cost',
            'Gross Profit',
            'Margin %',
            'Score',
            'Recommendation',
            'Performance Review Status',
            'Late POs',
            'Failed Submissions',
            'Missing Tracking',
            'Open Exceptions',
            'Returns',
            'Delivery Rate %',
            'Average Hours To Ship',
            'Average Hours To Deliver',
            'Risk Notes',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['store_name'] ?? '',
                $row['supplier_name'] ?? '',
                $row['supplier_code'] ?? '',
                $row['supplier_status'] ?? '',
                $row['purchase_order_count'] ?? 0,
                $row['customer_order_count'] ?? 0,
                $row['revenue'] ?? 0,
                $row['supplier_cost'] ?? 0,
                $row['gross_profit'] ?? 0,
                $row['margin_percent'] ?? 0,
                $row['score'] ?? 0,
                $row['recommendation'] ?? '',
                $row['performance_status'] ?? '',
                $row['late_purchase_order_count'] ?? 0,
                $row['failed_submission_count'] ?? 0,
                $row['missing_tracking_count'] ?? 0,
                $row['open_exception_count'] ?? 0,
                $row['return_count'] ?? 0,
                $row['delivery_rate'] ?? 0,
                $row['avg_hours_to_ship'] ?? '',
                $row['avg_hours_to_deliver'] ?? '',
                implode(' | ', $row['risk_notes'] ?? []),
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
            'store_id' => max(0, (int) $this->request->input('store_id')),
            'supplier_id' => max(0, (int) $this->request->input('supplier_id')),
            'recommendation' => trim((string) $this->request->input('recommendation')),
            'lookback_days' => max(1, (int) $this->request->input('lookback_days', 90)),
            'date_from' => trim((string) $this->request->input('date_from')),
            'date_to' => trim((string) $this->request->input('date_to')),
            'target_margin' => max(0, (float) $this->request->input('target_margin', 25)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(): array
    {
        return [
            'store_id' => max(0, (int) $this->request->input('filter_store_id')),
            'supplier_id' => max(0, (int) $this->request->input('filter_supplier_id')),
            'recommendation' => trim((string) $this->request->input('filter_recommendation')),
            'lookback_days' => max(1, (int) $this->request->input('filter_lookback_days', 90)),
            'date_from' => trim((string) $this->request->input('filter_date_from')),
            'date_to' => trim((string) $this->request->input('filter_date_to')),
            'target_margin' => max(0, (float) $this->request->input('filter_target_margin', 25)),
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

        return '/admin/supplier-performance'
            . ($query !== '' ? '?' . $query : '');
    }

    private function validateCsrf(): bool
    {
        return $this->csrf->validate(
            (string) $this->request->input('_csrf_token')
        );
    }

    private function redirectWithError(string $path, string $message)
    {
        $_SESSION['supplier_performance_error'] = $message;
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

<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Core\Request;
use App\Repositories\ProductSourcingRepository;
use App\Services\Auth\CsrfService;

class ProductSourcingController extends Controller
{
    public function __construct(
        private ProductSourcingRepository $sourcing,
        private CsrfService $csrf
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();
        $dashboard =
            $this->sourcing->dashboard($filters);

        return $this->view(
            'admin.product-sourcing.index',
            [
                'title' =>
                    'Product Sourcing & Profitability Scanner',
                'filters' => $filters,
                'stores' => $this->sourcing->stores(),
                'suppliers' =>
                    $this->sourcing->suppliers(
                        (int) $filters['store_id']
                    ),
                'dashboard' => $dashboard,
                'csrf_token' => $this->csrf->token(),
                'success' =>
                    $this->flash(
                        'product_sourcing_success'
                    ),
                'error' =>
                    $this->flash(
                        'product_sourcing_error'
                    ),
            ],
            'admin'
        );
    }

    public function saveRules()
    {
        $storeId = max(
            0,
            (int) $this->request->input('store_id')
        );

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl(['store_id' => $storeId]),
                'Security token expired. Please try again.'
            );
        }

        try {
            $this->sourcing->saveRules(
                $storeId,
                $this->rulesInput()
            );

            $this->csrf->regenerate();

            $_SESSION[
                'product_sourcing_success'
            ] = 'Product sourcing rules saved.';
        } catch (\Throwable $exception) {
            $_SESSION[
                'product_sourcing_error'
            ] =
                $exception->getMessage()
                ?: 'Unable to save product sourcing rules.';
        }

        $this->response->redirect(
            $this->indexUrl(['store_id' => $storeId])
        );
    }

    public function review(Request $request)
    {
        $supplierProductId = (int) $request->route(
            'supplier_product_id'
        );

        $filters = $this->filtersFromRequest();

        if (! $this->validateCsrf()) {
            return $this->redirectWithError(
                $this->indexUrl($filters),
                'Security token expired. Please try again.'
            );
        }

        try {
            $status = trim(
                (string) $this->request->input(
                    'status'
                )
            );
            $note = trim(
                (string) $this->request->input(
                    'review_note'
                )
            );

            $this->sourcing->recordReview(
                $supplierProductId,
                $status,
                $note,
                $filters
            );

            $this->csrf->regenerate();

            $_SESSION[
                'product_sourcing_success'
            ] =
                'Product sourcing review saved.';
        } catch (\Throwable $exception) {
            $_SESSION[
                'product_sourcing_error'
            ] =
                $exception->getMessage()
                ?: 'Unable to save sourcing review.';
        }

        $this->response->redirect(
            $this->indexUrl($filters)
        );
    }

    public function export()
    {
        $filters = $this->filters();
        $rows = $this->sourcing->exportRows($filters);

        $filename = 'product-sourcing-'
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
            'Product',
            'Store SKU',
            'Supplier SKU',
            'Retail Price',
            'Supplier Cost',
            'Estimated Shipping',
            'Payment Fee',
            'Return Allowance',
            'Discount Allowance',
            'Ad Spend Target',
            'Gross Profit',
            'Gross Margin %',
            'Net Profit',
            'Net Margin %',
            'Break-even Ad Spend',
            'Suggested Minimum Price',
            'Suggested Target Price',
            'Score',
            'Recommendation',
            'Review Status',
            'Stock Status',
            'Available Quantity',
            'Risk Notes',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['store_name'] ?? '',
                $row['supplier_name'] ?? '',
                $row['supplier_code'] ?? '',
                $row['product_name'] ?? '',
                $row['product_sku'] ?? '',
                $row['supplier_sku'] ?? '',
                $row['retail_price'] ?? '',
                $row['supplier_cost'] ?? '',
                $row['estimated_shipping'] ?? '',
                $row['payment_fee'] ?? '',
                $row['return_allowance'] ?? '',
                $row['discount_allowance'] ?? '',
                $row['ad_spend_target'] ?? '',
                $row['gross_profit'] ?? '',
                $row['gross_margin_percent'] ?? '',
                $row['net_profit'] ?? '',
                $row['net_margin_percent'] ?? '',
                $row['break_even_ad_spend'] ?? '',
                $row['suggested_min_price'] ?? '',
                $row['suggested_target_price'] ?? '',
                $row['score'] ?? '',
                $row['recommendation'] ?? '',
                $row['review_status'] ?? '',
                $row['stock_status'] ?? '',
                $row['available_quantity'] ?? '',
                implode(
                    ' | ',
                    $row['risk_notes'] ?? []
                ),
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * @return array<string, mixed>
     */
    private function rulesInput(): array
    {
        $fields = [
            'min_gross_margin_percent',
            'min_net_margin_percent',
            'target_net_margin_percent',
            'minimum_profit_amount',
            'payment_fee_percent',
            'payment_fixed_fee',
            'return_allowance_percent',
            'discount_allowance_percent',
            'ad_spend_percent',
            'shipping_allowance',
            'target_markup_percent',
            'high_risk_shipping_cost',
        ];

        $data = [];

        foreach ($fields as $field) {
            $data[$field] =
                $this->request->input($field);
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function filters(): array
    {
        return [
            'store_id' => max(
                0,
                (int) $this->request->input(
                    'store_id'
                )
            ),
            'supplier_id' => max(
                0,
                (int) $this->request->input(
                    'supplier_id'
                )
            ),
            'recommendation' => trim(
                (string) $this->request->input(
                    'recommendation'
                )
            ),
            'review_status' => trim(
                (string) $this->request->input(
                    'review_status'
                )
            ),
            'stock_status' => trim(
                (string) $this->request->input(
                    'stock_status'
                )
            ),
            'sort' => trim(
                (string) $this->request->input(
                    'sort',
                    'score_desc'
                )
            ),
            'q' => trim(
                (string) $this->request->input('q')
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function filtersFromRequest(): array
    {
        return [
            'store_id' => max(
                0,
                (int) $this->request->input(
                    'filter_store_id'
                )
            ),
            'supplier_id' => max(
                0,
                (int) $this->request->input(
                    'filter_supplier_id'
                )
            ),
            'recommendation' => trim(
                (string) $this->request->input(
                    'filter_recommendation'
                )
            ),
            'review_status' => trim(
                (string) $this->request->input(
                    'filter_review_status'
                )
            ),
            'stock_status' => trim(
                (string) $this->request->input(
                    'filter_stock_status'
                )
            ),
            'sort' => trim(
                (string) $this->request->input(
                    'filter_sort',
                    'score_desc'
                )
            ),
            'q' => trim(
                (string) $this->request->input(
                    'filter_q'
                )
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

        return '/admin/product-sourcing'
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
            'product_sourcing_error'
        ] = $message;

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

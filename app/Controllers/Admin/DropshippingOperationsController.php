<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Repositories\DropshippingOperationsRepository;

class DropshippingOperationsController extends Controller
{
    public function __construct(
        private DropshippingOperationsRepository $operations
    ) {
        parent::__construct();
    }

    public function index()
    {
        $filters = $this->filters();

        return $this->view(
            'admin.dropshipping.index',
            [
                'title' =>
                    'Dropshipping Operations Command Center',
                'filters' => $filters,
                'stores' => $this->operations->stores(),
                'suppliers' =>
                    $this->operations->suppliers(
                        (int) $filters['store_id']
                    ),
                'dashboard' =>
                    $this->operations->dashboard($filters),
            ],
            'admin'
        );
    }

    public function export()
    {
        $filters = $this->filters();
        $rows = $this->operations->exportRows($filters);

        $filename = 'dropshipping-operations-'
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
            'Queue',
            'Reference',
            'Store',
            'Supplier',
            'Status',
            'Amount',
            'Date',
            'Action URL',
            'Note',
        ]);

        foreach ($rows as $row) {
            fputcsv($output, [
                $row['queue'] ?? '',
                $row['reference'] ?? '',
                $row['store'] ?? '',
                $row['supplier'] ?? '',
                $row['status'] ?? '',
                $row['amount'] ?? '',
                $row['date'] ?? '',
                $row['action_url'] ?? '',
                $row['note'] ?? '',
            ]);
        }

        fclose($output);
        exit;
    }

    /**
     * @return array<string, int|float>
     */
    private function filters(): array
    {
        $lookbackDays = (int) $this->request->input(
            'lookback_days',
            30
        );
        $minMargin = (float) $this->request->input(
            'min_margin',
            20
        );

        return [
            'store_id' => max(
                0,
                (int) $this->request->input('store_id')
            ),
            'supplier_id' => max(
                0,
                (int) $this->request->input('supplier_id')
            ),
            'lookback_days' => max(
                1,
                min(365, $lookbackDays)
            ),
            'min_margin' => max(
                0,
                min(100, $minMargin)
            ),
        ];
    }
}

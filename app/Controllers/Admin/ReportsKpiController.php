<?php

declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Core\Controller;
use App\Services\Admin\ReportsKpiService;

class ReportsKpiController extends Controller
{
    public function __construct(private ReportsKpiService $reports)
    {
        parent::__construct();
    }

    public function index()
    {
        $report = $this->reports->report($this->filters());

        return $this->view(
            'admin.reports.index',
            [
                'title' => 'Reports & KPI Center',
                'report' => $report,
            ],
            'admin'
        );
    }

    public function export()
    {
        $filters = $this->filters();
        $rows = $this->reports->exportRows($filters);

        header('Content-Type: text/csv');
        header(
            'Content-Disposition: attachment; filename="mission-control-kpi-report-'
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
            'date_from' => $this->request->input('date_from'),
            'date_to' => $this->request->input('date_to'),
            'store_id' => $this->request->input('store_id'),
            'supplier_id' => $this->request->input('supplier_id'),
        ];
    }
}

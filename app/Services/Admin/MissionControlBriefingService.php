<?php

declare(strict_types=1);

namespace App\Services\Admin;

use App\Repositories\MissionControlBriefingRepository;

class MissionControlBriefingService
{
    public function __construct(
        private MissionControlBriefingRepository $briefings
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function preview(array $filters): array
    {
        $filters = $this->normalizeFilters($filters);

        $alerts = $this->briefings->alertSummary($filters);
        $operations = $this->briefings->operationalMetrics($filters);
        $financial = $this->briefings->financialMetrics($filters);
        $items = $this->items($filters, $operations, $financial);

        $briefing = array_merge(
            $filters,
            $alerts,
            $operations,
            $financial,
            [
                'subject' => $this->subject($filters, $alerts, $operations),
                'executive_summary' => $this->executiveSummary(
                    $filters,
                    $alerts,
                    $operations,
                    $financial
                ),
            ]
        );

        return [
            'filters' => $filters,
            'briefing' => $briefing,
            'items' => $items,
            'stores' => $this->briefings->stores(),
            'suppliers' => $this->briefings->suppliers((int) $filters['store_id']),
            'history' => $this->briefings->recentBriefings($filters, 25),
        ];
    }

    public function savePreview(array $filters, ?int $userId = null): int
    {
        $preview = $this->preview($filters);

        return $this->briefings->save(
            [
                'briefing' => $preview['briefing'],
                'items' => $preview['items'],
            ],
            $userId
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function saved(int $id): array
    {
        $briefing = $this->briefings->find($id);

        if (! $briefing) {
            return [
                'briefing' => null,
                'items' => [],
            ];
        }

        return [
            'briefing' => $briefing,
            'items' => $this->briefings->items($id),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRowsFromPreview(array $filters): array
    {
        $preview = $this->preview($filters);

        return $this->exportRows(
            $preview['briefing'],
            $preview['items']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function exportRowsFromSaved(int $id): array
    {
        $saved = $this->saved($id);

        if (! $saved['briefing']) {
            return [];
        }

        return $this->exportRows(
            $saved['briefing'],
            $saved['items']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function exportRows(array $briefing, array $items): array
    {
        $rows = [];

        $rows[] = [
            'section' => 'Briefing',
            'name' => 'Subject',
            'value' => $briefing['subject'] ?? '',
            'detail' => '',
        ];

        $rows[] = [
            'section' => 'Briefing',
            'name' => 'Executive Summary',
            'value' => $briefing['executive_summary'] ?? '',
            'detail' => '',
        ];

        foreach ([
            'open_alert_count',
            'critical_alert_count',
            'warning_alert_count',
            'acknowledged_alert_count',
            'tracking_gaps',
            'open_exceptions',
            'failed_submissions',
            'open_returns',
            'blocked_stores',
            'sales_revenue',
            'gross_profit',
            'margin_percent',
        ] as $metric) {
            $rows[] = [
                'section' => 'Metrics',
                'name' => ucwords(str_replace('_', ' ', $metric)),
                'value' => $briefing[$metric] ?? 0,
                'detail' => '',
            ];
        }

        foreach ($items as $item) {
            $rows[] = [
                'section' => 'Priority Items',
                'name' => $item['title'] ?? '',
                'value' => $item['severity'] ?? '',
                'detail' => $item['message'] ?? '',
            ];
        }

        return $rows;
    }

    private function subject(
        array $filters,
        array $alerts,
        array $operations
    ): string {
        $riskCount =
            (int) $alerts['critical_alert_count']
            + (int) $alerts['warning_alert_count']
            + (int) $operations['tracking_gaps']
            + (int) $operations['open_exceptions']
            + (int) $operations['failed_submissions'];

        $riskLabel = $riskCount > 0
            ? $riskCount . ' item(s) need attention'
            : 'No urgent blockers detected';

        return 'Mission Control Briefing: '
            . $filters['period_start']
            . ' to '
            . $filters['period_end']
            . ' — '
            . $riskLabel;
    }

    private function executiveSummary(
        array $filters,
        array $alerts,
        array $operations,
        array $financial
    ): string {
        $parts = [];

        $parts[] = 'For '
            . $filters['period_start']
            . ' through '
            . $filters['period_end']
            . ', Mission Control shows '
            . (int) $alerts['open_alert_count']
            . ' open alert(s), including '
            . (int) $alerts['critical_alert_count']
            . ' critical alert(s) and '
            . (int) $alerts['warning_alert_count']
            . ' warning alert(s).';

        $parts[] = 'Operational queues currently show '
            . (int) $operations['tracking_gaps']
            . ' tracking gap(s), '
            . (int) $operations['open_exceptions']
            . ' open fulfillment exception(s), '
            . (int) $operations['failed_submissions']
            . ' failed supplier submission(s), '
            . (int) $operations['open_returns']
            . ' open return(s), and '
            . (int) $operations['blocked_stores']
            . ' blocked store launch(es).';

        $parts[] = 'Financial snapshot for the selected period shows $'
            . number_format((float) $financial['sales_revenue'], 2)
            . ' in revenue, $'
            . number_format((float) $financial['gross_profit'], 2)
            . ' in estimated gross profit, and '
            . number_format((float) $financial['margin_percent'], 1)
            . '% estimated margin.';

        if (
            (int) $alerts['critical_alert_count'] === 0
            && (int) $operations['tracking_gaps'] === 0
            && (int) $operations['open_exceptions'] === 0
            && (int) $operations['failed_submissions'] === 0
        ) {
            $parts[] = 'No urgent operational blockers were detected in the current briefing preview.';
        } else {
            $parts[] = 'Recommended focus: clear critical alerts first, then resolve tracking gaps and failed supplier submissions before scaling more traffic.';
        }

        return implode("\n\n", $parts);
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function items(
        array $filters,
        array $operations,
        array $financial
    ): array {
        $items = [];

        foreach ($this->briefings->alertItems($filters, 15) as $alert) {
            $items[] = [
                'alert_id' => $alert['alert_id'],
                'item_type' => 'alert',
                'severity' => $alert['severity'],
                'title' => $alert['title'],
                'message' => $alert['message'],
                'action_url' => $alert['action_url'],
                'metric_key' => $alert['metric_key'],
                'metric_value' => $alert['metric_value'],
            ];
        }

        $operationalItems = [
            [
                'key' => 'tracking_gaps',
                'severity' => 'critical',
                'title' => 'Tracking gaps need reconciliation',
                'message' => (int) $operations['tracking_gaps']
                    . ' shipped or delivered purchase order(s) are missing tracking.',
                'action_url' => '/admin/tracking-reconciliation',
            ],
            [
                'key' => 'open_exceptions',
                'severity' => 'critical',
                'title' => 'Fulfillment exceptions need review',
                'message' => (int) $operations['open_exceptions']
                    . ' fulfillment exception(s) are open.',
                'action_url' => '/admin/dropshipping',
            ],
            [
                'key' => 'failed_submissions',
                'severity' => 'critical',
                'title' => 'Failed supplier submissions need review',
                'message' => (int) $operations['failed_submissions']
                    . ' supplier submission(s) failed.',
                'action_url' => '/admin/supplier-submissions',
            ],
            [
                'key' => 'open_returns',
                'severity' => 'warning',
                'title' => 'Open returns need processing',
                'message' => (int) $operations['open_returns']
                    . ' return(s) remain open.',
                'action_url' => '/admin/returns',
            ],
            [
                'key' => 'blocked_stores',
                'severity' => 'warning',
                'title' => 'Blocked store launches need attention',
                'message' => (int) $operations['blocked_stores']
                    . ' store launch(es) are blocked.',
                'action_url' => '/admin/multi-store-automation',
            ],
        ];

        foreach ($operationalItems as $item) {
            if ((int) $operations[$item['key']] > 0) {
                $items[] = [
                    'alert_id' => null,
                    'item_type' => 'metric',
                    'severity' => $item['severity'],
                    'title' => $item['title'],
                    'message' => $item['message'],
                    'action_url' => $item['action_url'],
                ];
            }
        }

        if ((float) $financial['sales_revenue'] > 0 && (float) $financial['margin_percent'] < 20) {
            $items[] = [
                'alert_id' => null,
                'item_type' => 'metric',
                'severity' => 'warning',
                'title' => 'Estimated margin is below target',
                'message' => 'Estimated margin is '
                    . number_format((float) $financial['margin_percent'], 1)
                    . '%. Review supplier cost, pricing, and return/refund pressure.',
                'action_url' => '/admin/reports',
            ];
        }

        if (empty($items)) {
            $items[] = [
                'alert_id' => null,
                'item_type' => 'status',
                'severity' => 'info',
                'title' => 'No urgent action items detected',
                'message' => 'Mission Control did not find open alerts or operational blockers for this briefing.',
                'action_url' => '/admin',
            ];
        }

        return array_slice($items, 0, 25);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeFilters(array $filters): array
    {
        $periodEnd = trim((string) ($filters['period_end'] ?? date('Y-m-d')));
        $periodStart = trim((string) ($filters['period_start'] ?? date('Y-m-d', strtotime('-7 days'))));

        if (! $this->validDate($periodStart)) {
            $periodStart = date('Y-m-d', strtotime('-7 days'));
        }

        if (! $this->validDate($periodEnd)) {
            $periodEnd = date('Y-m-d');
        }

        if ($periodStart > $periodEnd) {
            [$periodStart, $periodEnd] = [$periodEnd, $periodStart];
        }

        return [
            'period_start' => $periodStart,
            'period_end' => $periodEnd,
            'store_id' => max(0, (int) ($filters['store_id'] ?? 0)),
            'supplier_id' => max(0, (int) ($filters['supplier_id'] ?? 0)),
        ];
    }

    private function validDate(string $date): bool
    {
        $value = \DateTime::createFromFormat('Y-m-d', $date);

        return $value !== false && $value->format('Y-m-d') === $date;
    }
}

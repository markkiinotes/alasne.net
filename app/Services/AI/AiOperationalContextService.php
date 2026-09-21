<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Repositories\AiAgentRepository;
use App\Repositories\DashboardRepository;
use App\Services\Admin\ReportsKpiService;
use RuntimeException;

class AiOperationalContextService
{
    public const CONTEXT_TYPE =
        'mission_control_operational_snapshot_v1';

    private const MAX_ROWS = 10;
    private const MAX_CONTEXT_CHARS = 18000;

    public function __construct(
        private AiAgentRepository $agents,
        private ReportsKpiService $reports,
        private DashboardRepository $dashboard
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function previewForAgent(
        int $agentId
    ): array {
        $agent = $this->agents->find(
            $agentId
        );

        if (! $agent) {
            throw new RuntimeException(
                'AI agent was not found.'
            );
        }

        $status = strtolower(
            trim(
                (string) (
                    $agent['status']
                    ?? ''
                )
            )
        );

        if (
            ! in_array(
                $status,
                ['draft', 'active'],
                true
            )
        ) {
            throw new RuntimeException(
                'Archived AI agents cannot receive operational context.'
            );
        }

        $capabilities =
            $this->capabilities($agent);

        if (
            ! in_array(
                'operational_snapshot',
                $capabilities,
                true
            )
        ) {
            throw new RuntimeException(
                'This AI agent does not have the operational_snapshot capability.'
            );
        }

        $snapshot = $this->snapshot();
        $json = $this->encode($snapshot);

        return [
            'agent' => $agent,
            'context_type' =>
                self::CONTEXT_TYPE,
            'snapshot' => $snapshot,
            'json' => $json,
            'sha256' =>
                hash('sha256', $json),
            'length' =>
                mb_strlen($json),
        ];
    }

    /**
     * @return array{
     *   type:string,
     *   text:string,
     *   sha256:string,
     *   length:int
     * }
     */
    public function contextForAgent(
        int $agentId
    ): array {
        $preview =
            $this->previewForAgent(
                $agentId
            );

        return [
            'type' =>
                (string) $preview[
                    'context_type'
                ],
            'text' =>
                (string) $preview['json'],
            'sha256' =>
                (string) $preview[
                    'sha256'
                ],
            'length' =>
                (int) $preview['length'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(): array
    {
        $report = $this->reports->report(
            []
        );

        return [
            'schema' =>
                self::CONTEXT_TYPE,
            'period' =>
                $this->whitelist(
                    $report['filters'] ?? [],
                    [
                        'date_from',
                        'date_to',
                    ]
                ),
            'summary' =>
                $this->whitelist(
                    $report['summary'] ?? [],
                    [
                        'revenue',
                        'paid_orders',
                        'average_order_value',
                        'supplier_cost',
                        'gross_profit',
                        'margin_percent',
                        'refunds',
                        'open_returns',
                        'store_credit_issued',
                        'store_credit_redeemed',
                        'tracking_gaps',
                        'open_exceptions',
                        'failed_submissions',
                        'blocked_stores',
                        'approved_sourcing',
                    ]
                ),
            'operations' =>
                $this->rows(
                    $report['operations'] ?? [],
                    [
                        'metric',
                        'value',
                        'detail',
                    ]
                ),
            'sales_by_store' =>
                $this->rows(
                    $report['salesByStore']
                        ?? [],
                    [
                        'store_name',
                        'paid_orders',
                        'revenue',
                        'supplier_cost',
                        'gross_profit',
                        'average_order_value',
                        'margin_percent',
                    ]
                ),
            'returns' =>
                $this->rows(
                    $report['returns'] ?? [],
                    [
                        'status',
                        'return_count',
                        'approved_value',
                    ]
                ),
            'store_readiness' =>
                $this->rows(
                    $report['storeReadiness']
                        ?? [],
                    [
                        'store_name',
                        'store_status',
                        'automation_launch_status',
                        'automation_health_score',
                        'last_automation_audit_at',
                    ]
                ),
            'sourcing' =>
                $this->rows(
                    $report['sourcing'] ?? [],
                    [
                        'status',
                        'product_count',
                        'average_score',
                    ]
                ),
            'recent_orders' =>
                $this->rows(
                    $report['recentOrders']
                        ?? [],
                    [
                        'order_number',
                        'store_name',
                        'payment_status',
                        'dropship_status',
                        'order_total',
                        'created_at',
                    ]
                ),
            'low_stock_products' =>
                $this->rows(
                    $this->dashboard
                        ->lowStockProducts(
                            self::MAX_ROWS
                        ),
                    [
                        'name',
                        'sku',
                        'inventory_quantity',
                        'low_stock_threshold',
                        'store_name',
                    ]
                ),
        ];
    }

    /**
     * @param mixed $rows
     * @param list<string> $fields
     * @return list<array<string, mixed>>
     */
    private function rows(
        mixed $rows,
        array $fields
    ): array {
        if (! is_array($rows)) {
            return [];
        }

        $result = [];

        foreach (
            array_slice(
                $rows,
                0,
                self::MAX_ROWS
            )
            as $row
        ) {
            if (! is_array($row)) {
                continue;
            }

            $result[] =
                $this->whitelist(
                    $row,
                    $fields
                );
        }

        return $result;
    }

    /**
     * @param mixed $row
     * @param list<string> $fields
     * @return array<string, mixed>
     */
    private function whitelist(
        mixed $row,
        array $fields
    ): array {
        if (! is_array($row)) {
            return [];
        }

        $result = [];

        foreach ($fields as $field) {
            if (
                ! array_key_exists(
                    $field,
                    $row
                )
            ) {
                continue;
            }

            $result[$field] =
                $this->normalizeValue(
                    $row[$field]
                );
        }

        return $result;
    }

    private function normalizeValue(
        mixed $value
    ): mixed {
        if (
            is_int($value)
            || is_float($value)
            || is_bool($value)
            || $value === null
        ) {
            return $value;
        }

        if (is_numeric($value)) {
            return str_contains(
                (string) $value,
                '.'
            )
                ? (float) $value
                : (int) $value;
        }

        return mb_substr(
            trim((string) $value),
            0,
            500
        );
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function encode(
        array $snapshot
    ): string {
        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES
            | JSON_UNESCAPED_UNICODE
            | JSON_PRETTY_PRINT
        );

        if (! is_string($json)) {
            throw new RuntimeException(
                'Unable to encode the AI operational snapshot.'
            );
        }

        if (
            mb_strlen($json)
            > self::MAX_CONTEXT_CHARS
        ) {
            throw new RuntimeException(
                'AI operational snapshot exceeded the configured size limit.'
            );
        }

        return $json;
    }

    /**
     * @param array<string, mixed> $agent
     * @return list<string>
     */
    private function capabilities(
        array $agent
    ): array {
        $json =
            $agent['capabilities_json']
            ?? null;

        if (
            ! is_string($json)
            || trim($json) === ''
        ) {
            return [];
        }

        $decoded = json_decode(
            $json,
            true
        );

        if (! is_array($decoded)) {
            return [];
        }

        return array_values(
            array_filter(
                array_map(
                    static fn (
                        mixed $value
                    ): string =>
                        trim(
                            (string) $value
                        ),
                    $decoded
                ),
                static fn (
                    string $value
                ): bool =>
                    $value !== ''
            )
        );
    }
}

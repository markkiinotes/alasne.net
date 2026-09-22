<?php

declare(strict_types=1);

namespace App\Services\AI;

use App\Repositories\AiAgentRepository;
use DateTimeImmutable;
use PDO;
use RuntimeException;

class AiScopedOperationalContextService
{
    public const CONTEXT_TYPE = 'mission_control_store_snapshot_v1';

    private const MAX_ROWS = 10;
    private const MAX_CONTEXT_CHARS = 18000;

    public function __construct(
        private PDO $db,
        private AiAgentRepository $agents
    ) {
    }

    /**
     * The first scoped implementation deliberately permits only a
     * platform super_admin. A store ID is NEVER an authorization grant.
     *
     * @return array<string, mixed>
     */
    public function previewForOperator(
        int $agentId,
        int $operatorId,
        int $storeId,
        string $dateFrom,
        string $dateTo
    ): array {
        $this->assertPlatformOperator($operatorId);

        if ($storeId < 1) {
            throw new RuntimeException('Select one store before building AI context.');
        }

        $from = $this->date($dateFrom);
        $to = $this->date($dateTo);

        if ($from > $to || $from->diff($to)->days > 366) {
            throw new RuntimeException('Select a valid reporting period of at most 367 calendar days.');
        }

        $agent = $this->agents->find($agentId);

        if (! $agent || ! in_array((string) $agent['status'], ['draft', 'active'], true)) {
            throw new RuntimeException('This AI agent cannot receive operational context.');
        }

        $capabilities = json_decode((string) ($agent['capabilities_json'] ?? '[]'), true);

        if (! is_array($capabilities) || ! in_array('operational_snapshot', $capabilities, true)) {
            throw new RuntimeException('This AI agent does not have operational_snapshot capability.');
        }

        $store = $this->store($storeId);

        if ($store === null) {
            throw new RuntimeException('The selected store is not available.');
        }

        $snapshot = [
            'schema' => self::CONTEXT_TYPE,
            'scope' => [
                'store_id' => $storeId,
                'store_name' => mb_substr((string) $store['name'], 0, 255),
                'date_from' => $from->format('Y-m-d'),
                'date_to' => $to->format('Y-m-d'),
            ],
            'summary' => $this->orderSummary($storeId, $from, $to),
            'returns' => $this->returnSummary($storeId, $from, $to),
            'recent_orders' => $this->recentOrders($storeId, $from, $to),
            'low_stock_products' => $this->lowStock($storeId),
        ];

        $json = json_encode(
            $snapshot,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR
        );

        if (mb_strlen($json) > self::MAX_CONTEXT_CHARS) {
            throw new RuntimeException('Scoped AI context exceeds the configured size limit.');
        }

        return [
            'agent' => $agent,
            'snapshot' => $snapshot,
            'context_type' => self::CONTEXT_TYPE,
            'json' => $json,
            'sha256' => hash('sha256', $json),
            'length' => mb_strlen($json),
            'store_id' => $storeId,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];
    }

    /**
     * A store option is not an authorization grant: this list is available
     * only after the same platform-operator authorization used for context.
     *
     * @return list<array<string, mixed>>
     */
    public function storesForOperator(int $operatorId): array
    {
        $this->assertPlatformOperator($operatorId);

        $stmt = $this->db->query("
            SELECT id, name
            FROM stores
            ORDER BY name ASC, id ASC
        ");

        return array_map(
            static fn (array $row): array => [
                'id' => (int) $row['id'],
                'name' => mb_substr((string) $row['name'], 0, 255),
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return array{
     *     type:string,text:string,sha256:string,length:int,
     *     store_id:int,date_from:string,date_to:string
     * }
     */
    public function contextForOperator(
        int $agentId,
        int $operatorId,
        int $storeId,
        string $dateFrom,
        string $dateTo
    ): array {
        $preview = $this->previewForOperator(
            $agentId,
            $operatorId,
            $storeId,
            $dateFrom,
            $dateTo
        );

        return [
            'type' => (string) $preview['context_type'],
            'text' => (string) $preview['json'],
            'sha256' => (string) $preview['sha256'],
            'length' => (int) $preview['length'],
            'store_id' => (int) $preview['store_id'],
            'date_from' => (string) $preview['date_from'],
            'date_to' => (string) $preview['date_to'],
        ];
    }

    private function assertPlatformOperator(int $operatorId): void
    {
        if ($operatorId < 1) {
            throw new RuntimeException('An authenticated operator is required.');
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM role_user ru
            INNER JOIN roles r ON r.id = ru.role_id
            INNER JOIN users u ON u.id = ru.user_id
            WHERE ru.user_id = :user_id
              AND r.slug = 'super_admin'
        ");
        $stmt->execute(['user_id' => $operatorId]);

        if ((int) $stmt->fetchColumn() < 1) {
            throw new RuntimeException('The operator is not authorized for scoped AI reporting.');
        }
    }

    private function date(string $date): DateTimeImmutable
    {
        $date = trim($date);
        $value = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if ($value === false || $value->format('Y-m-d') !== $date) {
            throw new RuntimeException('Reporting dates must use YYYY-MM-DD.');
        }

        return $value;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function store(int $storeId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name FROM stores WHERE id = :store_id LIMIT 1
        ");
        $stmt->execute(['store_id' => $storeId]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * @return array<string, mixed>
     */
    private function orderSummary(int $storeId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) AS paid_orders,
                   COALESCE(SUM(grand_total), 0) AS revenue
            FROM orders
            WHERE store_id = :store_id
              AND payment_status = 'paid'
              AND created_at >= :date_from
              AND created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)
        ");
        $stmt->execute($this->periodParams($storeId, $from, $to));
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];

        return [
            'paid_orders' => (int) ($row['paid_orders'] ?? 0),
            'revenue' => round((float) ($row['revenue'] ?? 0), 2),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function returnSummary(int $storeId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->db->prepare("
            SELECT status, COUNT(*) AS return_count
            FROM returns
            WHERE store_id = :store_id
              AND created_at >= :date_from
              AND created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)
            GROUP BY status
            ORDER BY status
            LIMIT 10
        ");
        $stmt->execute($this->periodParams($storeId, $from, $to));

        return array_map(
            static fn (array $row): array => [
                'status' => mb_substr((string) $row['status'], 0, 50),
                'return_count' => (int) $row['return_count'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function recentOrders(int $storeId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stmt = $this->db->prepare("
            SELECT order_number, payment_status, grand_total, created_at
            FROM orders
            WHERE store_id = :store_id
              AND created_at >= :date_from
              AND created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)
            ORDER BY created_at DESC, id DESC
            LIMIT 10
        ");
        $stmt->execute($this->periodParams($storeId, $from, $to));

        return array_map(
            static fn (array $row): array => [
                'order_number' => mb_substr((string) $row['order_number'], 0, 100),
                'payment_status' => mb_substr((string) $row['payment_status'], 0, 50),
                'order_total' => round((float) $row['grand_total'], 2),
                'created_at' => (string) $row['created_at'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function lowStock(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT name, sku, inventory_quantity, low_stock_threshold
            FROM products
            WHERE store_id = :store_id
              AND inventory_quantity <= low_stock_threshold
            ORDER BY inventory_quantity ASC, id ASC
            LIMIT 10
        ");
        $stmt->execute(['store_id' => $storeId]);

        return array_map(
            static fn (array $row): array => [
                'name' => mb_substr((string) $row['name'], 0, 255),
                'sku' => mb_substr((string) ($row['sku'] ?? ''), 0, 100),
                'inventory_quantity' => (int) $row['inventory_quantity'],
                'low_stock_threshold' => (int) $row['low_stock_threshold'],
            ],
            $stmt->fetchAll(PDO::FETCH_ASSOC)
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function periodParams(int $storeId, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [
            'store_id' => $storeId,
            'date_from' => $from->format('Y-m-d'),
            'date_to' => $to->format('Y-m-d'),
        ];
    }
}

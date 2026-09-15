<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class ReturnExchangeRepository
{
    private ?array $orderColumns = null;

    public function __construct(
        private PDO $db
    ) {
    }

    public function productsForStore(
        int $storeId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                sku,
                price,
                inventory_quantity,
                status,
                is_visible
            FROM products
            WHERE store_id = :store_id
            AND status = 'active'
            AND is_visible = 1
            ORDER BY name ASC, id ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function lockProduct(
        int $storeId,
        int $productId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                sku,
                price,
                inventory_quantity
            FROM products
            WHERE id = :id
            AND store_id = :store_id
            AND status = 'active'
            AND is_visible = 1
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute([
            'id' => $productId,
            'store_id' => $storeId,
        ]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function createExchange(
        array $return,
        array $lines,
        float $merchandiseValue,
        ?string $notes = null
    ): array {
        $existing = $this->findByReturn(
            (int) $return['id']
        );

        if ($existing) {
            return $existing;
        }

        $exchangeNumber =
            $this->generateExchangeNumber();

        $exchangeOrderId =
            $this->createExchangeOrder(
                $return,
                $exchangeNumber,
                $merchandiseValue
            );

        $header = $this->db->prepare("
            INSERT INTO return_exchanges (
                return_id,
                store_id,
                original_order_id,
                exchange_order_id,
                exchange_number,
                status,
                merchandise_value,
                currency,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :return_id,
                :store_id,
                :original_order_id,
                :exchange_order_id,
                :exchange_number,
                'processing',
                :merchandise_value,
                :currency,
                :notes,
                NOW(),
                NOW()
            )
        ");

        $header->execute([
            'return_id' => (int) $return['id'],
            'store_id' => (int) $return['store_id'],
            'original_order_id' =>
                (int) $return['order_id'],
            'exchange_order_id' => $exchangeOrderId,
            'exchange_number' => $exchangeNumber,
            'merchandise_value' =>
                $this->money($merchandiseValue),
            'currency' => strtoupper(
                (string) ($return['currency'] ?? 'USD')
            ),
            'notes' => $this->nullable($notes),
        ]);

        $exchangeId = (int)
            $this->db->lastInsertId();

        foreach ($lines as $line) {
            $product = $line['product'];
            $quantity = (int) $line['quantity'];
            $lineTotal = round(
                (float) $product['price']
                * $quantity,
                2
            );

            $this->createExchangeItem(
                $exchangeId,
                (int) $line['return_item_id'],
                $product,
                $quantity,
                $lineTotal
            );

            $this->createOrderItem(
                $exchangeOrderId,
                $product,
                $quantity,
                $lineTotal
            );

            $balanceAfter =
                $this->reduceInventory(
                    (int) $product['id'],
                    $quantity
                );

            $this->recordInventoryMovement(
                (int) $product['id'],
                $exchangeOrderId,
                (int) $return['id'],
                -$quantity,
                $balanceAfter,
                'Reserved for exchange '
                . $exchangeNumber
            );
        }

        $this->recordOrderEvent(
            $exchangeOrderId,
            'exchange_order_created',
            'Exchange order created',
            'Created from return '
            . $return['return_number']
            . '.',
            null,
            'paid',
            true
        );

        return $this->findByReturn(
            (int) $return['id']
        ) ?? [];
    }

    public function findByReturn(
        int $returnId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                re.*,
                o.order_number AS exchange_order_number,
                o.status AS exchange_order_status
            FROM return_exchanges re
            INNER JOIN orders o
                ON o.id = re.exchange_order_id
            WHERE re.return_id = :return_id
            LIMIT 1
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        $exchange = $stmt->fetch();

        return $exchange ?: null;
    }

    public function itemsForReturn(
        int $returnId
    ): array {
        $stmt = $this->db->prepare("
            SELECT rei.*
            FROM return_exchange_items rei
            INNER JOIN return_exchanges re
                ON re.id = rei.exchange_id
            WHERE re.return_id = :return_id
            ORDER BY rei.id ASC
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        return $stmt->fetchAll();
    }

    private function createExchangeOrder(
        array $return,
        string $exchangeNumber,
        float $merchandiseValue
    ): int {
        $now = date('Y-m-d H:i:s');

        $data = [
            'order_number' => $exchangeNumber,
            'store_id' => (int) $return['store_id'],
            'customer_id' => (int) $return['customer_id'],
            'status' => 'paid',
            'order_type' => 'exchange',
            'source_return_id' => (int) $return['id'],
            'original_order_id' =>
                (int) $return['order_id'],
            'payment_status' => 'not_required',
            'payment_method_id' => null,
            'payment_method_name' =>
                'Return Exchange',
            'payment_method_code' =>
                'return_exchange',
            'payment_provider' => 'internal',
            'payment_transaction_id' => null,
            'currency' => strtoupper(
                (string) ($return['currency'] ?? 'USD')
            ),
            'subtotal' => $this->money(
                $merchandiseValue
            ),
            'tax_total' => '0.00',
            'tax_rule_id' => null,
            'tax_rule_name' => null,
            'tax_rule_code' => null,
            'tax_rate' => '0.0000',
            'taxable_amount' => '0.00',
            'tax_shipping' => 0,
            'tax_country_code' => null,
            'tax_state_region' => null,
            'tax_postal_code' => null,
            'shipping_total' => '0.00',
            'shipping_method_id' => null,
            'shipping_method_name' =>
                'Return Exchange',
            'shipping_method_code' =>
                'return_exchange',
            'shipping_estimated_days_min' => null,
            'shipping_estimated_days_max' => null,
            'discount_total' => $this->money(
                $merchandiseValue
            ),
            'grand_total' => '0.00',
            'amount_paid' => '0.00',
            'amount_refunded' => '0.00',
            'placed_at' => $now,
            'paid_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ];

        $available = $this->orderColumns();
        $filtered = [];

        foreach ($data as $column => $value) {
            if (isset($available[$column])) {
                $filtered[$column] = $value;
            }
        }

        foreach (
            [
                'order_number',
                'store_id',
                'customer_id',
                'status',
                'subtotal',
                'tax_total',
                'shipping_total',
                'discount_total',
                'grand_total',
                'created_at',
                'updated_at',
            ]
            as $required
        ) {
            if (! array_key_exists($required, $filtered)) {
                throw new RuntimeException(
                    'The orders table is missing required exchange column: '
                    . $required
                );
            }
        }

        $columns = array_keys($filtered);
        $placeholders = array_map(
            static fn (string $column): string =>
                ':' . $column,
            $columns
        );

        $stmt = $this->db->prepare(
            'INSERT INTO orders ('
            . implode(', ', $columns)
            . ') VALUES ('
            . implode(', ', $placeholders)
            . ')'
        );

        $stmt->execute($filtered);

        return (int) $this->db->lastInsertId();
    }

    private function createOrderItem(
        int $orderId,
        array $product,
        int $quantity,
        float $lineTotal
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO order_items (
                order_id,
                product_id,
                product_name,
                product_sku,
                quantity,
                unit_price,
                line_total,
                created_at,
                updated_at
            ) VALUES (
                :order_id,
                :product_id,
                :product_name,
                :product_sku,
                :quantity,
                :unit_price,
                :line_total,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'product_id' => (int) $product['id'],
            'product_name' => $product['name'],
            'product_sku' =>
                $this->nullable($product['sku'] ?? null),
            'quantity' => $quantity,
            'unit_price' =>
                $this->money($product['price']),
            'line_total' =>
                $this->money($lineTotal),
        ]);
    }

    private function createExchangeItem(
        int $exchangeId,
        int $returnItemId,
        array $product,
        int $quantity,
        float $lineTotal
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO return_exchange_items (
                exchange_id,
                return_item_id,
                product_id,
                product_name,
                product_sku,
                quantity,
                unit_price,
                line_total,
                created_at
            ) VALUES (
                :exchange_id,
                :return_item_id,
                :product_id,
                :product_name,
                :product_sku,
                :quantity,
                :unit_price,
                :line_total,
                NOW()
            )
        ");

        $stmt->execute([
            'exchange_id' => $exchangeId,
            'return_item_id' => $returnItemId,
            'product_id' => (int) $product['id'],
            'product_name' => $product['name'],
            'product_sku' =>
                $this->nullable($product['sku'] ?? null),
            'quantity' => $quantity,
            'unit_price' =>
                $this->money($product['price']),
            'line_total' =>
                $this->money($lineTotal),
        ]);
    }

    private function reduceInventory(
        int $productId,
        int $quantity
    ): int {
        $stmt = $this->db->prepare("
            UPDATE products
            SET
                inventory_quantity =
                    inventory_quantity
                    - :quantity_remove,
                updated_at = NOW()
            WHERE id = :product_id
            AND inventory_quantity >= :quantity_check
        ");

        $stmt->execute([
            'product_id' => $productId,
            'quantity_remove' => $quantity,
            'quantity_check' => $quantity,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Replacement inventory changed before the exchange could be created.'
            );
        }

        $balanceStmt = $this->db->prepare("
            SELECT inventory_quantity
            FROM products
            WHERE id = :product_id
            LIMIT 1
        ");

        $balanceStmt->execute([
            'product_id' => $productId,
        ]);

        return (int) $balanceStmt->fetchColumn();
    }

    private function recordInventoryMovement(
        int $productId,
        int $orderId,
        int $returnId,
        int $quantity,
        int $balanceAfter,
        string $note
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO inventory_movements (
                product_id,
                order_id,
                return_id,
                type,
                quantity,
                balance_after,
                note,
                created_at
            ) VALUES (
                :product_id,
                :order_id,
                :return_id,
                'exchange_replacement',
                :quantity,
                :balance_after,
                :note,
                NOW()
            )
        ");

        $stmt->execute([
            'product_id' => $productId,
            'order_id' => $orderId,
            'return_id' => $returnId,
            'quantity' => $quantity,
            'balance_after' => $balanceAfter,
            'note' => $note,
        ]);
    }

    private function recordOrderEvent(
        int $orderId,
        string $type,
        string $title,
        ?string $description,
        ?string $oldValue,
        ?string $newValue,
        bool $isPublic
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO order_events (
                order_id,
                type,
                title,
                description,
                old_value,
                new_value,
                is_public,
                created_at
            ) VALUES (
                :order_id,
                :type,
                :title,
                :description,
                :old_value,
                :new_value,
                :is_public,
                NOW()
            )
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'type' => $type,
            'title' => $title,
            'description' => $this->nullable(
                $description
            ),
            'old_value' => $this->nullable(
                $oldValue
            ),
            'new_value' => $this->nullable(
                $newValue
            ),
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    private function orderColumns(): array
    {
        if ($this->orderColumns !== null) {
            return $this->orderColumns;
        }

        $stmt = $this->db->query(
            'SHOW COLUMNS FROM orders'
        );

        $columns = [];

        foreach ($stmt->fetchAll() as $column) {
            $columns[(string) $column['Field']] = true;
        }

        $this->orderColumns = $columns;

        return $columns;
    }

    private function generateExchangeNumber(): string
    {
        return 'EXC-'
            . date('Ymd-His')
            . '-'
            . strtoupper(
                bin2hex(random_bytes(2))
            );
    }

    private function money(mixed $value): string
    {
        return number_format(
            round((float) $value, 2),
            2,
            '.',
            ''
        );
    }

    private function nullable(mixed $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }
}

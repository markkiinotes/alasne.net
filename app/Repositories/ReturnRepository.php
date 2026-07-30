<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class ReturnRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function filtered(
        array $filters = [],
        int $limit = 250
    ): array {
        $sql = "
            SELECT
                r.*,
                o.order_number,
                o.payment_status,
                o.grand_total,
                s.name AS store_name,
                s.slug AS store_slug,
                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email
            FROM returns r
            INNER JOIN orders o
                ON o.id = r.order_id
            INNER JOIN stores s
                ON s.id = r.store_id
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE 1 = 1
        ";

        $parameters = [];

        $query = trim(
            (string) ($filters['q'] ?? '')
        );

        if ($query !== '') {
            $sql .= "
                AND (
                    r.return_number LIKE :query_return
                    OR o.order_number LIKE :query_order
                    OR c.email LIKE :query_email
                    OR CONCAT(
                        c.first_name,
                        ' ',
                        c.last_name
                    ) LIKE :query_customer
                )
            ";

            $likeQuery = '%' . $query . '%';

            $parameters['query_return'] = $likeQuery;
            $parameters['query_order'] = $likeQuery;
            $parameters['query_email'] = $likeQuery;
            $parameters['query_customer'] = $likeQuery;
        }

        $status = trim(
            (string) ($filters['status'] ?? '')
        );

        if ($status !== '') {
            $sql .= ' AND r.status = :status';
            $parameters['status'] = $status;
        }

        $storeId = (int) (
            $filters['store_id'] ?? 0
        );

        if ($storeId > 0) {
            $sql .= ' AND r.store_id = :store_id';
            $parameters['store_id'] = $storeId;
        }

        $sql .= "
            ORDER BY
                r.created_at DESC,
                r.id DESC
            LIMIT " . max(1, min($limit, 5000));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll();
    }

    public function find(int $returnId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.*,
                o.order_number,
                o.status AS order_status,
                o.payment_status,
                o.payment_provider,
                o.payment_method_name,
                o.currency AS order_currency,
                o.amount_paid,
                o.amount_refunded,
                o.grand_total,
                s.name AS store_name,
                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone
            FROM returns r
            INNER JOIN orders o
                ON o.id = r.order_id
            INNER JOIN stores s
                ON s.id = r.store_id
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE r.id = :id
            LIMIT 1
        ");

        $stmt->execute(['id' => $returnId]);
        $return = $stmt->fetch();

        return $return ?: null;
    }

    public function lock(int $returnId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM returns
            WHERE id = :id
            LIMIT 1
            FOR UPDATE
        ");

        $stmt->execute(['id' => $returnId]);
        $return = $stmt->fetch();

        return $return ?: null;
    }

    public function items(int $returnId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM return_items
            WHERE return_id = :return_id
            ORDER BY id ASC
        ");

        $stmt->execute(['return_id' => $returnId]);

        return $stmt->fetchAll();
    }

    public function lockItems(int $returnId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM return_items
            WHERE return_id = :return_id
            ORDER BY id ASC
            FOR UPDATE
        ");

        $stmt->execute(['return_id' => $returnId]);

        return $stmt->fetchAll();
    }

    public function events(int $returnId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM return_events
            WHERE return_id = :return_id
            ORDER BY created_at ASC, id ASC
        ");

        $stmt->execute(['return_id' => $returnId]);

        return $stmt->fetchAll();
    }

    public function forOrder(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                r.*,
                COUNT(ri.id) AS item_count,
                COALESCE(
                    SUM(ri.quantity_requested),
                    0
                ) AS total_quantity_requested,
                COALESCE(
                    SUM(ri.quantity_received),
                    0
                ) AS total_quantity_received
            FROM returns r
            LEFT JOIN return_items ri
                ON ri.return_id = r.id
            WHERE r.order_id = :order_id
            GROUP BY r.id
            ORDER BY r.created_at DESC, r.id DESC
        ");

        $stmt->execute(['order_id' => $orderId]);

        return $stmt->fetchAll();
    }

    public function orderForReturn(
        int $orderId
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                s.name AS store_name,
                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email
            FROM orders o
            INNER JOIN stores s
                ON s.id = o.store_id
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :order_id
            LIMIT 1
        ");

        $stmt->execute(['order_id' => $orderId]);
        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function availableItemsForOrder(
        int $orderId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                oi.product_name,
                oi.product_sku,
                oi.quantity,
                oi.unit_price,
                oi.line_total,
                COALESCE(
                    SUM(
                        CASE
                            WHEN r.status <> 'cancelled'
                            THEN ri.quantity_requested
                            ELSE 0
                        END
                    ),
                    0
                ) AS quantity_already_requested
            FROM order_items oi
            LEFT JOIN return_items ri
                ON ri.order_item_id = oi.id
            LEFT JOIN returns r
                ON r.id = ri.return_id
            WHERE oi.order_id = :order_id
            GROUP BY oi.id
            ORDER BY oi.id ASC
        ");

        $stmt->execute(['order_id' => $orderId]);
        $items = $stmt->fetchAll();

        foreach ($items as &$item) {
            $item['quantity_available_to_return'] =
                max(
                    0,
                    (int) $item['quantity']
                    - (int) $item[
                        'quantity_already_requested'
                    ]
                );
        }
        unset($item);

        return $items;
    }

    public function createHeader(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO returns (
                return_number,
                store_id,
                order_id,
                status,
                reason_code,
                reason_details,
                customer_notes,
                internal_notes,
                currency,
                requested_refund_amount,
                approved_refund_amount,
                refunded_amount,
                refund_status,
                created_by_user_id,
                requested_at,
                created_at,
                updated_at
            ) VALUES (
                :return_number,
                :store_id,
                :order_id,
                :status,
                :reason_code,
                :reason_details,
                :customer_notes,
                :internal_notes,
                :currency,
                :requested_refund_amount,
                :approved_refund_amount,
                :refunded_amount,
                :refund_status,
                :created_by_user_id,
                NOW(),
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'return_number' => $data['return_number'],
            'store_id' => (int) $data['store_id'],
            'order_id' => (int) $data['order_id'],
            'status' => $data['status'] ?? 'requested',
            'reason_code' => $data['reason_code'] ?? 'other',
            'reason_details' => $this->nullable(
                $data['reason_details'] ?? null
            ),
            'customer_notes' => $this->nullable(
                $data['customer_notes'] ?? null
            ),
            'internal_notes' => $this->nullable(
                $data['internal_notes'] ?? null
            ),
            'currency' => strtoupper(
                (string) ($data['currency'] ?? 'USD')
            ),
            'requested_refund_amount' =>
                $this->money(
                    $data['requested_refund_amount']
                    ?? 0
                ),
            'approved_refund_amount' =>
                $this->money(
                    $data['approved_refund_amount']
                    ?? 0
                ),
            'refunded_amount' =>
                $this->money(
                    $data['refunded_amount']
                    ?? 0
                ),
            'refund_status' =>
                $data['refund_status'] ?? 'none',
            'created_by_user_id' =>
                $data['created_by_user_id'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function createItem(
        int $returnId,
        array $data
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO return_items (
                return_id,
                order_item_id,
                product_id,
                product_name,
                product_sku,
                quantity_ordered,
                quantity_requested,
                quantity_received,
                quantity_restocked,
                quantity_discarded,
                unit_price,
                requested_refund_amount,
                approved_refund_amount,
                reason_code,
                condition_code,
                resolution_code,
                notes,
                created_at,
                updated_at
            ) VALUES (
                :return_id,
                :order_item_id,
                :product_id,
                :product_name,
                :product_sku,
                :quantity_ordered,
                :quantity_requested,
                0,
                0,
                0,
                :unit_price,
                :requested_refund_amount,
                0.00,
                :reason_code,
                NULL,
                :resolution_code,
                :notes,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'return_id' => $returnId,
            'order_item_id' =>
                (int) $data['order_item_id'],
            'product_id' =>
                $data['product_id'] ?? null,
            'product_name' =>
                $data['product_name'],
            'product_sku' =>
                $this->nullable(
                    $data['product_sku'] ?? null
                ),
            'quantity_ordered' =>
                (int) $data['quantity_ordered'],
            'quantity_requested' =>
                (int) $data['quantity_requested'],
            'unit_price' =>
                $this->money($data['unit_price']),
            'requested_refund_amount' =>
                $this->money(
                    $data['requested_refund_amount']
                ),
            'reason_code' =>
                $data['reason_code'] ?? null,
            'resolution_code' =>
                $data['resolution_code'] ?? 'refund',
            'notes' => $this->nullable(
                $data['notes'] ?? null
            ),
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function approve(
        int $returnId,
        ?string $notes = null
    ): void {
        $this->db->prepare("
            UPDATE return_items
            SET
                approved_refund_amount =
                    requested_refund_amount,
                updated_at = NOW()
            WHERE return_id = :return_id
        ")->execute(['return_id' => $returnId]);

        $stmt = $this->db->prepare("
            UPDATE returns
            SET
                status = 'approved',
                approved_refund_amount =
                    requested_refund_amount,
                internal_notes = CASE
                    WHEN :notes_check IS NULL
                    THEN internal_notes
                    WHEN internal_notes IS NULL
                    OR internal_notes = ''
                    THEN :notes_first
                    ELSE CONCAT(
                        internal_notes,
                        '\n',
                        :notes_append
                    )
                END,
                approved_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $returnId,
            'notes_check' => $this->nullable($notes),
            'notes_first' => $this->nullable($notes),
            'notes_append' => $this->nullable($notes),
        ]);
    }

    public function updateReceivedItem(
        int $returnItemId,
        int $quantityReceived,
        int $quantityRestocked,
        int $quantityDiscarded,
        float $approvedRefundAmount,
        ?string $conditionCode,
        ?string $notes
    ): void {
        $stmt = $this->db->prepare("
            UPDATE return_items
            SET
                quantity_received = :quantity_received,
                quantity_restocked = :quantity_restocked,
                quantity_discarded = :quantity_discarded,
                approved_refund_amount =
                    :approved_refund_amount,
                condition_code = :condition_code,
                notes = :notes,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $returnItemId,
            'quantity_received' => $quantityReceived,
            'quantity_restocked' => $quantityRestocked,
            'quantity_discarded' => $quantityDiscarded,
            'approved_refund_amount' =>
                $this->money($approvedRefundAmount),
            'condition_code' =>
                $this->nullable($conditionCode),
            'notes' => $this->nullable($notes),
        ]);
    }

    public function markReceived(
        int $returnId,
        float $approvedRefundAmount,
        ?string $notes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE returns
            SET
                status = 'received',
                approved_refund_amount =
                    :approved_refund_amount,
                internal_notes = CASE
                    WHEN :notes_check IS NULL
                    THEN internal_notes
                    WHEN internal_notes IS NULL
                    OR internal_notes = ''
                    THEN :notes_first
                    ELSE CONCAT(
                        internal_notes,
                        '\n',
                        :notes_append
                    )
                END,
                received_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $returnId,
            'approved_refund_amount' =>
                $this->money($approvedRefundAmount),
            'notes_check' => $this->nullable($notes),
            'notes_first' => $this->nullable($notes),
            'notes_append' => $this->nullable($notes),
        ]);
    }

    public function markCompleted(
        int $returnId,
        string $refundStatus
    ): void {
        $stmt = $this->db->prepare("
            UPDATE returns
            SET
                status = 'completed',
                refund_status = :refund_status,
                completed_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $returnId,
            'refund_status' => $refundStatus,
        ]);
    }

    public function attachRefundResult(
        int $returnId,
        string $refundStatus,
        ?int $transactionId,
        float $processedAmount,
        ?string $notes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE returns
            SET
                refund_status = :refund_status,
                refund_transaction_id =
                    :refund_transaction_id,
                refunded_amount = CASE
                    WHEN :processed_amount_check > 0
                    THEN :processed_amount_value
                    ELSE refunded_amount
                END,
                internal_notes = CASE
                    WHEN :notes_check IS NULL
                    THEN internal_notes
                    WHEN internal_notes IS NULL
                    OR internal_notes = ''
                    THEN :notes_first
                    ELSE CONCAT(
                        internal_notes,
                        '\n',
                        :notes_append
                    )
                END,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $returnId,
            'refund_status' => $refundStatus,
            'refund_transaction_id' =>
                $transactionId,
            'processed_amount_check' =>
                $this->money($processedAmount),
            'processed_amount_value' =>
                $this->money($processedAmount),
            'notes_check' => $this->nullable($notes),
            'notes_first' => $this->nullable($notes),
            'notes_append' => $this->nullable($notes),
        ]);
    }

    public function cancel(
        int $returnId,
        ?string $notes = null
    ): void {
        $stmt = $this->db->prepare("
            UPDATE returns
            SET
                status = 'cancelled',
                internal_notes = CASE
                    WHEN :notes_check IS NULL
                    THEN internal_notes
                    WHEN internal_notes IS NULL
                    OR internal_notes = ''
                    THEN :notes_first
                    ELSE CONCAT(
                        internal_notes,
                        '\n',
                        :notes_append
                    )
                END,
                cancelled_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $returnId,
            'notes_check' => $this->nullable($notes),
            'notes_first' => $this->nullable($notes),
            'notes_append' => $this->nullable($notes),
        ]);
    }

    public function recordEvent(
        int $returnId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        ?int $createdByUserId = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO return_events (
                return_id,
                type,
                title,
                description,
                old_value,
                new_value,
                created_by_user_id,
                created_at
            ) VALUES (
                :return_id,
                :type,
                :title,
                :description,
                :old_value,
                :new_value,
                :created_by_user_id,
                NOW()
            )
        ");

        $stmt->execute([
            'return_id' => $returnId,
            'type' => $type,
            'title' => $title,
            'description' =>
                $this->nullable($description),
            'old_value' =>
                $this->nullable($oldValue),
            'new_value' =>
                $this->nullable($newValue),
            'created_by_user_id' =>
                $createdByUserId,
        ]);
    }

    public function recordOrderEvent(
        int $orderId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        bool $isPublic = false
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
            'description' =>
                $this->nullable($description),
            'old_value' =>
                $this->nullable($oldValue),
            'new_value' =>
                $this->nullable($newValue),
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    public function restockProduct(
        int $productId,
        int $quantity
    ): int {
        if ($quantity <= 0) {
            throw new RuntimeException(
                'Restock quantity must be greater than zero.'
            );
        }

        $stmt = $this->db->prepare("
            UPDATE products
            SET
                inventory_quantity =
                    inventory_quantity + :quantity,
                updated_at = NOW()
            WHERE id = :product_id
        ");

        $stmt->execute([
            'product_id' => $productId,
            'quantity' => $quantity,
        ]);

        if ($stmt->rowCount() !== 1) {
            throw new RuntimeException(
                'Unable to restock the returned product.'
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

    public function recordInventoryRestock(
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
                'return_restock',
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
            'quantity' => abs($quantity),
            'balance_after' => $balanceAfter,
            'note' => $note,
        ]);
    }


    public function storeBySlug(
        string $storeSlug
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                slug
            FROM stores
            WHERE slug = :slug
            LIMIT 1
        ");

        $stmt->execute([
            'slug' => trim($storeSlug),
        ]);

        $store = $stmt->fetch();

        return $store ?: null;
    }

    public function findPublicByCredentials(
        string $storeSlug,
        string $returnNumber,
        string $customerEmail
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                r.id,
                r.return_number,
                r.status,
                r.reason_code,
                r.currency,
                r.requested_refund_amount,
                r.approved_refund_amount,
                r.refund_status,
                r.requested_at,
                r.approved_at,
                r.received_at,
                r.completed_at,
                r.cancelled_at,
                r.created_at,
                o.id AS order_id,
                o.order_number,
                o.status AS order_status,
                o.payment_status,
                o.amount_paid,
                o.amount_refunded,
                s.name AS store_name,
                s.slug AS store_slug,
                CONCAT(
                    c.first_name,
                    ' ',
                    c.last_name
                ) AS customer_name,
                c.email AS customer_email
            FROM returns r
            INNER JOIN orders o
                ON o.id = r.order_id
            INNER JOIN stores s
                ON s.id = r.store_id
            INNER JOIN customers c
                ON c.id = o.customer_id
            WHERE s.slug = :store_slug
            AND r.return_number = :return_number
            AND LOWER(c.email) = LOWER(:customer_email)
            LIMIT 1
        ");

        $stmt->execute([
            'store_slug' => trim($storeSlug),
            'return_number' => trim($returnNumber),
            'customer_email' => trim($customerEmail),
        ]);

        $return = $stmt->fetch();

        return $return ?: null;
    }

    public function publicItems(
        int $returnId
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                product_name,
                product_sku,
                quantity_requested,
                quantity_received,
                quantity_restocked,
                quantity_discarded,
                unit_price,
                requested_refund_amount,
                approved_refund_amount,
                condition_code,
                resolution_code
            FROM return_items
            WHERE return_id = :return_id
            ORDER BY id ASC
        ");

        $stmt->execute([
            'return_id' => $returnId,
        ]);

        return $stmt->fetchAll();
    }

    public function publicEvents(
        int $returnId
    ): array {
        $allowedTypes = [
            'return_requested',
            'return_approved',
            'return_received',
            'return_completed',
            'return_cancelled',
            'refund_succeeded',
            'refund_failed',
        ];

        $placeholders = implode(
            ', ',
            array_fill(0, count($allowedTypes), '?')
        );

        $stmt = $this->db->prepare("
            SELECT
                type,
                title,
                old_value,
                new_value,
                created_at
            FROM return_events
            WHERE return_id = ?
            AND type IN ({$placeholders})
            ORDER BY created_at ASC, id ASC
        ");

        $stmt->execute([
            $returnId,
            ...$allowedTypes,
        ]);

        return $stmt->fetchAll();
    }

    private function money(mixed $value): string
    {
        return number_format(
            round(max(0, (float) $value), 2),
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

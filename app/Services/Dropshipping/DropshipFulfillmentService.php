<?php

declare(strict_types=1);

namespace App\Services\Dropshipping;

use App\Repositories\ProductSupplierRepository;
use App\Services\Suppliers\SupplierSubmissionService;
use App\Services\Notifications\PurchaseOrderCreatedNotificationPublisher;
use PDO;
use RuntimeException;

class DropshipFulfillmentService
{
    public function __construct(
        private PDO $db,
        private ProductSupplierRepository $mappings,
        private SupplierSubmissionService $submissions
    ) {
    }

    public function routePaidOrder(
        int $orderId,
        bool $retryUnassigned = false
    ): array {
        /*
         * Only purchase orders created during this routing run
         * should emit purchase_order.created after commit.
         */
        $newPurchaseOrderIds = [];

        $this->db->beginTransaction();

        try {
            $order = $this->lockOrder($orderId);

            if (! $order) {
                throw new RuntimeException('Order not found.');
            }

            if (
                ($order['payment_status'] ?? '') !== 'paid'
                && ($order['status'] ?? '') !== 'paid'
            ) {
                throw new RuntimeException(
                    'Only paid orders can be routed to suppliers.'
                );
            }

            $items = $this->unroutedItems($orderId);

            if (empty($items)) {
                $summary = $this->summary($orderId);
                $this->db->commit();
                return $summary;
            }

            if ($retryUnassigned) {
                $this->closeOpenExceptions($orderId);
            }

            $groups = [];
            $unassigned = 0;

            foreach ($items as $item) {
                $quantity = (int) $item['quantity'];
                $candidate = $this->mappings->bestCandidate(
                    (int) $order['store_id'],
                    (int) $item['product_id'],
                    $quantity
                );

                if (! $candidate) {
                    $unassigned++;
                    $this->createException(
                        (int) $order['store_id'],
                        $orderId,
                        (int) $item['id'],
                        (int) $item['product_id'],
                        'supplier_mapping_unavailable',
                        'No active supplier mapping has enough known availability for '
                        . $item['product_name']
                        . ' × '
                        . $quantity
                        . '.'
                    );
                    continue;
                }

                $supplierId = (int) $candidate['supplier_id'];
                $groups[$supplierId]['candidate'] = $candidate;
                $groups[$supplierId]['items'][] = $item;
            }

            foreach ($groups as $supplierId => $group) {
                $purchaseOrderCreated = false;

                $purchaseOrderId = $this->findOrCreatePurchaseOrder(
                    $order,
                    (int) $supplierId,
                    (array) $group['candidate'],
                    $purchaseOrderCreated
                );

                if ($purchaseOrderCreated) {
                    $newPurchaseOrderIds[] =
                        $purchaseOrderId;
                }

                foreach ($group['items'] as $item) {
                    $this->addPurchaseOrderItem(
                        $purchaseOrderId,
                        $item,
                        (array) $group['candidate']
                    );
                }

                $this->recalculatePurchaseOrder(
                    $purchaseOrderId
                );

                try {
                    $this->submissions
                        ->autoPrepareForPurchaseOrder(
                            $purchaseOrderId
                        );
                } catch (\Throwable $submissionError) {
                    $this->createException(
                        (int) $order['store_id'],
                        $orderId,
                        null,
                        null,
                        'supplier_submission_prepare_failed_'
                            . (int) $supplierId,
                        'Purchase order '
                            . $purchaseOrderId
                            . ' was routed, but its supplier submission could not be prepared: '
                            . (
                                $submissionError
                                    ->getMessage()
                                ?: 'Unknown preparation error.'
                            )
                    );
                }
            }

            $summary = $this->recalculateOrder(
                $orderId,
                $unassigned
            );

            $this->recordOrderEvent(
                $orderId,
                'dropship_routed',
                $unassigned > 0
                    ? 'Supplier routing needs attention'
                    : 'Order routed to suppliers',
                $unassigned > 0
                    ? $unassigned
                        . ' order line(s) could not be routed. The remaining lines were assigned to supplier purchase orders.'
                    : count($groups)
                        . ' supplier purchase order(s) were generated automatically.',
                (string) ($order['dropship_status'] ?? 'unrouted'),
                (string) $summary['dropship_status'],
                false
            );

            $this->db->commit();

            /*
             * At this point each new purchase order, its items,
             * totals, supplier-submission preparation state, and
             * customer-order routing summary are durable.
             *
             * Event Bridge failures are isolated from fulfillment.
             */
            foreach (
                array_values(
                    array_unique(
                        $newPurchaseOrderIds
                    )
                )
                as $purchaseOrderId
            ) {
                try {
                    $publisher =
                        new PurchaseOrderCreatedNotificationPublisher(
                            $this->db
                        );

                    $publisher->publish(
                        (int) $purchaseOrderId
                    );
                } catch (\Throwable $notificationException) {
                    error_log(
                        '[Alasne purchase_order.created notification] '
                        . $notificationException->getMessage()
                    );
                }
            }

            return $summary;
        } catch (\Throwable $exception) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $exception;
        }
    }

    public function recordAutomationFailure(
        int $orderId,
        string $message
    ): void {
        try {
            $stmt = $this->db->prepare("
                SELECT store_id
                FROM orders
                WHERE id = :id
                LIMIT 1
            ");
            $stmt->execute(['id' => $orderId]);
            $storeId = (int) $stmt->fetchColumn();

            if ($storeId <= 0) {
                return;
            }

            $this->createException(
                $storeId,
                $orderId,
                null,
                null,
                'routing_automation_failure',
                mb_substr(
                    trim($message) ?: 'Supplier routing failed.',
                    0,
                    1000
                )
            );

            $update = $this->db->prepare("
                UPDATE orders
                SET dropship_status = 'attention',
                    updated_at = NOW()
                WHERE id = :id
            ");
            $update->execute(['id' => $orderId]);
        } catch (\Throwable) {
            // Customer checkout remains successful even when
            // the administrative fulfillment alert cannot be saved.
        }
    }

    public function resolveException(
        int $exceptionId,
        string $note
    ): void {
        $stmt = $this->db->prepare("
            UPDATE dropship_exceptions
            SET status = 'resolved',
                resolution_note = :resolution_note,
                resolved_at = NOW(),
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $exceptionId,
            'resolution_note' => trim($note) ?: null,
        ]);
    }

    private function lockOrder(int $orderId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.*,
                NULLIF(TRIM(CONCAT(
                    COALESCE(c.first_name, ''),
                    ' ',
                    COALESCE(c.last_name, '')
                )), '') AS ship_to_name,
                c.email AS ship_to_email,
                c.phone AS ship_to_phone,
                c.address_line_1 AS ship_to_address_line_1,
                c.address_line_2 AS ship_to_address_line_2,
                c.city AS ship_to_city,
                c.state AS ship_to_state,
                c.postal_code AS ship_to_postal_code,
                c.country AS ship_to_country
            FROM orders o
            LEFT JOIN customers c
                ON c.id = o.customer_id
            WHERE o.id = :id
            LIMIT 1
            FOR UPDATE
        ");
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    private function unroutedItems(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT oi.*
            FROM order_items oi
            LEFT JOIN purchase_order_items poi
                ON poi.order_item_id = oi.id
            WHERE oi.order_id = :order_id
            AND poi.id IS NULL
            ORDER BY oi.id ASC
        ");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    private function findOrCreatePurchaseOrder(
        array $order,
        int $supplierId,
        array $candidate,
        bool &$created
    ): int {
        $created = false;

        $find = $this->db->prepare("
            SELECT id
            FROM purchase_orders
            WHERE order_id = :order_id
            AND supplier_id = :supplier_id
            LIMIT 1
            FOR UPDATE
        ");
        $find->execute([
            'order_id' => (int) $order['id'],
            'supplier_id' => $supplierId,
        ]);
        $existing = $find->fetchColumn();
        if ($existing) {
            return (int) $existing;
        }

        $number = 'PO-'
            . date('Ymd-His')
            . '-'
            . (int) $order['id']
            . '-'
            . $supplierId;

        $leadDays = $candidate['lead_time_max']
            ?? $candidate['default_lead_time_max']
            ?? null;
        $expectedShipAt = $leadDays !== null
            ? date('Y-m-d H:i:s', strtotime('+' . (int) $leadDays . ' days'))
            : null;

        $stmt = $this->db->prepare("
            INSERT INTO purchase_orders (
                store_id, supplier_id, order_id,
                purchase_order_number, status, currency,
                expected_ship_at,
                ship_to_name, ship_to_email, ship_to_phone,
                ship_to_address_line_1, ship_to_address_line_2,
                ship_to_city, ship_to_state,
                ship_to_postal_code, ship_to_country,
                created_at, updated_at
            ) VALUES (
                :store_id, :supplier_id, :order_id,
                :purchase_order_number, 'pending', :currency,
                :expected_ship_at,
                :ship_to_name, :ship_to_email, :ship_to_phone,
                :ship_to_address_line_1, :ship_to_address_line_2,
                :ship_to_city, :ship_to_state,
                :ship_to_postal_code, :ship_to_country,
                NOW(), NOW()
            )
        ");
        $stmt->execute([
            'store_id' => (int) $order['store_id'],
            'supplier_id' => $supplierId,
            'order_id' => (int) $order['id'],
            'purchase_order_number' => $number,
            'currency' => strtoupper((string) ($candidate['currency'] ?? 'USD')),
            'expected_ship_at' => $expectedShipAt,
            'ship_to_name' => $order['ship_to_name'] ?? null,
            'ship_to_email' => $order['ship_to_email'] ?? null,
            'ship_to_phone' => $order['ship_to_phone'] ?? null,
            'ship_to_address_line_1' =>
                $order['ship_to_address_line_1'] ?? null,
            'ship_to_address_line_2' =>
                $order['ship_to_address_line_2'] ?? null,
            'ship_to_city' => $order['ship_to_city'] ?? null,
            'ship_to_state' => $order['ship_to_state'] ?? null,
            'ship_to_postal_code' =>
                $order['ship_to_postal_code'] ?? null,
            'ship_to_country' => $order['ship_to_country'] ?? null,
        ]);
        $id = (int) $this->db->lastInsertId();
        $created = true;

        $this->purchaseOrderEvent(
            $id,
            'created',
            'Purchase order generated',
            'Generated automatically from paid customer order '
            . ($order['order_number'] ?? ('#' . $order['id']))
            . '.'
        );

        return $id;
    }

    private function addPurchaseOrderItem(
        int $purchaseOrderId,
        array $item,
        array $candidate
    ): void {
        $quantity = (int) $item['quantity'];
        $unitCost = round((float) $candidate['wholesale_cost'], 2);
        $lineCost = round($unitCost * $quantity, 2);
        $revenue = round((float) $item['line_total'], 2);
        $profit = round($revenue - $lineCost, 2);

        $stmt = $this->db->prepare("
            INSERT IGNORE INTO purchase_order_items (
                purchase_order_id, order_item_id,
                product_id, supplier_product_id,
                supplier_sku, product_name, quantity,
                unit_cost, line_cost,
                customer_unit_price,
                customer_line_revenue,
                estimated_profit, status,
                created_at, updated_at
            ) VALUES (
                :purchase_order_id, :order_item_id,
                :product_id, :supplier_product_id,
                :supplier_sku, :product_name, :quantity,
                :unit_cost, :line_cost,
                :customer_unit_price,
                :customer_line_revenue,
                :estimated_profit, 'pending',
                NOW(), NOW()
            )
        ");
        $stmt->execute([
            'purchase_order_id' => $purchaseOrderId,
            'order_item_id' => (int) $item['id'],
            'product_id' => (int) $item['product_id'],
            'supplier_product_id' => (int) $candidate['id'],
            'supplier_sku' => (string) $candidate['supplier_sku'],
            'product_name' => (string) $item['product_name'],
            'quantity' => $quantity,
            'unit_cost' => number_format($unitCost, 2, '.', ''),
            'line_cost' => number_format($lineCost, 2, '.', ''),
            'customer_unit_price' => number_format((float) $item['unit_price'], 2, '.', ''),
            'customer_line_revenue' => number_format($revenue, 2, '.', ''),
            'estimated_profit' => number_format($profit, 2, '.', ''),
        ]);
    }

    private function recalculatePurchaseOrder(int $id): void
    {
        $stmt = $this->db->prepare("
            SELECT
                COALESCE(SUM(line_cost), 0) AS items_subtotal,
                COALESCE(SUM(customer_line_revenue), 0) AS revenue,
                COALESCE(SUM(estimated_profit), 0) AS profit
            FROM purchase_order_items
            WHERE purchase_order_id = :id
        ");
        $stmt->execute(['id' => $id]);
        $totals = $stmt->fetch() ?: [];

        $cost = round((float) ($totals['items_subtotal'] ?? 0), 2);
        $revenue = round((float) ($totals['revenue'] ?? 0), 2);
        $profit = round((float) ($totals['profit'] ?? 0), 2);
        $margin = $revenue > 0
            ? round(($profit / $revenue) * 100, 3)
            : 0.0;

        $update = $this->db->prepare("
            UPDATE purchase_orders
            SET items_subtotal = :items_subtotal,
                total_cost = :total_cost,
                customer_revenue = :customer_revenue,
                estimated_profit = :estimated_profit,
                estimated_margin_percent = :margin,
                updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            'id' => $id,
            'items_subtotal' => number_format($cost, 2, '.', ''),
            'total_cost' => number_format($cost, 2, '.', ''),
            'customer_revenue' => number_format($revenue, 2, '.', ''),
            'estimated_profit' => number_format($profit, 2, '.', ''),
            'margin' => number_format($margin, 3, '.', ''),
        ]);
    }

    private function recalculateOrder(
        int $orderId,
        int $newUnassigned
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS purchase_order_count,
                COALESCE(SUM(total_cost), 0) AS supplier_cost,
                COALESCE(SUM(customer_revenue), 0) AS routed_revenue,
                COALESCE(SUM(estimated_profit), 0) AS profit
            FROM purchase_orders
            WHERE order_id = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        $totals = $stmt->fetch() ?: [];

        $openStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dropship_exceptions
            WHERE order_id = :order_id
            AND status = 'open'
        ");
        $openStmt->execute(['order_id' => $orderId]);
        $openExceptions = (int) $openStmt->fetchColumn();

        $cost = round((float) ($totals['supplier_cost'] ?? 0), 2);
        $revenue = round((float) ($totals['routed_revenue'] ?? 0), 2);
        $profit = round((float) ($totals['profit'] ?? 0), 2);
        $margin = $revenue > 0
            ? round(($profit / $revenue) * 100, 3)
            : 0.0;
        $status = ($openExceptions + $newUnassigned) > 0
            ? 'attention'
            : 'routed';

        $update = $this->db->prepare("
            UPDATE orders
            SET dropship_status = :dropship_status,
                supplier_cost_total = :supplier_cost_total,
                estimated_gross_profit = :estimated_gross_profit,
                estimated_margin_percent = :estimated_margin_percent,
                dropship_routed_at = COALESCE(dropship_routed_at, NOW()),
                updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute([
            'id' => $orderId,
            'dropship_status' => $status,
            'supplier_cost_total' => number_format($cost, 2, '.', ''),
            'estimated_gross_profit' => number_format($profit, 2, '.', ''),
            'estimated_margin_percent' => number_format($margin, 3, '.', ''),
        ]);

        return [
            'order_id' => $orderId,
            'purchase_order_count' => (int) ($totals['purchase_order_count'] ?? 0),
            'open_exception_count' => $openExceptions,
            'supplier_cost_total' => $cost,
            'estimated_gross_profit' => $profit,
            'estimated_margin_percent' => $margin,
            'dropship_status' => $status,
        ];
    }

    private function summary(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id AS order_id,
                dropship_status,
                supplier_cost_total,
                estimated_gross_profit,
                estimated_margin_percent
            FROM orders
            WHERE id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $orderId]);
        $summary = $stmt->fetch() ?: [];

        $poStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM purchase_orders
            WHERE order_id = :order_id
        ");
        $poStmt->execute(['order_id' => $orderId]);
        $summary['purchase_order_count'] = (int) $poStmt->fetchColumn();

        $exStmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM dropship_exceptions
            WHERE order_id = :order_id
            AND status = 'open'
        ");
        $exStmt->execute(['order_id' => $orderId]);
        $summary['open_exception_count'] = (int) $exStmt->fetchColumn();
        return $summary;
    }

    private function createException(
        int $storeId,
        int $orderId,
        ?int $orderItemId,
        ?int $productId,
        string $code,
        string $message
    ): void {
        $sql = "
            SELECT id
            FROM dropship_exceptions
            WHERE order_id = :order_id
            AND exception_code = :exception_code
            AND status = 'open'
        ";
        $params = [
            'order_id' => $orderId,
            'exception_code' => $code,
        ];
        if ($orderItemId === null) {
            $sql .= ' AND order_item_id IS NULL';
        } else {
            $sql .= ' AND order_item_id = :order_item_id';
            $params['order_item_id'] = $orderItemId;
        }
        $sql .= ' LIMIT 1';
        $check = $this->db->prepare($sql);
        $check->execute($params);
        if ($check->fetchColumn()) {
            return;
        }

        $stmt = $this->db->prepare("
            INSERT INTO dropship_exceptions (
                store_id, order_id, order_item_id, product_id,
                exception_code, message, status,
                created_at, updated_at
            ) VALUES (
                :store_id, :order_id, :order_item_id, :product_id,
                :exception_code, :message, 'open',
                NOW(), NOW()
            )
        ");
        $stmt->execute([
            'store_id' => $storeId,
            'order_id' => $orderId,
            'order_item_id' => $orderItemId,
            'product_id' => $productId,
            'exception_code' => $code,
            'message' => mb_substr($message, 0, 1000),
        ]);
    }

    private function closeOpenExceptions(int $orderId): void
    {
        $stmt = $this->db->prepare("
            UPDATE dropship_exceptions
            SET status = 'resolved',
                resolution_note = 'Automatically closed before routing retry.',
                resolved_at = NOW(),
                updated_at = NOW()
            WHERE order_id = :order_id
            AND status = 'open'
        ");
        $stmt->execute(['order_id' => $orderId]);
    }

    private function purchaseOrderEvent(
        int $purchaseOrderId,
        string $type,
        string $title,
        ?string $description = null
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO purchase_order_events (
                purchase_order_id, event_type, title,
                description, is_public, created_at
            ) VALUES (
                :purchase_order_id, :event_type, :title,
                :description, 0, NOW()
            )
        ");
        $stmt->execute([
            'purchase_order_id' => $purchaseOrderId,
            'event_type' => $type,
            'title' => $title,
            'description' => $description,
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
                order_id, type, title, description,
                old_value, new_value, is_public,
                created_at
            ) VALUES (
                :order_id, :type, :title, :description,
                :old_value, :new_value, :is_public,
                NOW()
            )
        ");
        $stmt->execute([
            'order_id' => $orderId,
            'type' => $type,
            'title' => $title,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }
}

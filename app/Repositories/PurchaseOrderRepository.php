<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class PurchaseOrderRepository
{
    public function __construct(private PDO $db) {}

    public function all(array $filters = [], int $limit = 200): array
    {
        $sql = "
            SELECT
                po.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                o.order_number,
                s.name AS store_name,
                COUNT(poi.id) AS item_count,
                COALESCE(SUM(poi.quantity), 0) AS unit_count
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            LEFT JOIN purchase_order_items poi
                ON poi.purchase_order_id = po.id
            WHERE 1 = 1
        ";
        $params = [];

        foreach (['store_id', 'supplier_id'] as $field) {
            $value = (int) ($filters[$field] ?? 0);
            if ($value > 0) {
                $sql .= " AND po.{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        $orderId = (int) ($filters['order_id'] ?? 0);
        if ($orderId > 0) {
            $sql .= ' AND po.order_id = :order_id';
            $params['order_id'] = $orderId;
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $sql .= ' AND po.status = :status';
            $params['status'] = $status;
        }

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $like = '%' . $q . '%';
            $sql .= " AND (
                po.purchase_order_number LIKE :q_po
                OR o.order_number LIKE :q_order
                OR sup.name LIKE :q_supplier
                OR po.tracking_number LIKE :q_tracking
            )";
            $params['q_po'] = $like;
            $params['q_order'] = $like;
            $params['q_supplier'] = $like;
            $params['q_tracking'] = $like;
        }

        $sql .= "
            GROUP BY po.id
            ORDER BY po.id DESC
            LIMIT " . max(1, min(1000, $limit));

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                po.*,
                sup.name AS supplier_name,
                sup.code AS supplier_code,
                sup.email AS supplier_email,
                sup.phone AS supplier_phone,
                sup.website AS supplier_website,
                sup.auto_submit,
                o.order_number,
                o.customer_id,
                o.status AS customer_order_status,
                o.payment_status,
                s.name AS store_name,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                c.email AS customer_email
            FROM purchase_orders po
            INNER JOIN suppliers sup ON sup.id = po.supplier_id
            INNER JOIN orders o ON o.id = po.order_id
            INNER JOIN stores s ON s.id = po.store_id
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE po.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function findOrder(int $orderId): ?array
    {
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
            INNER JOIN stores s ON s.id = o.store_id
            LEFT JOIN customers c ON c.id = o.customer_id
            WHERE o.id = :id
            LIMIT 1
        ");
        $stmt->execute(['id' => $orderId]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public function items(int $purchaseOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT poi.*, p.sku AS product_sku
            FROM purchase_order_items poi
            LEFT JOIN products p ON p.id = poi.product_id
            WHERE poi.purchase_order_id = :purchase_order_id
            ORDER BY poi.id ASC
        ");
        $stmt->execute(['purchase_order_id' => $purchaseOrderId]);
        return $stmt->fetchAll();
    }

    public function events(int $purchaseOrderId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM purchase_order_events
            WHERE purchase_order_id = :purchase_order_id
            ORDER BY id DESC
        ");
        $stmt->execute(['purchase_order_id' => $purchaseOrderId]);
        return $stmt->fetchAll();
    }

    public function exceptionsForOrder(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT de.*, p.name AS product_name, p.sku AS product_sku
            FROM dropship_exceptions de
            LEFT JOIN products p ON p.id = de.product_id
            WHERE de.order_id = :order_id
            ORDER BY de.status = 'open' DESC, de.id DESC
        ");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll();
    }

    public function updateStatus(
        int $id,
        string $status,
        array $data = []
    ): void {
        $purchaseOrder = $this->find($id);
        if (! $purchaseOrder) {
            throw new RuntimeException('Purchase order not found.');
        }

        $allowed = [
            'pending', 'submitted', 'accepted',
            'partially_shipped', 'shipped',
            'delivered', 'cancelled', 'failed',
        ];
        if (! in_array($status, $allowed, true)) {
            throw new RuntimeException('Invalid purchase-order status.');
        }

        $stmt = $this->db->prepare("
            UPDATE purchase_orders SET
                status = :status,
                external_order_id = :external_order_id,
                supplier_reference = :supplier_reference,
                shipping_carrier = :shipping_carrier,
                tracking_number = :tracking_number,
                tracking_url = :tracking_url,
                notes = :notes,
                submitted_at = CASE
                    WHEN :submitted_flag = 1
                    THEN COALESCE(submitted_at, NOW())
                    ELSE submitted_at
                END,
                accepted_at = CASE
                    WHEN :accepted_flag = 1
                    THEN COALESCE(accepted_at, NOW())
                    ELSE accepted_at
                END,
                shipped_at = CASE
                    WHEN :shipped_flag = 1
                    THEN COALESCE(shipped_at, NOW())
                    ELSE shipped_at
                END,
                delivered_at = CASE
                    WHEN :delivered_flag = 1
                    THEN COALESCE(delivered_at, NOW())
                    ELSE delivered_at
                END,
                updated_at = NOW()
            WHERE id = :id
        ");
        $nullable = static function (mixed $v): ?string {
            $v = trim((string) $v);
            return $v !== '' ? $v : null;
        };
        $stmt->execute([
            'id' => $id,
            'status' => $status,
            'external_order_id' => $nullable($data['external_order_id'] ?? null),
            'supplier_reference' => $nullable($data['supplier_reference'] ?? null),
            'shipping_carrier' => $nullable($data['shipping_carrier'] ?? null),
            'tracking_number' => $nullable($data['tracking_number'] ?? null),
            'tracking_url' => $nullable($data['tracking_url'] ?? null),
            'notes' => $nullable($data['notes'] ?? $purchaseOrder['notes'] ?? null),
            'submitted_flag' => in_array($status, ['submitted','accepted','partially_shipped','shipped','delivered'], true) ? 1 : 0,
            'accepted_flag' => in_array($status, ['accepted','partially_shipped','shipped','delivered'], true) ? 1 : 0,
            'shipped_flag' => in_array($status, ['shipped','delivered'], true) ? 1 : 0,
            'delivered_flag' => $status === 'delivered' ? 1 : 0,
        ]);

        $this->event(
            $id,
            'status_updated',
            'Purchase order status updated',
            trim((string) ($data['event_note'] ?? '')) ?: null,
            (string) $purchaseOrder['status'],
            $status
        );
        $this->syncOrderDropshipStatus(
            (int) $purchaseOrder['order_id']
        );

        $this->syncCustomerOrderFulfillment(
            $purchaseOrder,
            $status,
            $data
        );
    }

    public function event(
        int $purchaseOrderId,
        string $type,
        string $title,
        ?string $description = null,
        ?string $oldValue = null,
        ?string $newValue = null,
        bool $isPublic = false
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO purchase_order_events (
                purchase_order_id, event_type, title,
                description, old_value, new_value,
                is_public, created_at
            ) VALUES (
                :purchase_order_id, :event_type, :title,
                :description, :old_value, :new_value,
                :is_public, NOW()
            )
        ");
        $stmt->execute([
            'purchase_order_id' => $purchaseOrderId,
            'event_type' => $type,
            'title' => $title,
            'description' => $description,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'is_public' => $isPublic ? 1 : 0,
        ]);
    }

    public function stores(): array
    {
        return $this->db->query(
            'SELECT id, name FROM stores ORDER BY name ASC'
        )->fetchAll();
    }

    public function suppliers(): array
    {
        return $this->db->query(
            'SELECT id, name, code, store_id FROM suppliers ORDER BY name ASC'
        )->fetchAll();
    }

    private function syncCustomerOrderFulfillment(
        array $purchaseOrder,
        string $status,
        array $data
    ): void {
        $orderId = (int) $purchaseOrder['order_id'];
        $trackingNumber = trim(
            (string) (
                $data['tracking_number']
                ?? $purchaseOrder['tracking_number']
                ?? ''
            )
        );
        $carrier = trim(
            (string) (
                $data['shipping_carrier']
                ?? $purchaseOrder['shipping_carrier']
                ?? ''
            )
        );
        $trackingUrl = trim(
            (string) (
                $data['tracking_url']
                ?? $purchaseOrder['tracking_url']
                ?? ''
            )
        );

        if (
            $trackingNumber !== ''
            && in_array(
                $status,
                [
                    'partially_shipped',
                    'shipped',
                    'delivered',
                ],
                true
            )
        ) {
            $countStmt = $this->db->prepare("
                SELECT COUNT(*)
                FROM purchase_orders
                WHERE order_id = :order_id
            ");
            $countStmt->execute([
                'order_id' => $orderId,
            ]);

            /*
             * The legacy customer-order schema has one
             * tracking-number field. Populate it only for a
             * single-supplier order; multi-supplier tracking
             * remains lossless in purchase_orders and events.
             */
            if ((int) $countStmt->fetchColumn() === 1) {
                $update = $this->db->prepare("
                    UPDATE orders
                    SET
                        shipping_carrier = :carrier,
                        tracking_number = :tracking_number,
                        tracking_url = :tracking_url,
                        shipped_at = CASE
                            WHEN :shipped_flag = 1
                            THEN COALESCE(shipped_at, NOW())
                            ELSE shipped_at
                        END,
                        updated_at = NOW()
                    WHERE id = :id
                ");
                $update->execute([
                    'id' => $orderId,
                    'carrier' =>
                        $carrier !== '' ? $carrier : null,
                    'tracking_number' => $trackingNumber,
                    'tracking_url' =>
                        $trackingUrl !== ''
                            ? $trackingUrl
                            : null,
                    'shipped_flag' => 1,
                ]);
            }
        }

        $description =
            'Supplier '
            . ($purchaseOrder['supplier_name'] ?? '')
            . ' updated purchase order '
            . $purchaseOrder['purchase_order_number']
            . ' to '
            . ucwords(
                str_replace('_', ' ', $status)
            )
            . '.';

        if ($trackingNumber !== '') {
            $description .=
                ' Tracking: '
                . $trackingNumber
                . ($carrier !== ''
                    ? ' via ' . $carrier
                    : '')
                . '.';
        }

        $event = $this->db->prepare("
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
                'supplier_fulfillment_update',
                :title,
                :description,
                :old_value,
                :new_value,
                1,
                NOW()
            )
        ");
        $event->execute([
            'order_id' => $orderId,
            'title' =>
                'Supplier fulfillment update',
            'description' => $description,
            'old_value' =>
                $purchaseOrder['status'] ?? null,
            'new_value' => $status,
        ]);
    }

    private function syncOrderDropshipStatus(int $orderId): void
    {
        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) AS total_count,
                SUM(status = 'delivered') AS delivered_count,
                SUM(status IN ('shipped','partially_shipped')) AS shipped_count,
                SUM(status IN ('failed','cancelled')) AS problem_count
            FROM purchase_orders
            WHERE order_id = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        $summary = $stmt->fetch() ?: [];

        $total = (int) ($summary['total_count'] ?? 0);
        $delivered = (int) ($summary['delivered_count'] ?? 0);
        $shipped = (int) ($summary['shipped_count'] ?? 0);
        $problems = (int) ($summary['problem_count'] ?? 0);

        $status = 'routed';
        if ($total > 0 && $delivered === $total) {
            $status = 'delivered';
        } elseif ($problems > 0) {
            $status = 'attention';
        } elseif ($shipped > 0) {
            $status = 'partially_shipped';
        }

        $update = $this->db->prepare("
            UPDATE orders
            SET dropship_status = :status, updated_at = NOW()
            WHERE id = :id
        ");
        $update->execute(['id' => $orderId, 'status' => $status]);
    }
}

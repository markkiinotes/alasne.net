<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;
use RuntimeException;

class OrderRepository
{
    public function __construct(private PDO $db)
    {
    }

	public function all(array $filters = []): array
	{
		$sql = "
			SELECT
				o.id,
				o.order_number,
				o.store_id,
				o.customer_id,
				o.status,
				o.subtotal,
				o.tax_total,
				o.shipping_total,
				o.discount_total,
				o.grand_total,
				o.placed_at,
				o.created_at,
				s.name AS store_name,
				CONCAT(c.first_name, ' ', c.last_name) AS customer_name
			FROM orders o
			INNER JOIN stores s ON s.id = o.store_id
			INNER JOIN customers c ON c.id = o.customer_id
			WHERE 1 = 1
		";

		$params = [];

		if (! empty($filters['store_id'])) {
			$sql .= " AND o.store_id = :store_id";
			$params['store_id'] = (int) $filters['store_id'];
		}

		if (! empty($filters['customer_id'])) {
			$sql .= " AND o.customer_id = :customer_id";
			$params['customer_id'] = (int) $filters['customer_id'];
		}

		if (! empty($filters['status'])) {
			$sql .= " AND o.status = :status";
			$params['status'] = $filters['status'];
		}

		$sql .= " ORDER BY o.created_at DESC";

		$stmt = $this->db->prepare($sql);

		$stmt->execute($params);

		return $stmt->fetchAll();
	}

    public function create(array $order, array $item): int
    {
        $this->db->beginTransaction();

        try {
            $orderStmt = $this->db->prepare("
                INSERT INTO orders (
                    order_number,
                    store_id,
                    customer_id,
                    status,
                    subtotal,
                    tax_total,
                    shipping_total,
                    discount_total,
                    grand_total,
                    placed_at,
                    created_at,
                    updated_at
                ) VALUES (
                    :order_number,
                    :store_id,
                    :customer_id,
                    :status,
                    :subtotal,
                    :tax_total,
                    :shipping_total,
                    :discount_total,
                    :grand_total,
                    NOW(),
                    NOW(),
                    NOW()
                )
            ");

            $orderStmt->execute([
                'order_number' => $order['order_number'],
                'store_id' => $order['store_id'],
                'customer_id' => $order['customer_id'],
                'status' => $order['status'],
                'subtotal' => $order['subtotal'],
                'tax_total' => $order['tax_total'],
                'shipping_total' => $order['shipping_total'],
                'discount_total' => $order['discount_total'],
                'grand_total' => $order['grand_total'],
            ]);

            $orderId = (int) $this->db->lastInsertId();

            $itemStmt = $this->db->prepare("
                INSERT INTO order_items (
                    order_id,
                    product_id,
                    product_name,
                    sku,
                    quantity,
                    unit_price,
                    line_total,
                    created_at,
                    updated_at
                ) VALUES (
                    :order_id,
                    :product_id,
                    :product_name,
                    :sku,
                    :quantity,
                    :unit_price,
                    :line_total,
                    NOW(),
                    NOW()
                )
            ");

            $itemStmt->execute([
                'order_id' => $orderId,
                'product_id' => $item['product_id'],
                'product_name' => $item['product_name'],
                'sku' => $item['sku'] ?: null,
                'quantity' => $item['quantity'],
                'unit_price' => $item['unit_price'],
                'line_total' => $item['line_total'],
            ]);

           $inventoryStmt = $this->db->prepare("
				UPDATE products
				SET 
					inventory_quantity = inventory_quantity - :quantity_remove,
					updated_at = NOW()
				WHERE id = :product_id
				AND inventory_quantity >= :quantity_check
			");

			$inventoryStmt->execute([
				'product_id' => $item['product_id'],
				'quantity_remove' => $item['quantity'],
				'quantity_check' => $item['quantity'],
			]);

			if ($inventoryStmt->rowCount() !== 1) {
				throw new RuntimeException('Not enough inventory to create this order.');
			}
			
			$balanceStmt = $this->db->prepare("
				SELECT inventory_quantity
				FROM products
				WHERE id = :product_id
				LIMIT 1
			");

			$balanceStmt->execute([
				'product_id' => $item['product_id'],
			]);

			$balanceAfter = (int) $balanceStmt->fetchColumn();

			$movementStmt = $this->db->prepare("
				INSERT INTO inventory_movements (
					product_id,
					order_id,
					type,
					quantity,
					balance_after,
					note,
					created_at
				) VALUES (
					:product_id,
					:order_id,
					:type,
					:quantity,
					:balance_after,
					:note,
					NOW()
				)
			");

			$movementStmt->execute([
				'product_id' => $item['product_id'],
				'order_id' => $orderId,
				'type' => 'order_sale',
				'quantity' => -abs((int) $item['quantity']),
				'balance_after' => $balanceAfter,
				'note' => 'Inventory reduced for order ' . $order['order_number'],
			]);

            $this->db->commit();

            return $orderId;
        } catch (\Throwable $exception) {
            $this->db->rollBack();

            throw $exception;
        }
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.id,
                o.order_number,
                o.store_id,
                o.customer_id,
                o.status,
				o.shipping_carrier,
				o.tracking_number,
				o.tracking_url,
				o.shipped_at,
				o.fulfillment_notes,
                o.subtotal,
                o.tax_total,
                o.shipping_total,
                o.discount_total,
                o.grand_total,
                o.placed_at,
                o.created_at,
                s.name AS store_name,
                s.domain AS store_domain,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
                c.email AS customer_email,
                c.phone AS customer_phone,
                c.city AS customer_city,
                c.state AS customer_state,
                c.country AS customer_country
            FROM orders o
            INNER JOIN stores s ON s.id = o.store_id
            INNER JOIN customers c ON c.id = o.customer_id
            WHERE o.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function itemsForOrder(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                oi.id,
                oi.product_id,
                oi.product_name,
                oi.sku,
                oi.quantity,
                oi.unit_price,
                oi.line_total
            FROM order_items oi
            WHERE oi.order_id = :order_id
            ORDER BY oi.id
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }

    public function updateStatus(int $id, string $status): void
	{
		$this->db->beginTransaction();

		try {
			$currentStmt = $this->db->prepare("
				SELECT status
				FROM orders
				WHERE id = :id
				LIMIT 1
				FOR UPDATE
			");

			$currentStmt->execute([
				'id' => $id,
			]);

			$oldStatus = $currentStmt->fetchColumn();

			if ($oldStatus === false) {
				throw new \RuntimeException('Order not found.');
			}

			$stmt = $this->db->prepare("
				UPDATE orders
				SET
					status = :status,
					updated_at = NOW()
				WHERE id = :id
			");

			$stmt->execute([
				'id' => $id,
				'status' => $status,
			]);

			if ((string) $oldStatus !== $status) {
				$this->recordEvent(
					$id,
					'status_change',
					'Order status updated',
					'Order status changed from ' . $oldStatus . ' to ' . $status . '.',
					(string) $oldStatus,
					$status,
					true
				);
			}

			$this->db->commit();
		} catch (\Throwable $exception) {
			$this->db->rollBack();

			throw $exception;
		}
	}
	
	public function updateFulfillment(int $id, array $data): bool
	{
		$this->db->beginTransaction();

		try {
			$currentStmt = $this->db->prepare("
				SELECT
					shipping_carrier,
					tracking_number,
					tracking_url,
					shipped_at,
					fulfillment_notes
				FROM orders
				WHERE id = :id
				LIMIT 1
				FOR UPDATE
			");

			$currentStmt->execute([
				'id' => $id,
			]);

			$oldFulfillment = $currentStmt->fetch();

			if (! $oldFulfillment) {
				throw new \RuntimeException('Order not found.');
			}

			$shippingCarrier = trim(
				(string) ($data['shipping_carrier'] ?? '')
			) ?: null;

			$trackingNumber = trim(
				(string) ($data['tracking_number'] ?? '')
			) ?: null;

			$trackingUrl = trim(
				(string) ($data['tracking_url'] ?? '')
			) ?: null;

			$fulfillmentNotes = trim(
				(string) ($data['fulfillment_notes'] ?? '')
			) ?: null;

			$shippedAt = trim(
				(string) ($data['shipped_at'] ?? '')
			);

			if ($shippedAt !== '') {
				$shippedAt = str_replace(
					'T',
					' ',
					$shippedAt
				);

				if (strlen($shippedAt) === 16) {
					$shippedAt .= ':00';
				}
			} else {
				$shippedAt = null;
			}

			$changed =
				(string) ($oldFulfillment['shipping_carrier'] ?? '') !==
					(string) ($shippingCarrier ?? '')
				||
				(string) ($oldFulfillment['tracking_number'] ?? '') !==
					(string) ($trackingNumber ?? '')
				||
				(string) ($oldFulfillment['tracking_url'] ?? '') !==
					(string) ($trackingUrl ?? '')
				||
				(string) ($oldFulfillment['shipped_at'] ?? '') !==
					(string) ($shippedAt ?? '')
				||
				(string) ($oldFulfillment['fulfillment_notes'] ?? '') !==
					(string) ($fulfillmentNotes ?? '');

			if (! $changed) {
				$this->db->commit();

				return false;
			}

			$stmt = $this->db->prepare("
				UPDATE orders
				SET
					shipping_carrier = :shipping_carrier,
					tracking_number = :tracking_number,
					tracking_url = :tracking_url,
					shipped_at = :shipped_at,
					fulfillment_notes = :fulfillment_notes,
					updated_at = NOW()
				WHERE id = :id
			");

			$stmt->execute([
				'id' => $id,
				'shipping_carrier' => $shippingCarrier,
				'tracking_number' => $trackingNumber,
				'tracking_url' => $trackingUrl,
				'shipped_at' => $shippedAt,
				'fulfillment_notes' => $fulfillmentNotes,
			]);

			$trackingSummary = trim(
				($shippingCarrier ?: 'Carrier not set')
				. ' '
				. ($trackingNumber ?: '')
			);

			$this->recordEvent(
				$id,
				'fulfillment_update',
				'Fulfillment details updated',
				'Shipping and tracking information was updated.',
				null,
				$trackingSummary,
				true
			);

			$this->db->commit();

			return true;

		} catch (\Throwable $exception) {
			if ($this->db->inTransaction()) {
				$this->db->rollBack();
			}

			throw $exception;
		}
	}
	
	
	public function recordEvent(
		int $orderId,
		string $type,
		string $title,
		?string $description = null,
		?string $oldValue = null,
		?string $newValue = null,
		bool $isPublic = true
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
			'description' => $description,
			'old_value' => $oldValue,
			'new_value' => $newValue,
			'is_public' => $isPublic ? 1 : 0,
		]);
	}
	
	public function eventsForOrder(int $orderId): array
	{
		$stmt = $this->db->prepare("
			SELECT
				id,
				order_id,
				type,
				title,
				description,
				old_value,
				new_value,
				is_public,
				created_at
			FROM order_events
			WHERE order_id = :order_id
			ORDER BY created_at DESC, id DESC
		");

		$stmt->execute([
			'order_id' => $orderId,
		]);

		return $stmt->fetchAll();
	}

	public function filtered(array $filters = [], int $limit = 50): array
	{
		$where = [];
		$params = [];

		if (! empty($filters['q'])) {
			$where[] = "(
				o.order_number LIKE :q_order_number
				OR c.first_name LIKE :q_first_name
				OR c.last_name LIKE :q_last_name
				OR c.email LIKE :q_email
			)";

			$search = '%' . $filters['q'] . '%';

			$params['q_order_number'] = $search;
			$params['q_first_name'] = $search;
			$params['q_last_name'] = $search;
			$params['q_email'] = $search;
		}

		if (! empty($filters['status'])) {
			$where[] = "o.status = :status";
			$params['status'] = $filters['status'];
		}

		if (! empty($filters['store_id'])) {
			$where[] = "o.store_id = :store_id";
			$params['store_id'] = (int) $filters['store_id'];
		}

		if (! empty($filters['date_from'])) {
			$where[] = "DATE(COALESCE(o.placed_at, o.created_at)) >= :date_from";
			$params['date_from'] = $filters['date_from'];
		}

		if (! empty($filters['date_to'])) {
			$where[] = "DATE(COALESCE(o.placed_at, o.created_at)) <= :date_to";
			$params['date_to'] = $filters['date_to'];
		}

		$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

		$sql = "
			SELECT
				o.*,
				s.name AS store_name,
				c.first_name AS customer_first_name,
				c.last_name AS customer_last_name,
				c.email AS customer_email,
				CONCAT(c.first_name, ' ', c.last_name) AS customer_name
			FROM orders o
			INNER JOIN stores s ON s.id = o.store_id
			INNER JOIN customers c ON c.id = o.customer_id
			{$whereSql}
			ORDER BY COALESCE(o.placed_at, o.created_at) DESC, o.id DESC
			LIMIT :limit
		";

		$stmt = $this->db->prepare($sql);

		foreach ($params as $key => $value) {
			if ($key === 'store_id') {
				$stmt->bindValue($key, $value, PDO::PARAM_INT);
			} else {
				$stmt->bindValue($key, $value);
			}
		}

		$stmt->bindValue('limit', $limit, PDO::PARAM_INT);

		$stmt->execute();

		return $stmt->fetchAll();
	}

	public function availableStatuses(): array
	{
		return [
			'pending',
			'processing',
			'paid',
			'shipped',
			'completed',
			'cancelled',
			'refunded',
		];
	}
	
}
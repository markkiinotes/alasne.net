<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class DashboardRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function stats(): array
    {
        return [
            'stores' => $this->countTable('stores'),
            'products' => $this->countTable('products'),
            'customers' => $this->countTable('customers'),
            'orders' => $this->countTable('orders'),

            'revenue' => $this->sumOrders('grand_total'),

            'pending_orders' => $this->countOrdersByStatus('pending'),
            'paid_orders' => $this->countOrdersByStatus('paid'),
            'processing_orders' => $this->countOrdersByStatus('processing'),
            'shipped_orders' => $this->countOrdersByStatus('shipped'),
            'cancelled_orders' => $this->countOrdersByStatus('cancelled'),

            'low_stock_products' => $this->countLowStockProducts(),
            'out_of_stock_products' => $this->countOutOfStockProducts(),
        ];
    }

    public function lowStockProducts(int $limit = 8): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                p.sku,
                p.inventory_quantity,
                p.low_stock_threshold,
                s.name AS store_name
            FROM products p
            INNER JOIN stores s ON s.id = p.store_id
            WHERE p.inventory_quantity <= p.low_stock_threshold
            ORDER BY p.inventory_quantity ASC, p.name ASC
            LIMIT :limit
        ");

        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    protected function countTable(string $table): int
    {
        $allowedTables = [
            'stores',
            'products',
            'customers',
            'orders',
        ];

        if (! in_array($table, $allowedTables, true)) {
            return 0;
        }

        $stmt = $this->db->query("SELECT COUNT(*) FROM {$table}");

        return (int) $stmt->fetchColumn();
    }

    protected function sumOrders(string $column): float
    {
        $allowedColumns = [
            'grand_total',
            'subtotal',
            'tax_total',
            'shipping_total',
            'discount_total',
        ];

        if (! in_array($column, $allowedColumns, true)) {
            return 0.0;
        }

        $stmt = $this->db->query("
            SELECT COALESCE(SUM({$column}), 0)
            FROM orders
        ");

        return (float) $stmt->fetchColumn();
    }

    protected function countOrdersByStatus(string $status): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM orders
            WHERE status = :status
        ");

        $stmt->execute([
            'status' => $status,
        ]);

        return (int) $stmt->fetchColumn();
    }

    protected function countLowStockProducts(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM products
            WHERE inventory_quantity > 0
            AND inventory_quantity <= low_stock_threshold
        ");

        return (int) $stmt->fetchColumn();
    }

    protected function countOutOfStockProducts(): int
    {
        $stmt = $this->db->query("
            SELECT COUNT(*)
            FROM products
            WHERE inventory_quantity <= 0
        ");

        return (int) $stmt->fetchColumn();
    }
	
	public function recentOrderEvents(int $limit = 10): array
	{
		$stmt = $this->db->prepare("
			SELECT
				oe.id,
				oe.order_id,
				oe.type,
				oe.title,
				oe.description,
				oe.old_value,
				oe.new_value,
				oe.is_public,
				oe.created_at,

				o.order_number,
				o.status AS order_status,

				s.name AS store_name,

				CONCAT(c.first_name, ' ', c.last_name) AS customer_name
			FROM order_events oe
			INNER JOIN orders o ON o.id = oe.order_id
			INNER JOIN stores s ON s.id = o.store_id
			INNER JOIN customers c ON c.id = o.customer_id
			ORDER BY oe.created_at DESC, oe.id DESC
			LIMIT :limit
		");

		$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll();
	}
}
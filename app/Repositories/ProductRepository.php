<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class ProductRepository
{
    public function __construct(private PDO $db)
    {
    }

	public function all(array $filters = []): array
	{
		$sql = "
			SELECT 
				p.id,
				p.store_id,
				p.name,
				p.slug,
				p.sku,
				p.image_url,
				p.image_alt_text,
				p.is_visible,
				p.is_featured,
				p.sort_order,
				p.price,
				p.cost,
				p.inventory_quantity,
				p.low_stock_threshold,
				p.status,
				p.created_at,
				s.name AS store_name,
				COALESCE(
					GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
					'Uncategorized'
				) AS categories
			FROM products p
			INNER JOIN stores s ON s.id = p.store_id
			LEFT JOIN category_product cp ON cp.product_id = p.id
			LEFT JOIN categories c ON c.id = cp.category_id
			WHERE 1 = 1
		";

		$params = [];

		if (! empty($filters['store_id'])) {
			$sql .= " AND p.store_id = :store_id";
			$params['store_id'] = (int) $filters['store_id'];
		}
		
		if (! empty($filters['category_id'])) {
			$sql .= "
				AND EXISTS (
					SELECT 1
					FROM category_product cp_filter
					WHERE cp_filter.product_id = p.id
					AND cp_filter.category_id = :category_id
				)
			";

			$params['category_id'] = (int) $filters['category_id'];
		}

		if (! empty($filters['status'])) {
			$sql .= " AND p.status = :status";
			$params['status'] = $filters['status'];
		}

		if (! empty($filters['stock'])) {
			if ($filters['stock'] === 'in_stock') {
				$sql .= " AND p.inventory_quantity > p.low_stock_threshold";
			}

			if ($filters['stock'] === 'low_stock') {
				$sql .= " AND p.inventory_quantity > 0";
				$sql .= " AND p.inventory_quantity <= p.low_stock_threshold";
			}

			if ($filters['stock'] === 'out_of_stock') {
				$sql .= " AND p.inventory_quantity <= 0";
			}
		}

		$sql .= "
			GROUP BY
				p.id,
				p.store_id,
				p.name,
				p.slug,
				p.sku,
				p.image_url,
				p.image_alt_text,
				p.is_visible,
				p.is_featured,
				p.sort_order,
				p.price,
				p.cost,
				p.inventory_quantity,
				p.low_stock_threshold,
				p.status,
				p.created_at,
				s.name
			ORDER BY p.created_at DESC
		";

		$stmt = $this->db->prepare($sql);

		$stmt->execute($params);

		return $stmt->fetchAll();
	}
	
    public function find(int $id): ?array
	{
		$stmt = $this->db->prepare("
			SELECT
				p.id,
				p.store_id,
				p.name,
				p.slug,
				p.sku,
				p.description,
				p.meta_title,
				p.meta_description,
				p.seo_keywords,
				p.image_url,
				p.image_alt_text,
				p.is_visible,
				p.is_featured,
				p.sort_order,
				p.price,
				p.cost,
				p.inventory_quantity,
				p.low_stock_threshold,
				p.status,
				p.created_at,
				s.name AS store_name
			FROM products p
			INNER JOIN stores s ON s.id = p.store_id
			WHERE p.id = :id
			LIMIT 1
		");

		$stmt->execute([
			'id' => $id,
		]);

		$product = $stmt->fetch();

		return $product ?: null;
	}

    public function findByStoreAndSlug(int $storeId, string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, store_id, name, slug, sku, description, price, cost, inventory_quantity, status
            FROM products
            WHERE store_id = :store_id
            AND slug = :slug
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'slug' => $slug,
        ]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO products (
                store_id,
                name,
                slug,
                sku,
                description,
				meta_title,
				meta_description,
				seo_keywords,
				image_url,
				image_alt_text,
				is_visible,
				is_featured,
				sort_order,
                price,
                cost,
                inventory_quantity,
				low_stock_threshold,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :name,
                :slug,
                :sku,
                :description,
				:meta_title,
				:meta_description,
				:seo_keywords,
				:image_url,
				:image_alt_text,
				:is_visible,
				:is_featured,
				:sort_order,
                :price,
                :cost,
                :inventory_quantity,
				:low_stock_threshold,
                :status,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $data['store_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sku' => $data['sku'] ?: null,
            'description' => $data['description'] ?: null,
			'meta_title' => $data['meta_title'] ?: null,
			'meta_description' => $data['meta_description'] ?: null,
			'seo_keywords' => $data['seo_keywords'] ?: null,
			'image_url' => $data['image_url'] ?: null,
			'image_alt_text' => $data['image_alt_text'] ?: null,
			'is_visible' => (int) ($data['is_visible'] ?? 1),
			'is_featured' => (int) ($data['is_featured'] ?? 0),
			'sort_order' => (int) ($data['sort_order'] ?? 0),
            'price' => $data['price'] ?: 0,
            'cost' => $data['cost'] ?: 0,
            'inventory_quantity' => $data['inventory_quantity'] ?: 0,
			'low_stock_threshold' => $data['low_stock_threshold'] ?: 5,
            'status' => $data['status'] ?: 'draft',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE products
            SET 
                store_id = :store_id,
                name = :name,
                slug = :slug,
                sku = :sku,
				description = :description,
				meta_title = :meta_title,
				meta_description = :meta_description,
				seo_keywords = :seo_keywords,
				image_url = :image_url,
				image_alt_text = :image_alt_text,
				is_visible = :is_visible,
				is_featured = :is_featured,
				sort_order = :sort_order,
                price = :price,
                cost = :cost,
                inventory_quantity = :inventory_quantity,
				low_stock_threshold = :low_stock_threshold,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'store_id' => $data['store_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'sku' => $data['sku'] ?: null,
            'description' => $data['description'] ?: null,
			'meta_title' => $data['meta_title'] ?: null,
			'meta_description' => $data['meta_description'] ?: null,
			'seo_keywords' => $data['seo_keywords'] ?: null,
			'image_url' => $data['image_url'] ?: null,
			'image_alt_text' => $data['image_alt_text'] ?: null,
			'is_visible' => (int) ($data['is_visible'] ?? 1),
			'is_featured' => (int) ($data['is_featured'] ?? 0),
			'sort_order' => (int) ($data['sort_order'] ?? 0),
            'price' => $data['price'] ?: 0,
            'cost' => $data['cost'] ?: 0,
            'inventory_quantity' => $data['inventory_quantity'] ?: 0,
			'low_stock_threshold' => $data['low_stock_threshold'] ?: 5,
            'status' => $data['status'] ?: 'draft',
        ]);
    }

	public function inventoryMovements(int $productId, int $limit = 10): array
	{
		$stmt = $this->db->prepare("
			SELECT
				im.id,
				im.type,
				im.quantity,
				im.balance_after,
				im.note,
				im.created_at,
				o.order_number
			FROM inventory_movements im
			LEFT JOIN orders o ON o.id = im.order_id
			WHERE im.product_id = :product_id
			ORDER BY im.created_at DESC, im.id DESC
			LIMIT :limit
		");

		$stmt->bindValue('product_id', $productId, PDO::PARAM_INT);
		$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll();
	}

	public function ordersForProduct(int $productId, int $limit = 10): array
	{
		$stmt = $this->db->prepare("
			SELECT
				o.id,
				o.order_number,
				o.status,
				o.grand_total,
				o.created_at,
				CONCAT(c.first_name, ' ', c.last_name) AS customer_name,
				oi.quantity,
				oi.unit_price,
				oi.line_total
			FROM order_items oi
			INNER JOIN orders o ON o.id = oi.order_id
			INNER JOIN customers c ON c.id = o.customer_id
			WHERE oi.product_id = :product_id
			ORDER BY o.created_at DESC
			LIMIT :limit
		");

		$stmt->bindValue('product_id', $productId, PDO::PARAM_INT);
		$stmt->bindValue('limit', $limit, PDO::PARAM_INT);
		$stmt->execute();

		return $stmt->fetchAll();
	}

public function categoryIds(int $productId): array
{
    $stmt = $this->db->prepare("
        SELECT category_id
        FROM category_product
        WHERE product_id = :product_id
    ");

    $stmt->execute([
        'product_id' => $productId,
    ]);

    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

public function categoriesForProduct(int $productId): array
{
    $stmt = $this->db->prepare("
        SELECT
            c.id,
            c.name,
            c.slug,
            c.status,
            s.name AS store_name
        FROM category_product cp
        INNER JOIN categories c ON c.id = cp.category_id
        INNER JOIN stores s ON s.id = c.store_id
        WHERE cp.product_id = :product_id
        ORDER BY c.name
    ");

    $stmt->execute([
        'product_id' => $productId,
    ]);

    return $stmt->fetchAll();
}

public function categoriesBelongToStore(int $storeId, array $categoryIds): bool
{
    $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

    if (empty($categoryIds)) {
        return true;
    }

    $placeholders = [];
    $params = [
        'store_id' => $storeId,
    ];

    foreach ($categoryIds as $index => $categoryId) {
        $key = 'category_id_' . $index;
        $placeholders[] = ':' . $key;
        $params[$key] = $categoryId;
    }

    $stmt = $this->db->prepare("
        SELECT COUNT(*)
        FROM categories
        WHERE store_id = :store_id
        AND id IN (" . implode(', ', $placeholders) . ")
    ");

    $stmt->execute($params);

    return (int) $stmt->fetchColumn() === count($categoryIds);
}

public function syncCategories(int $productId, array $categoryIds): void
{
    $categoryIds = array_values(array_unique(array_map('intval', $categoryIds)));

    $delete = $this->db->prepare("
        DELETE FROM category_product
        WHERE product_id = :product_id
    ");

    $delete->execute([
        'product_id' => $productId,
    ]);

    if (empty($categoryIds)) {
        return;
    }

    $insert = $this->db->prepare("
        INSERT IGNORE INTO category_product (
            category_id,
            product_id,
            created_at
        ) VALUES (
            :category_id,
            :product_id,
            NOW()
        )
    ");

    foreach ($categoryIds as $categoryId) {
        $insert->execute([
            'category_id' => $categoryId,
            'product_id' => $productId,
        ]);
    }
}

}
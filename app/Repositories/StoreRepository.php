<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class StoreRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                name,
                slug,
                domain,
                platform,
                status,
                created_at
            FROM stores
            ORDER BY created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                slug,
                domain,
                platform,
                status,
                created_at
            FROM stores
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $store = $stmt->fetch();

        return $store ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                slug,
                domain,
                platform,
                status,
                created_at
            FROM stores
            WHERE slug = :slug
            LIMIT 1
        ");

        $stmt->execute([
            'slug' => $slug,
        ]);

        $store = $stmt->fetch();

        return $store ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO stores (
                name,
                slug,
                domain,
                platform,
                status,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :slug,
                :domain,
                :platform,
                :status,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] ?: null,
            'platform' => $data['platform'] ?: 'custom',
            'status' => $data['status'] ?: 'active',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE stores
            SET
                name = :name,
                slug = :slug,
                domain = :domain,
                platform = :platform,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'domain' => $data['domain'] ?: null,
            'platform' => $data['platform'] ?: 'custom',
            'status' => $data['status'] ?: 'active',
        ]);
    }

    public function stats(int $storeId): array
    {
        return [
            'products' => $this->countForStore('products', $storeId),
            'customers' => $this->countForStore('customers', $storeId),
            'orders' => $this->countForStore('orders', $storeId),
            'revenue' => $this->revenueForStore($storeId),
            'low_stock_products' => $this->lowStockCountForStore($storeId),
            'out_of_stock_products' => $this->outOfStockCountForStore($storeId),
        ];
    }

    protected function countForStore(string $table, int $storeId): int
    {
        $allowedTables = [
            'products',
            'customers',
            'orders',
        ];

        if (! in_array($table, $allowedTables, true)) {
            return 0;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM {$table}
            WHERE store_id = :store_id
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    protected function revenueForStore(int $storeId): float
    {
        $stmt = $this->db->prepare("
            SELECT COALESCE(SUM(grand_total), 0)
            FROM orders
            WHERE store_id = :store_id
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return (float) $stmt->fetchColumn();
    }

    protected function lowStockCountForStore(int $storeId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE store_id = :store_id
            AND inventory_quantity > 0
            AND inventory_quantity <= low_stock_threshold
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    protected function outOfStockCountForStore(int $storeId): int
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM products
            WHERE store_id = :store_id
            AND inventory_quantity <= 0
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return (int) $stmt->fetchColumn();
    }

    public function productsForStore(int $storeId, int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                sku,
                price,
                inventory_quantity,
                low_stock_threshold,
                status
            FROM products
            WHERE store_id = :store_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function customersForStore(int $storeId, int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                first_name,
                last_name,
                email,
                phone,
                status,
                created_at
            FROM customers
            WHERE store_id = :store_id
            ORDER BY created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function ordersForStore(int $storeId, int $limit = 5): array
    {
        $stmt = $this->db->prepare("
            SELECT
                o.id,
                o.order_number,
                o.status,
                o.grand_total,
                o.created_at,
                CONCAT(c.first_name, ' ', c.last_name) AS customer_name
            FROM orders o
            INNER JOIN customers c ON c.id = o.customer_id
            WHERE o.store_id = :store_id
            ORDER BY o.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function findPublicBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                name,
                slug,
                domain,
                platform,
                status,
                created_at
            FROM stores
            WHERE slug = :slug
            AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            'slug' => $slug,
        ]);

        $store = $stmt->fetch();

        return $store ?: null;
    }

    public function publicCategoriesForStore(int $storeId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.description,
                COUNT(p.id) AS product_count
            FROM categories c
            LEFT JOIN category_product cp ON cp.category_id = c.id
            LEFT JOIN products p ON p.id = cp.product_id
                AND p.status = 'active'
                AND p.is_visible = 1
                AND p.store_id = c.store_id
            WHERE c.store_id = :store_id
            AND c.status = 'active'
            GROUP BY
                c.id,
                c.name,
                c.slug,
                c.description
            ORDER BY c.name ASC
        ");

        $stmt->execute([
            'store_id' => $storeId,
        ]);

        return $stmt->fetchAll();
    }

    public function featuredProductsForStore(int $storeId, int $limit = 6): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                COALESCE(
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
                    'Uncategorized'
                ) AS categories
            FROM products p
            LEFT JOIN category_product cp ON cp.product_id = p.id
            LEFT JOIN categories c ON c.id = cp.category_id
            WHERE p.store_id = :store_id
            AND p.status = 'active'
            AND p.is_visible = 1
            AND p.is_featured = 1
            GROUP BY
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order
            ORDER BY p.sort_order ASC, p.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function publicProductsForStore(int $storeId, int $limit = 24): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                COALESCE(
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
                    'Uncategorized'
                ) AS categories
            FROM products p
            LEFT JOIN category_product cp ON cp.product_id = p.id
            LEFT JOIN categories c ON c.id = cp.category_id
            WHERE p.store_id = :store_id
            AND p.status = 'active'
            AND p.is_visible = 1
            GROUP BY
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order
            ORDER BY p.sort_order ASC, p.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function publicProductForStoreBySlug(int $storeId, string $slug): ?array
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
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                p.status,
                p.created_at
            FROM products p
            WHERE p.store_id = :store_id
            AND p.slug = :slug
            AND p.status = 'active'
            AND p.is_visible = 1
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'slug' => $slug,
        ]);

        $product = $stmt->fetch();

        return $product ?: null;
    }

    public function publicCategoriesForProduct(int $productId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                c.id,
                c.name,
                c.slug,
                c.description
            FROM category_product cp
            INNER JOIN categories c ON c.id = cp.category_id
            WHERE cp.product_id = :product_id
            AND c.status = 'active'
            ORDER BY c.name ASC
        ");

        $stmt->execute([
            'product_id' => $productId,
        ]);

        return $stmt->fetchAll();
    }

    public function relatedProductsForStore(
        int $storeId,
        int $excludeProductId,
        int $limit = 4
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                COALESCE(
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
                    'Uncategorized'
                ) AS categories
            FROM products p
            LEFT JOIN category_product cp ON cp.product_id = p.id
            LEFT JOIN categories c ON c.id = cp.category_id
            WHERE p.store_id = :store_id
            AND p.id != :exclude_product_id
            AND p.status = 'active'
            AND p.is_visible = 1
            GROUP BY
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order
            ORDER BY p.sort_order ASC, p.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('exclude_product_id', $excludeProductId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function publicCategoryForStoreBySlug(int $storeId, string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                id,
                store_id,
                name,
                slug,
                description,
                status,
                created_at
            FROM categories
            WHERE store_id = :store_id
            AND slug = :slug
            AND status = 'active'
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'slug' => $slug,
        ]);

        $category = $stmt->fetch();

        return $category ?: null;
    }

    public function publicProductsForCategory(
        int $storeId,
        int $categoryId,
        int $limit = 24
    ): array {
        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                p.created_at,
                COALESCE(
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
                    'Uncategorized'
                ) AS categories
            FROM products p
            INNER JOIN category_product cp_filter ON cp_filter.product_id = p.id
            LEFT JOIN category_product cp ON cp.product_id = p.id
            LEFT JOIN categories c ON c.id = cp.category_id
            WHERE p.store_id = :store_id
            AND cp_filter.category_id = :category_id
            AND p.status = 'active'
            AND p.is_visible = 1
            GROUP BY
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.meta_title,
                p.meta_description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                p.created_at
            ORDER BY p.sort_order ASC, p.created_at DESC
            LIMIT :limit
        ");

        $stmt->bindValue('store_id', $storeId, PDO::PARAM_INT);
        $stmt->bindValue('category_id', $categoryId, PDO::PARAM_INT);
        $stmt->bindValue('limit', $limit, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll();
    }

    public function publicProductsForCart(int $storeId, array $productIds): array
    {
        $productIds = array_values(array_unique(array_map('intval', $productIds)));

        if (empty($productIds)) {
            return [];
        }

        $placeholders = [];
        $params = [
            'store_id' => $storeId,
        ];

        foreach ($productIds as $index => $productId) {
            $key = 'product_id_' . $index;
            $placeholders[] = ':' . $key;
            $params[$key] = $productId;
        }

        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order,
                COALESCE(
                    GROUP_CONCAT(DISTINCT c.name ORDER BY c.name SEPARATOR ', '),
                    'Uncategorized'
                ) AS categories
            FROM products p
            LEFT JOIN category_product cp ON cp.product_id = p.id
            LEFT JOIN categories c ON c.id = cp.category_id
            WHERE p.store_id = :store_id
            AND p.id IN (" . implode(', ', $placeholders) . ")
            AND p.status = 'active'
            AND p.is_visible = 1
            GROUP BY
                p.id,
                p.name,
                p.slug,
                p.sku,
                p.description,
                p.image_url,
                p.image_alt_text,
                p.price,
                p.inventory_quantity,
                p.low_stock_threshold,
                p.is_featured,
                p.sort_order
            ORDER BY p.sort_order ASC, p.name ASC
        ");

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function publicOrderForStore(int $storeId, int $orderId): ?array
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

                c.first_name,
                c.last_name,
                c.email,
                c.phone,
                c.address_line_1,
                c.address_line_2,
                c.city,
                c.state,
                c.postal_code,
                c.country
            FROM orders o
            INNER JOIN customers c ON c.id = o.customer_id
            WHERE o.id = :order_id
            AND o.store_id = :store_id
            LIMIT 1
        ");

        $stmt->execute([
            'order_id' => $orderId,
            'store_id' => $storeId,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function publicOrderItemsForOrder(int $orderId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                oi.id,
                oi.order_id,
                oi.product_id,
                COALESCE(oi.product_name, p.name, 'Product') AS product_name,
                COALESCE(oi.product_sku, p.sku, '') AS product_sku,
                oi.quantity,
                oi.unit_price,
                oi.line_total,
                p.slug AS product_slug,
                p.image_url,
                p.image_alt_text
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = :order_id
            ORDER BY oi.id ASC
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }

    public function publicOrderForStoreByNumberAndEmail(
        int $storeId,
        string $orderNumber,
        string $email
    ): ?array {
        $stmt = $this->db->prepare("
            SELECT
                o.*,

                s.name AS store_name,
                s.slug AS store_slug,

                c.first_name AS customer_first_name,
                c.last_name AS customer_last_name,
                c.email AS customer_email,
                c.phone AS customer_phone,

                CONCAT(c.first_name, ' ', c.last_name) AS customer_name,

                oa.full_name AS shipping_full_name,
                oa.address_line_1 AS shipping_address_line_1,
                oa.address_line_2 AS shipping_address_line_2,
                oa.city AS shipping_city,
                oa.state_region AS shipping_state_region,
                oa.postal_code AS shipping_postal_code,
                oa.country_code AS shipping_country_code,
                oa.phone AS shipping_phone
            FROM orders o
            INNER JOIN stores s ON s.id = o.store_id
            INNER JOIN customers c ON c.id = o.customer_id
            LEFT JOIN order_addresses oa
                ON oa.order_id = o.id
                AND oa.type = 'shipping'
            WHERE o.store_id = :store_id
            AND o.order_number = :order_number
            AND LOWER(c.email) = LOWER(:email)
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'order_number' => $orderNumber,
            'email' => $email,
        ]);

        $order = $stmt->fetch();

        return $order ?: null;
    }

    public function publicEventsForOrder(int $orderId): array
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
                created_at
            FROM order_events
            WHERE order_id = :order_id
            AND is_public = 1
            ORDER BY created_at ASC, id ASC
        ");

        $stmt->execute([
            'order_id' => $orderId,
        ]);

        return $stmt->fetchAll();
    }
}
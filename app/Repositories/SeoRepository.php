<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class SeoRepository
{
    public function __construct(
        private PDO $db
    ) {
    }

    public function activeStores(): array
    {
        $stmt = $this->db->query("
            SELECT
                id,
                slug
            FROM stores
            WHERE status = 'active'
            AND slug IS NOT NULL
            AND slug <> ''
            ORDER BY id ASC
        ");

        return $stmt->fetchAll();
    }

    public function activeCategories(): array
    {
        $stmt = $this->db->query("
            SELECT
                s.slug AS store_slug,
                c.slug AS category_slug
            FROM categories c
            INNER JOIN stores s
                ON s.id = c.store_id
            WHERE s.status = 'active'
            AND c.status = 'active'
            AND s.slug IS NOT NULL
            AND s.slug <> ''
            AND c.slug IS NOT NULL
            AND c.slug <> ''
            ORDER BY
                s.id ASC,
                c.id ASC
        ");

        return $stmt->fetchAll();
    }

    public function activeProducts(): array
    {
        $stmt = $this->db->query("
            SELECT
                s.slug AS store_slug,
                p.slug AS product_slug
            FROM products p
            INNER JOIN stores s
                ON s.id = p.store_id
            WHERE s.status = 'active'
            AND p.status = 'active'
            AND p.is_visible = 1
            AND s.slug IS NOT NULL
            AND s.slug <> ''
            AND p.slug IS NOT NULL
            AND p.slug <> ''
            ORDER BY
                s.id ASC,
                p.id ASC
        ");

        return $stmt->fetchAll();
    }
}

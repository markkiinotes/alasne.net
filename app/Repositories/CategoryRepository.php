<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class CategoryRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(array $filters = []): array
    {
        $sql = "
            SELECT
                c.id,
                c.store_id,
                c.name,
                c.slug,
                c.description,
                c.status,
                c.created_at,
                s.name AS store_name
            FROM categories c
            INNER JOIN stores s ON s.id = c.store_id
            WHERE 1 = 1
        ";

        $params = [];

        if (! empty($filters['store_id'])) {
            $sql .= " AND c.store_id = :store_id";
            $params['store_id'] = (int) $filters['store_id'];
        }

        if (! empty($filters['status'])) {
            $sql .= " AND c.status = :status";
            $params['status'] = $filters['status'];
        }

        $sql .= " ORDER BY c.created_at DESC";

        $stmt = $this->db->prepare($sql);

        $stmt->execute($params);

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
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
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $category = $stmt->fetch();

        return $category ?: null;
    }

    public function findByStoreAndSlug(int $storeId, string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, store_id, name, slug, description, status
            FROM categories
            WHERE store_id = :store_id
            AND slug = :slug
            LIMIT 1
        ");

        $stmt->execute([
            'store_id' => $storeId,
            'slug' => $slug,
        ]);

        $category = $stmt->fetch();

        return $category ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO categories (
                store_id,
                name,
                slug,
                description,
                status,
                created_at,
                updated_at
            ) VALUES (
                :store_id,
                :name,
                :slug,
                :description,
                :status,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'store_id' => $data['store_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'status' => $data['status'] ?: 'active',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE categories
            SET
                store_id = :store_id,
                name = :name,
                slug = :slug,
                description = :description,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'store_id' => $data['store_id'],
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?: null,
            'status' => $data['status'] ?: 'active',
        ]);
    }
}
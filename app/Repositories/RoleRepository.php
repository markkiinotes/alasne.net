<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class RoleRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT 
                r.id,
                r.name,
                r.slug,
                r.description,
                COALESCE(
                    GROUP_CONCAT(p.slug ORDER BY p.slug SEPARATOR ', '),
                    'No permissions'
                ) AS permissions
            FROM roles r
            LEFT JOIN permission_role pr ON pr.role_id = r.id
            LEFT JOIN permissions p ON p.id = pr.permission_id
            GROUP BY 
                r.id,
                r.name,
                r.slug,
                r.description
            ORDER BY r.name
        ");

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, slug, description
            FROM roles
            WHERE id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $role = $stmt->fetch();

        return $role ?: null;
    }

    public function findBySlug(string $slug): ?array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, slug, description
            FROM roles
            WHERE slug = :slug
            LIMIT 1
        ");

        $stmt->execute([
            'slug' => $slug,
        ]);

        $role = $stmt->fetch();

        return $role ?: null;
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO roles (name, slug, description)
            VALUES (:name, :slug, :description)
        ");

        $stmt->execute([
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE roles
            SET 
                name = :name,
                slug = :slug,
                description = :description,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'slug' => $data['slug'],
            'description' => $data['description'] ?? null,
        ]);
    }

    public function attachToUser(int $userId, int $roleId): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO role_user (user_id, role_id)
            VALUES (:user_id, :role_id)
        ");

        $stmt->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }

    public function attachPermissions(int $roleId, array $permissionIds): void
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO permission_role (permission_id, role_id)
            VALUES (:permission_id, :role_id)
        ");

        foreach ($permissionIds as $permissionId) {
            $stmt->execute([
                'permission_id' => (int) $permissionId,
                'role_id' => $roleId,
            ]);
        }
    }

    public function permissionIds(int $roleId): array
    {
        $stmt = $this->db->prepare("
            SELECT permission_id
            FROM permission_role
            WHERE role_id = :role_id
        ");

        $stmt->execute([
            'role_id' => $roleId,
        ]);

        return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
    }

    public function syncPermissions(int $roleId, array $permissionIds): void
    {
        $delete = $this->db->prepare("
            DELETE FROM permission_role
            WHERE role_id = :role_id
        ");

        $delete->execute([
            'role_id' => $roleId,
        ]);

        $this->attachPermissions($roleId, $permissionIds);
    }
}
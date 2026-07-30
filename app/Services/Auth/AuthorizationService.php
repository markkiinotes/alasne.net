<?php

declare(strict_types=1);

namespace App\Services\Auth;

use PDO;

class AuthorizationService
{
    public function __construct(private PDO $db)
    {
    }

    public function hasRole(int $userId, string $role): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM role_user ru
            INNER JOIN roles r ON r.id = ru.role_id
            WHERE ru.user_id = :user_id
            AND r.slug = :role
        ");

        $stmt->execute([
            'user_id' => $userId,
            'role' => $role,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function can(int $userId, string $permission): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM role_user ru
            INNER JOIN roles r ON r.id = ru.role_id
            INNER JOIN permission_role pr ON pr.role_id = r.id
            INNER JOIN permissions p ON p.id = pr.permission_id
            WHERE ru.user_id = :user_id
            AND p.slug = :permission
        ");

        $stmt->execute([
            'user_id' => $userId,
            'permission' => $permission,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }

    public function permissionsForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT p.slug
            FROM role_user ru
            INNER JOIN roles r ON r.id = ru.role_id
            INNER JOIN permission_role pr ON pr.role_id = r.id
            INNER JOIN permissions p ON p.id = pr.permission_id
            WHERE ru.user_id = :user_id
            ORDER BY p.slug
        ");

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    public function rolesForUser(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT DISTINCT r.slug
            FROM role_user ru
            INNER JOIN roles r ON r.id = ru.role_id
            WHERE ru.user_id = :user_id
            ORDER BY r.slug
        ");

        $stmt->execute([
            'user_id' => $userId,
        ]);

        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
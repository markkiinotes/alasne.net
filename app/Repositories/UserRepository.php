<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\User;
use PDO;

class UserRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.status,
                u.last_login_at,
                u.created_at,
                COALESCE(
                    GROUP_CONCAT(r.name ORDER BY r.name SEPARATOR ', '),
                    'No roles'
                ) AS roles
            FROM users u
            LEFT JOIN role_user ru ON ru.user_id = u.id
            LEFT JOIN roles r ON r.id = ru.role_id
            GROUP BY 
                u.id,
                u.name,
                u.email,
                u.status,
                u.last_login_at,
                u.created_at
            ORDER BY u.created_at DESC
        ");

        return $stmt->fetchAll();
    }

    public function find(int $id): ?array
    {
        $stmt = $this->db->prepare("
            SELECT 
                u.id,
                u.name,
                u.email,
                u.role,
                u.status,
                u.last_login_at,
                u.created_at,
                r.id AS role_id,
                r.slug AS role_slug
            FROM users u
            LEFT JOIN role_user ru ON ru.user_id = u.id
            LEFT JOIN roles r ON r.id = ru.role_id
            WHERE u.id = :id
            LIMIT 1
        ");

        $stmt->execute([
            'id' => $id,
        ]);

        $user = $stmt->fetch();

        return $user ?: null;
    }

    public function findByEmail(string $email): ?User
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, password, role, status
            FROM users
            WHERE email = :email
            LIMIT 1
        ");

        $stmt->execute([
            'email' => $email,
        ]);

        $user = $stmt->fetch();

        if (! $user) {
            return null;
        }

        return new User(
            (int) $user['id'],
            $user['name'],
            $user['email'],
            $user['password'],
            $user['role'],
            $user['status']
        );
    }

    public function create(array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO users (
                name,
                email,
                password,
                role,
                status,
                created_at,
                updated_at
            ) VALUES (
                :name,
                :email,
                :password,
                :role,
                :status,
                NOW(),
                NOW()
            )
        ");

        $stmt->execute([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
            'role' => $data['role'] ?? 'admin',
            'status' => $data['status'] ?? 'active',
        ]);

        return (int) $this->db->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $stmt = $this->db->prepare("
            UPDATE users
            SET 
                name = :name,
                email = :email,
                role = :role,
                status = :status,
                updated_at = NOW()
            WHERE id = :id
        ");

        $stmt->execute([
            'id' => $id,
            'name' => $data['name'],
            'email' => $data['email'],
            'role' => $data['role'],
            'status' => $data['status'],
        ]);
    }

    public function syncRole(int $userId, int $roleId): void
    {
        $delete = $this->db->prepare("
            DELETE FROM role_user
            WHERE user_id = :user_id
        ");

        $delete->execute([
            'user_id' => $userId,
        ]);

        $insert = $this->db->prepare("
            INSERT INTO role_user (user_id, role_id)
            VALUES (:user_id, :role_id)
        ");

        $insert->execute([
            'user_id' => $userId,
            'role_id' => $roleId,
        ]);
    }
}
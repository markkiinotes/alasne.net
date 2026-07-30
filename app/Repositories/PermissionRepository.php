<?php

declare(strict_types=1);

namespace App\Repositories;

use PDO;

class PermissionRepository
{
    public function __construct(private PDO $db)
    {
    }

    public function all(): array
    {
        $stmt = $this->db->query("
            SELECT id, name, slug, description
            FROM permissions
            ORDER BY slug
        ");

        return $stmt->fetchAll();
    }
}
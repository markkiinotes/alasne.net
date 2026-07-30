<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS permission_role (
                permission_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (permission_id, role_id),

                CONSTRAINT fk_permission_role_permission
                    FOREIGN KEY (permission_id) REFERENCES permissions(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_permission_role_role
                    FOREIGN KEY (role_id) REFERENCES roles(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS permission_role");
    }
};
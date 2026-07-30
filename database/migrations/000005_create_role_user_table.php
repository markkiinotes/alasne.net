<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS role_user (
                user_id BIGINT UNSIGNED NOT NULL,
                role_id BIGINT UNSIGNED NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

                PRIMARY KEY (user_id, role_id),

                CONSTRAINT fk_role_user_user
                    FOREIGN KEY (user_id) REFERENCES users(id)
                    ON DELETE CASCADE,

                CONSTRAINT fk_role_user_role
                    FOREIGN KEY (role_id) REFERENCES roles(id)
                    ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS role_user");
    }
};
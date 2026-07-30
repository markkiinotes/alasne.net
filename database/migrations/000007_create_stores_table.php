<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS stores (
                id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                slug VARCHAR(255) NOT NULL UNIQUE,
                domain VARCHAR(255) NULL,
                platform VARCHAR(100) NOT NULL DEFAULT 'custom',
                status VARCHAR(50) NOT NULL DEFAULT 'active',
                created_at DATETIME NULL,
                updated_at DATETIME NULL
            )
        ");
    }

    public function down(): void
    {
        $this->db->exec("DROP TABLE IF EXISTS stores");
    }
};
<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        $database = $_ENV['DB_DATABASE'] ?? '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = 'products'
            AND COLUMN_NAME = 'low_stock_threshold'
        ");

        $stmt->execute([
            'database' => $database,
        ]);

        if ((int) $stmt->fetchColumn() === 0) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN low_stock_threshold INT NOT NULL DEFAULT 5
                AFTER inventory_quantity
            ");
        }
    }

    public function down(): void
    {
        $database = $_ENV['DB_DATABASE'] ?? '';

        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = :database
            AND TABLE_NAME = 'products'
            AND COLUMN_NAME = 'low_stock_threshold'
        ");

        $stmt->execute([
            'database' => $database,
        ]);

        if ((int) $stmt->fetchColumn() > 0) {
            $this->db->exec("
                ALTER TABLE products
                DROP COLUMN low_stock_threshold
            ");
        }
    }
};
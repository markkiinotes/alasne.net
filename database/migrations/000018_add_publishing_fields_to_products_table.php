<?php

declare(strict_types=1);

use App\Core\Migration;

return new class(app()->container->make(\PDO::class)) extends Migration
{
    public function up(): void
    {
        if (! $this->columnExists('products', 'is_visible')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN is_visible TINYINT(1) NOT NULL DEFAULT 1 AFTER image_alt_text
            ");
        }

        if (! $this->columnExists('products', 'is_featured')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN is_featured TINYINT(1) NOT NULL DEFAULT 0 AFTER is_visible
            ");
        }

        if (! $this->columnExists('products', 'sort_order')) {
            $this->db->exec("
                ALTER TABLE products
                ADD COLUMN sort_order INT NOT NULL DEFAULT 0 AFTER is_featured
            ");
        }
    }

    public function down(): void
    {
        if ($this->columnExists('products', 'sort_order')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN sort_order");
        }

        if ($this->columnExists('products', 'is_featured')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN is_featured");
        }

        if ($this->columnExists('products', 'is_visible')) {
            $this->db->exec("ALTER TABLE products DROP COLUMN is_visible");
        }
    }

    private function columnExists(string $table, string $column): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*)
            FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = :table_name
            AND COLUMN_NAME = :column_name
        ");

        $stmt->execute([
            'table_name' => $table,
            'column_name' => $column,
        ]);

        return (int) $stmt->fetchColumn() > 0;
    }
};